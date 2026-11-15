<?php

namespace App\EventSubscriber;

use App\Entity\Booking;
use App\Service\BrandedEmailRenderer;
use App\Service\EmailService;
use App\Service\SiteSettingsHelper;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, entity: Booking::class)]
final class BookingNotificationSubscriber
{
    public function __construct(
        private EmailService $emailService,
        private SiteSettingsHelper $siteSettings,
        private BrandedEmailRenderer $renderer
    ) {
    }

    public function postPersist(Booking $booking): void
    {
        $serviceLabel = match ($booking->getServiceType()) {
            'shuttle' => 'Navette',
            'custom_shuttle' => 'Navette sur mesure',
            default => 'Circuit',
        };

        try {
            $customer = $this->renderer->bookingConfirmation($booking);
            $this->emailService->sendEmail(
                $booking->getUserEmail(),
                sprintf('Confirmation de réservation : %s', $serviceLabel),
                $customer['html'],
                true,
                null,
                null,
                $customer['text']
            );

            $adminEmail = $this->siteSettings->getAdminNotificationEmail();
            if ($adminEmail) {
                $admin = $this->renderer->bookingAdminNotification($booking);
                $this->emailService->sendEmail(
                    $adminEmail,
                    sprintf('Nouvelle réservation : %s, %s', $serviceLabel, $booking->getUserName()),
                    $admin['html'],
                    true,
                    null,
                    null,
                    $admin['text']
                );
            }
        } catch (\Throwable) {
            // Ne pas bloquer la création de la réservation si l'e-mail échoue
        }
    }
}
