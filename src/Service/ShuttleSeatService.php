<?php

namespace App\Service;

use App\Entity\ShuttleSchedule;
use App\Repository\BookingRepository;

/**
 * Places disponibles sur les navettes.
 *
 * `ShuttleSchedule::availableSeats` est la capacité d'un départ. Les places restantes se calculent
 * par jour : capacité moins les participants des réservations non annulées de ce jour.
 */
final class ShuttleSeatService
{
    public function __construct(
        private BookingRepository $bookingRepository,
        private SiteSettingsHelper $siteSettings
    ) {
    }

    public function reservedSeats(ShuttleSchedule $schedule, \DateTimeInterface $date): int
    {
        return $this->bookingRepository->countReservedSeats($schedule, $date);
    }

    public function remainingSeats(ShuttleSchedule $schedule, \DateTimeInterface $date): int
    {
        return max(0, $schedule->getAvailableSeats() - $this->reservedSeats($schedule, $date));
    }

    /** Aujourd'hui, selon le fuseau horaire du site. */
    public function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today', $this->siteSettings->getTimezone());
    }

    /** Vrai si la date est aujourd'hui et que l'heure de départ est déjà passée. */
    public function hasDeparted(ShuttleSchedule $schedule, \DateTimeInterface $date): bool
    {
        $now = new \DateTimeImmutable('now', $this->siteSettings->getTimezone());
        if ($date->format('Y-m-d') !== $now->format('Y-m-d')) {
            return false;
        }

        return $schedule->getDepartureTime() <= $now->format('H:i');
    }
}
