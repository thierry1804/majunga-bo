<?php

namespace App\EventSubscriber;

use App\Entity\Booking;
use App\Service\EmailService;
use App\Service\SiteSettingsHelper;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, entity: Booking::class)]
final class BookingNotificationSubscriber
{
    public function __construct(
        private EmailService $emailService,
        private SiteSettingsHelper $siteSettings
    ) {
    }

    public function postPersist(Booking $booking): void
    {
        $serviceLabel = $booking->getServiceType() === 'shuttle' ? 'Navette' : 'Circuit';
        $subject = sprintf('Confirmation de réservation — %s', $serviceLabel);

        $body = sprintf(
            "Bonjour %s,\n\nVotre réservation (%s) a été enregistrée.\nRéférence : %s\nDate : %s\nParticipants : %d\nMontant : %s\nStatut : %s\n\nMerci,\nMadaBooking",
            $booking->getUserName(),
            $serviceLabel,
            $booking->getId(),
            $booking->getBookingDate()?->format('d/m/Y') ?? '',
            $booking->getParticipants(),
            $booking->getTotalPrice(),
            $booking->getStatus()
        );

        try {
            $this->emailService->sendEmail($booking->getUserEmail(), $subject, $body);

            $adminEmail = $this->siteSettings->getAdminNotificationEmail();
            if ($adminEmail) {
                $this->emailService->sendEmail(
                    $adminEmail,
                    'Nouvelle réservation — ' . $booking->getId(),
                    $body
                );
            }
        } catch (\Throwable) {
            // Ne pas bloquer la création de booking si l'email échoue
        }
    }
}
