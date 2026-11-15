<?php

namespace App\EventSubscriber;

use App\Entity\Booking;
use App\Service\BrandedEmailRenderer;
use App\Service\EmailService;
use App\Service\MailLogger;
use App\Service\SiteSettingsHelper;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Psr\Container\ContainerInterface;

/**
 * Envoie les e-mails d'une nouvelle réservation.
 *
 * L'écouteur postPersist s'exécute à l'intérieur de la transaction de création : on ne fait donc
 * que mémoriser la réservation. Les e-mails partent après la réponse (kernel.terminate), donc après
 * le commit, sans ralentir la requête ni toucher à la transaction. Chaque e-mail a son propre
 * try/catch et tout échec est journalisé dans var/log/mail.log.
 */
#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: Booking::class)]
final class BookingNotificationSubscriber
{
    /** @var array<int, Booking> */
    private array $pending = [];

    public function __construct(
        // Services résolus à l'envoi seulement : une variable d'environnement manquante
        // (MAILER_DSN, MAILER_FROM_EMAIL) est alors journalisée au lieu de casser la réservation.
        #[AutowireLocator([
            EmailService::class,
            BrandedEmailRenderer::class,
            SiteSettingsHelper::class,
        ])]
        private ContainerInterface $services,
        private EntityManagerInterface $entityManager,
        private MailLogger $logger
    ) {
    }

    public function postPersist(Booking $booking): void
    {
        $this->pending[] = $booking;
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    #[AsEventListener(event: ConsoleEvents::TERMINATE)]
    public function onTerminate(): void
    {
        $this->flushPending();
    }

    public function flushPending(): void
    {
        $bookings = $this->pending;
        $this->pending = [];

        foreach ($bookings as $booking) {
            // Si la transaction a échoué, l'EntityManager est fermé : la réservation n'existe pas.
            if (!$this->entityManager->isOpen()) {
                $this->logger->warning('E-mails ignorés : la création de la réservation a échoué (EntityManager fermé).', [
                    'booking' => $booking->getId(),
                ]);
                continue;
            }
            $this->notify($booking);
        }
    }

    private function notify(Booking $booking): void
    {
        $id = $booking->getId();
        $serviceLabel = match ($booking->getServiceType()) {
            'shuttle' => 'Navette',
            'custom_shuttle' => 'Navette sur mesure',
            default => 'Circuit',
        };

        // 1. E-mail de confirmation au client
        $this->attempt('confirmation_client', $id, $booking->getUserEmail(), function () use ($booking, $serviceLabel): void {
            $customer = $this->services->get(BrandedEmailRenderer::class)->bookingConfirmation($booking);
            $this->services->get(EmailService::class)->sendEmail(
                $booking->getUserEmail(),
                sprintf('Confirmation de réservation : %s', $serviceLabel),
                $customer['html'],
                true,
                null,
                null,
                $customer['text']
            );
        });

        // 2. E-mail à l'équipe, indépendant du premier
        $adminEmail = null;
        try {
            $adminEmail = $this->services->get(SiteSettingsHelper::class)->getAdminNotificationEmail();
        } catch (\Throwable $e) {
            $this->logFailure('lecture_admin_notification_email', $id, null, $e);
        }

        if (!$adminEmail) {
            $this->logger->info('Pas d\'e-mail équipe : paramètre admin_notification_email absent ou vide.', ['booking' => $id]);

            return;
        }

        $this->attempt('notification_equipe', $id, $adminEmail, function () use ($booking, $serviceLabel, $adminEmail): void {
            $admin = $this->services->get(BrandedEmailRenderer::class)->bookingAdminNotification($booking);
            $this->services->get(EmailService::class)->sendEmail(
                $adminEmail,
                sprintf('Nouvelle réservation : %s, %s', $serviceLabel, $booking->getUserName()),
                $admin['html'],
                true,
                null,
                null,
                $admin['text']
            );
        });
    }

    private function attempt(string $type, ?string $bookingId, ?string $to, callable $send): void
    {
        try {
            $send();
            $this->logger->info('E-mail envoyé.', ['type' => $type, 'booking' => $bookingId, 'to' => MailLogger::mask($to)]);
        } catch (\Throwable $e) {
            $this->logFailure($type, $bookingId, $to, $e);
        }
    }

    private function logFailure(string $type, ?string $bookingId, ?string $to, \Throwable $e): void
    {
        $this->logger->error('Échec d\'envoi d\'e-mail.', [
            'type' => $type,
            'booking' => $bookingId,
            'to' => MailLogger::mask($to),
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'at' => basename($e->getFile()) . ':' . $e->getLine(),
        ]);
    }
}
