<?php

namespace App\Repository;

use App\Entity\Allergene;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Menu;

/**
 * @extends ServiceEntityRepository<Allergene>
 */
class AllergeneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Allergene::class);
    }

    /**
     * Liste des allergènes avec leur nombre d'ingrédients.
     *
     * Les compteurs sont calculés par la base en une seule requête. Les lire
     * depuis les collections dans le gabarit déclencherait une requête par
     * ligne affichée (N+1), et le coût grandirait avec le catalogue.
     *
     * COUNT(DISTINCT) est indispensable : sans lui, plusieurs jointures sur
     * des collections différentes multiplient les lignes entre elles et
     * faussent les totaux.
     *
     * @return array<int, array{entite: Allergene, nbIngredients: int}>
     */
    public function findPourAdministration(): array
    {
        return $this->createQueryBuilder('a')
            ->select('a AS entite', 'COUNT(DISTINCT i.id) AS nbIngredients')
            ->leftJoin('a.ingredients', 'i')
            ->groupBy('a.id')
            ->orderBy('a.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Tous les allergènes présents dans un menu.
     *
     * L'information est à trois niveaux de distance du menu :
     * Menu → Plats → Ingrédients → Allergènes. La remonter en PHP imposerait
     * de parcourir ces trois niveaux et de dédoublonner à la main, puisqu'un
     * même allergène apparaît dans plusieurs ingrédients.
     *
     * Informer le client des allergènes est une obligation réglementaire :
     * cette requête est ce qui permet de l'afficher, et de l'afficher juste.
     *
     * @return Allergene[]
     */
    public function findPourMenu(Menu $menu): array
    {
        return $this->createQueryBuilder('a')
            ->distinct()
            ->join('a.ingredients', 'i')
            ->join('i.plats', 'p')
            ->join('p.menus', 'm')
            ->andWhere('m = :menu')
            ->setParameter('menu', $menu)
            ->orderBy('a.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
