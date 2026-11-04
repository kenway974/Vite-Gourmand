<?php

namespace App\Repository;

use App\Entity\Plat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Plat>
 */
class PlatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Plat::class);
    }

    /**
     * Liste des plats avec, pour chacun, son nombre d'ingrédients et de menus.
     *
     * Les compteurs sont calculés par la base en une seule requête. Les lire
     * depuis les collections dans le gabarit déclencherait une requête par
     * ligne affichée (N+1), et le coût grandirait avec le catalogue.
     *
     * COUNT(DISTINCT) est indispensable : sans lui, plusieurs jointures sur
     * des collections différentes multiplient les lignes entre elles et
     * faussent les totaux.
     *
     * @return array<int, array{entite: Plat, nbIngredients: int, nbMenus: int}>
     */
    public function findPourAdministration(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p AS entite', 'COUNT(DISTINCT i.id) AS nbIngredients', 'COUNT(DISTINCT m.id) AS nbMenus')
            ->leftJoin('p.ingredients', 'i')
            ->leftJoin('p.menus', 'm')
            ->groupBy('p.id')
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
