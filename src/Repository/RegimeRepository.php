<?php

namespace App\Repository;

use App\Entity\Regime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Regime>
 */
class RegimeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Regime::class);
    }

//    /**
//     * @return Regime[] Returns an array of Regime objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('r.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Regime
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    /**
     * Liste des régimes avec leur nombre de menus.
     *
     * Les compteurs sont calculés par la base en une seule requête. Les lire
     * depuis les collections dans le gabarit déclencherait une requête par
     * ligne affichée (N+1), et le coût grandirait avec le catalogue.
     *
     * COUNT(DISTINCT) est indispensable : sans lui, plusieurs jointures sur
     * des collections différentes multiplient les lignes entre elles et
     * faussent les totaux.
     *
     * @return array<int, array{entite: Regime, nbMenus: int}>
     */
    public function findPourAdministration(): array
    {
        return $this->createQueryBuilder('r')
            ->select('r AS entite', 'COUNT(DISTINCT m.id) AS nbMenus')
            ->leftJoin('r.menus', 'm')
            ->groupBy('r.id')
            ->orderBy('r.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
