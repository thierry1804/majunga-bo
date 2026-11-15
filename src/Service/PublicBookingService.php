<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\ShuttleSchedule;
use App\Entity\Tour;
use App\Repository\ShuttleScheduleRepository;
use App\Repository\TourRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class PublicBookingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TourRepository $tourRepository,
        private ShuttleScheduleRepository $shuttleScheduleRepository,
        private SiteSettingsHelper $siteSettings,
        private ShuttleSeatService $seats
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
        if (!in_array($serviceType, ['tour', 'shuttle', 'custom_shuttle'], true)) {
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
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', substr((string) $bookingDate, 0, 10));
        if ($date === false) {
            throw new \InvalidArgumentException('bookingDate invalide (AAAA-MM-JJ).');
        }
        if ($date < $this->seats->today()) {
            throw new \InvalidArgumentException('La date de réservation est déjà passée.');
        }
        $booking->setBookingDate($date);
        $schedule = null;

        if ($serviceType === 'tour') {
            $tour = $this->resolveTour($data['tour'] ?? null);
            if ($tour === null || !$tour->isActive()) {
                throw new \InvalidArgumentException('Tour invalide ou inactif.');
            }
            $booking->setTour($tour);
        } elseif ($serviceType === 'custom_shuttle') {
            $route = $this->validateCustomRoute($data['customRoute'] ?? null);
            $this->assertCustomShuttlePrice((float) ($data['totalPrice'] ?? 0), $route['distanceKm']);
            $booking->setSpecialRequests($this->formatCustomRoute($route, $data['specialRequests'] ?? null));
        } else {
            $schedule = $this->resolveShuttleSchedule($data['shuttleSchedule'] ?? null);
            if ($schedule === null || !$schedule->isActive()) {
                throw new \InvalidArgumentException('Navette invalide ou inactive.');
            }
            $this->assertSeatsAvailable($schedule, $date, $participants);
            $booking->setShuttleSchedule($schedule);
        }

        $this->entityManager->wrapInTransaction(function () use ($booking, $schedule, $date, $participants): void {
            if ($schedule instanceof ShuttleSchedule) {
                // Verrou sur la navette : deux réservations simultanées ne peuvent pas prendre les dernières places.
                $this->entityManager->lock($schedule, LockMode::PESSIMISTIC_WRITE);
                $this->assertSeatsAvailable($schedule, $date, $participants);
            }

            $this->entityManager->persist($booking);
            $this->entityManager->flush();
        });

        return $booking;
    }

    private function assertSeatsAvailable(ShuttleSchedule $schedule, \DateTimeImmutable $date, int $participants): void
    {
        if ($this->seats->hasDeparted($schedule, $date)) {
            throw new \InvalidArgumentException('Cette navette est déjà partie aujourd\'hui. Choisissez un autre départ.');
        }

        $remaining = $this->seats->remainingSeats($schedule, $date);
        if ($remaining <= 0) {
            throw new \InvalidArgumentException('Cette navette est complète à cette date. Choisissez un autre départ ou un autre jour.');
        }
        if ($participants > $remaining) {
            throw new \InvalidArgumentException(sprintf(
                'Il ne reste que %d place%s sur cette navette à cette date.',
                $remaining,
                $remaining > 1 ? 's' : ''
            ));
        }
    }

    public function confirmBooking(Booking $booking, string $paymentId): Booking
    {
        $booking->setPaymentId($paymentId);
        $booking->setStatus('confirmed');
        $this->entityManager->flush();

        return $booking;
    }

    /**
     * @return array{from: array{lat: float, lng: float, address: string}, to: array{lat: float, lng: float, address: string}, departureTime: string, distanceKm: float}
     */
    private function validateCustomRoute(mixed $route): array
    {
        if (!is_array($route)) {
            throw new \InvalidArgumentException('customRoute est requis pour une navette sur mesure.');
        }

        $points = [];
        foreach (['from', 'to'] as $key) {
            $p = $route[$key] ?? null;
            if (!is_array($p) || !isset($p['lat'], $p['lng']) || !is_numeric($p['lat']) || !is_numeric($p['lng'])) {
                throw new \InvalidArgumentException(sprintf('customRoute.%s invalide.', $key));
            }
            $lat = (float) $p['lat'];
            $lng = (float) $p['lng'];
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                throw new \InvalidArgumentException(sprintf('Coordonnées de customRoute.%s hors limites.', $key));
            }
            $points[$key] = [
                'lat' => $lat,
                'lng' => $lng,
                'address' => mb_substr(trim((string) ($p['address'] ?? '')), 0, 255),
            ];
        }

        $time = (string) ($route['departureTime'] ?? '');
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            throw new \InvalidArgumentException('customRoute.departureTime invalide (HH:MM).');
        }

        $distanceKm = $this->haversineKm($points['from'], $points['to']);
        if ($distanceKm < 0.05) {
            throw new \InvalidArgumentException('Le départ et l\'arrivée sont identiques.');
        }

        return [
            'from' => $points['from'],
            'to' => $points['to'],
            'departureTime' => $time,
            'distanceKm' => $distanceKm,
        ];
    }

    /**
     * Le client calcule la distance par la route (OSRM), toujours >= à vol d'oiseau.
     * On borne donc le prix : au moins distance directe x tarif, au plus 3 fois cette valeur.
     */
    private function assertCustomShuttlePrice(float $totalPrice, float $directKm): void
    {
        $min = round($directKm * $this->siteSettings->getPricePerKm(), 2);
        $max = $min * 3;

        if ($totalPrice < $min - 0.01 || $totalPrice > $max + 0.01) {
            throw new \InvalidArgumentException('Le prix ne correspond pas au trajet demandé.');
        }
    }

    /**
     * @param array{from: array{lat: float, lng: float, address: string}, to: array{lat: float, lng: float, address: string}, departureTime: string, distanceKm: float} $route
     */
    private function formatCustomRoute(array $route, mixed $userRequests): string
    {
        $lines = [
            '[Navette sur mesure]',
            sprintf('Départ : %s (%.6f, %.6f)', $route['from']['address'], $route['from']['lat'], $route['from']['lng']),
            sprintf('Arrivée : %s (%.6f, %.6f)', $route['to']['address'], $route['to']['lat'], $route['to']['lng']),
            sprintf('Heure souhaitée : %s', $route['departureTime']),
            sprintf('Distance à vol d\'oiseau : %.2f km', $route['distanceKm']),
        ];
        $extra = trim((string) $userRequests);
        if ($extra !== '') {
            $lines[] = 'Demandes : ' . mb_substr($extra, 0, 1000);
        }

        return implode("\n", $lines);
    }

    /**
     * @param array{lat: float, lng: float} $a
     * @param array{lat: float, lng: float} $b
     */
    private function haversineKm(array $a, array $b): float
    {
        $r = 6371.0;
        $dLat = deg2rad($b['lat'] - $a['lat']);
        $dLng = deg2rad($b['lng'] - $a['lng']);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($a['lat'])) * cos(deg2rad($b['lat'])) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($h)));
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
