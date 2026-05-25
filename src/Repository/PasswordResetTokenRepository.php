<?php

namespace App\Repository;

use App\Entity\PasswordResetToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordResetToken>
 */
class PasswordResetTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetToken::class);
    }

    public function findValidByHash(string $tokenHash): ?PasswordResetToken
    {
        $token = $this->findOneBy(['tokenHash' => $tokenHash]);

        if ($token && $token->isValid()) {
            return $token;
        }

        return null;
    }
}
