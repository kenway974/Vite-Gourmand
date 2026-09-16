<?php

namespace App\Repository;

use App\Entity\Menu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;
use App\Entity\Theme;
use App\Entity\Regime;

/**
 * @extends ServiceEntityRepository<Menu>
 */
class MenuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Menu::class);
    }

//    /**
//     * @return Menu[] Returns an array of Menu objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('m')
//            ->andWhere('m.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('m.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Menu
//    {
//        return $this->createQueryBuilder('m')
//            ->andWhere('m.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    /**
     * Liste des menus pour l'administration, thème et régime compris.
     *
     * addSelect() sur les entités jointes est ce qui transforme la jointure en
     * « fetch join » : Doctrine hydrate thème et régime dans la foulée. Sans
     * lui, le gabarit qui affiche theme.libelle irait les chercher un par un.
     *
     * @return Menu[]
     */
    public function findPourAdministration(): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('t', 'r')
            ->join('m.theme', 't')
            ->join('m.regime', 'r')
            ->orderBy('m.titre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Catalogue public : menus proposés, filtrés et paginés.
     *
     * Le filtrage et la pagination sont faits par la base. Les faire en PHP
     * supposerait de charger tout le catalogue en mémoire à chaque visite,
     * et paginerait après coup un jeu déjà tronqué.
     *
     * Seules des relations ToOne sont jointes (thème, régime) : une jointure
     * sur une collection multiplierait les lignes et fausserait LIMIT/OFFSET.
     *
     * @param array{theme?: ?Theme, regime?: ?Regime, prixMax?: ?string, nbPersonnes?: ?int, seulementCommandables?: bool} $filtres
     *
     * @return Paginator<Menu>
     */
    public function findCatalogue(array $filtres = [], int $page = 1, int $parPage = 9): Paginator
    {
        $qb = $this->createQueryBuilder('m')
            ->addSelect('t', 'r')
            ->join('m.theme', 't')
            ->join('m.regime', 'r')
            // On masque uniquement les menus dont la période est révolue :
            // ils ne sont plus proposés. Un menu épuisé, ou dont la saison
            // n'a pas commencé, reste affiché — c'est précisément ce qui fait
            // savoir au visiteur qu'on le propose. Sa commandabilité est
            // signalée à l'affichage par Menu::disponibilite().
            ->andWhere('m.dateFin IS NULL OR m.dateFin >= :aujourdhui')
            ->setParameter('aujourdhui', new \DateTime('today'))
            ->orderBy('m.titre', 'ASC');

        if (!empty($filtres['seulementCommandables'])) {
            $qb->andWhere('m.stock > 0')
               ->andWhere('m.dateDebut IS NULL OR m.dateDebut <= :aujourdhui');
        }

        if (!empty($filtres['theme'])) {
            $qb->andWhere('m.theme = :theme')->setParameter('theme', $filtres['theme']);
        }

        if (!empty($filtres['regime'])) {
            $qb->andWhere('m.regime = :regime')->setParameter('regime', $filtres['regime']);
        }

        if (!empty($filtres['prixMax'])) {
            $qb->andWhere('m.prixMin <= :prixMax')->setParameter('prixMax', $filtres['prixMax']);
        }

        if (!empty($filtres['nbPersonnes'])) {
            // On ne propose que les menus réalisables pour cet effectif.
            $qb->andWhere('m.nbMinPersonnes <= :nbPersonnes')
               ->setParameter('nbPersonnes', $filtres['nbPersonnes']);
        }

        $page = max(1, $page);

        $qb->setFirstResult(($page - 1) * $parPage)->setMaxResults($parPage);

        return new Paginator($qb->getQuery(), fetchJoinCollection: false);
    }

    /**
     * Fiche détaillée d'un menu : thème, régime, plats et ingrédients des plats.
     *
     * Tout est ramené en une requête. En chargement paresseux, cette page
     * coûterait une requête par plat, puis une par plat pour ses ingrédients.
     *
     * Les jointures suivent un chemin (menu → plats → ingrédients) et non
     * deux branches parallèles : le nombre de lignes reste proportionnel au
     * contenu réel du menu, sans produit cartésien.
     */
    public function findDetail(int $id): ?Menu
    {
        return $this->createQueryBuilder('m')
            ->addSelect('t', 'r', 'p', 'i')
            ->join('m.theme', 't')
            ->join('m.regime', 'r')
            ->leftJoin('m.plats', 'p')
            ->leftJoin('p.ingredients', 'i')
            ->andWhere('m.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
