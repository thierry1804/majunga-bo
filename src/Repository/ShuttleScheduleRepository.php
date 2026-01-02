<?php

namespace App\Repository;

use App\Entity\ShuttleSchedule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShuttleSchedule>
 */
class ShuttleScheduleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShuttleSchedule::class);
    }

    /**
     * Find all active shuttle schedules
     *
     * @return ShuttleSchedule[]
     */
    public function findActiveSchedules(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('s.departureTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find schedules by direction
     *
     * @param string $direction
     * @return ShuttleSchedule[]
     */
    public function findByDirection(string $direction): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.direction = :direction')
            ->andWhere('s.isActive = :active')
            ->setParameter('direction', $direction)
            ->setParameter('active', true)
            ->orderBy('s.departureTime', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

