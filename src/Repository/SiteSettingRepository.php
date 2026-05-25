<?php

namespace App\Repository;

use App\Entity\SiteSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SiteSetting>
 */
class SiteSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteSetting::class);
    }

    /**
     * Find a setting by its key
     */
    public function findByKey(string $key): ?SiteSetting
    {
        return $this->createQueryBuilder('s')
            ->where('s.key = :key')
            ->setParameter('key', $key)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all public settings
     *
     * @return SiteSetting[]
     */
    public function findPublicSettings(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.isPublic = :isPublic')
            ->setParameter('isPublic', true)
            ->orderBy('s.category', 'ASC')
            ->addOrderBy('s.key', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find settings by category
     *
     * @return SiteSetting[]
     */
    public function findByCategory(string $category): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.category = :category')
            ->setParameter('category', $category)
            ->orderBy('s.key', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

