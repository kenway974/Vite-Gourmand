<?php

namespace App\Repository;

use App\Entity\ZoneLivraison;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ZoneLivraison>
 */
class ZoneLivraisonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ZoneLivraison::class);
    }

    public function findParCodePostal(?string $codePostal): ?ZoneLivraison
    {
        if (null === $codePostal || '' === $codePostal) {
            return null;
        }

        return $this->findOneBy(['codePostal' => $codePostal]);
    }

    /**
     * Zones desservies, les communes sans supplément d'abord.
     *
     * @return ZoneLivraison[]
     */
    public function findToutes(): array
    {
        return $this->createQueryBuilder('z')
            ->orderBy('z.frais', 'ASC')
            ->addOrderBy('z.commune', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
