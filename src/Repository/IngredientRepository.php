<?php

namespace App\Repository;

use App\Entity\Ingredient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ingredient>
 */
class IngredientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ingredient::class);
    }

//    /**
//     * @return Ingredient[] Returns an array of Ingredient objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('i')
//            ->andWhere('i.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('i.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Ingredient
//    {
//        return $this->createQueryBuilder('i')
//            ->andWhere('i.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    /**
     * Liste des ingrédients avec leur nombre d'allergènes et de plats.
     *
     * Les compteurs sont calculés par la base en une seule requête. Les lire
     * depuis les collections dans le gabarit déclencherait une requête par
     * ligne affichée (N+1), et le coût grandirait avec le catalogue.
     *
     * COUNT(DISTINCT) est indispensable : sans lui, plusieurs jointures sur
     * des collections différentes multiplient les lignes entre elles et
     * faussent les totaux.
     *
     * @return array<int, array{entite: Ingredient, nbAllergenes: int, nbPlats: int}>
     */
    public function findPourAdministration(): array
    {
        return $this->createQueryBuilder('i')
            ->select('i AS entite', 'COUNT(DISTINCT a.id) AS nbAllergenes', 'COUNT(DISTINCT p.id) AS nbPlats')
            ->leftJoin('i.allergenes', 'a')
            ->leftJoin('i.plats', 'p')
            ->groupBy('i.id')
            ->orderBy('i.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
