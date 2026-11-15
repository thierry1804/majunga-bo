<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\ShuttleSchedule;
use Doctrine\DBAL\Types\Types;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    /**
     * Nombre de places déjà prises sur une navette pour un jour donné.
     * Les réservations annulées libèrent leurs places ; celles en attente les retiennent.
     */
    public function countReservedSeats(ShuttleSchedule $schedule, \DateTimeInterface $date): int
    {
        $total = $this->createQueryBuilder('b')
            ->select('COALESCE(SUM(b.participants), 0)')
            ->where('b.shuttleSchedule = :schedule')
            ->andWhere('b.bookingDate = :date')
            ->andWhere("b.serviceType = 'shuttle'")
            ->andWhere("COALESCE(b.status, 'pending') <> 'cancelled'")
            ->setParameter('schedule', $schedule)
            ->setParameter('date', $date, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $total;
    }

    /**
     * Find all bookings by user email
     *
     * @param string $email
     * @return Booking[]
     */
    public function findByUserEmail(string $email): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.userEmail = :email')
            ->setParameter('email', $email)
            ->orderBy('b.bookingDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find bookings by status
     *
     * @param string $status
     * @return Booking[]
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.status = :status')
            ->setParameter('status', $status)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find bookings by tour
     *
     * @param string $tourId
     * @return Booking[]
     */
    public function findByTour(string $tourId): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.tour = :tourId')
            ->setParameter('tourId', $tourId)
            ->orderBy('b.bookingDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

