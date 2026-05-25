<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\ShuttleSchedule;
use App\Entity\Tour;
use App\Repository\ShuttleScheduleRepository;
use App\Repository\TourRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class PublicBookingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TourRepository $tourRepository,
        private ShuttleScheduleRepository $shuttleScheduleRepository,
        private SiteSettingsHelper $siteSettings
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createFromPublicRequest(array $data): Booking
    {
        if (!$this->siteSettings->isBookingEnabled()) {
            throw new \InvalidArgumentException('Les réservations sont actuellement désactivées.');
        }

        $serviceType = $data['serviceType'] ?? 'tour';
        if (!in_array($serviceType, ['tour', 'shuttle'], true)) {
            throw new \InvalidArgumentException('serviceType invalide.');
        }

        $participants = (int) ($data['participants'] ?? 0);
        $maxParticipants = $this->siteSettings->getMaxBookingParticipants();
        if ($participants < 1) {
            throw new \InvalidArgumentException('Le nombre de participants doit être supérieur à 0.');
        }
        if ($participants > $maxParticipants) {
            throw new \InvalidArgumentException(sprintf('Maximum %d participants autorisés.', $maxParticipants));
        }

        $userEmail = trim((string) ($data['userEmail'] ?? ''));
        $userName = trim((string) ($data['userName'] ?? ''));
        if ($userEmail === '' || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email invalide.');
        }
        if ($userName === '') {
            throw new \InvalidArgumentException('userName est requis.');
        }

        $booking = new Booking();
        $booking->setId((string) Uuid::v4());
        $booking->setServiceType($serviceType);
        $booking->setUserEmail($userEmail);
        $booking->setUserName($userName);
        $booking->setParticipants($participants);
        $booking->setTotalPrice((string) ($data['totalPrice'] ?? '0'));
        $booking->setStatus('pending');
        $booking->setPhone(isset($data['phone']) ? (string) $data['phone'] : null);
        $booking->setSpecialRequests(isset($data['specialRequests']) ? (string) $data['specialRequests'] : null);

        if (isset($data['paymentId'])) {
            $booking->setPaymentId((string) $data['paymentId']);
        }

        $bookingDate = $data['bookingDate'] ?? null;
        if (!$bookingDate) {
            throw new \InvalidArgumentException('bookingDate est requis.');
        }
        $booking->setBookingDate(new \DateTimeImmutable((string) $bookingDate));

        if ($serviceType === 'tour') {
            $tour = $this->resolveTour($data['tour'] ?? null);
            if ($tour === null || !$tour->isActive()) {
                throw new \InvalidArgumentException('Tour invalide ou inactif.');
            }
            $booking->setTour($tour);
        } else {
            $schedule = $this->resolveShuttleSchedule($data['shuttleSchedule'] ?? null);
            if ($schedule === null || !$schedule->isActive()) {
                throw new \InvalidArgumentException('Navette invalide ou inactive.');
            }
            if ($schedule->getAvailableSeats() < $participants) {
                throw new \InvalidArgumentException('Places insuffisantes pour cette navette.');
            }
            $booking->setShuttleSchedule($schedule);
        }

        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        return $booking;
    }

    public function confirmBooking(Booking $booking, string $paymentId): Booking
    {
        $booking->setPaymentId($paymentId);
        $booking->setStatus('confirmed');
        $this->entityManager->flush();

        return $booking;
    }

    private function resolveTour(mixed $tourRef): ?Tour
    {
        if ($tourRef === null) {
            return null;
        }

        $id = $this->extractId((string) $tourRef);

        return $id ? $this->tourRepository->find($id) : null;
    }

    private function resolveShuttleSchedule(mixed $scheduleRef): ?ShuttleSchedule
    {
        if ($scheduleRef === null) {
            return null;
        }

        $id = $this->extractId((string) $scheduleRef);

        return $id ? $this->shuttleScheduleRepository->find($id) : null;
    }

    private function extractId(string $ref): ?string
    {
        if (str_contains($ref, '/')) {
            $parts = explode('/', trim($ref, '/'));

            return end($parts) ?: null;
        }

        return $ref;
    }
}
