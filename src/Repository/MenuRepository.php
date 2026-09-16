<?php

namespace App\Repository;

use App\Entity\Avis;
use App\Entity\Menu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use App\Entity\Theme;
use App\Entity\Regime;

/**
 * @extends ServiceEntityRepository<Menu>
 */
class MenuRepository extends ServiceEntityRepository
{
    /**
     * Ordres de tri acceptés par le catalogue, libellés compris. La clé est
     * ce qui circule dans l'URL ; toute autre valeur retombe sur le titre.
     */
    /**
     * Paliers du filtre « nombre de convives », tels que les maquettes les
     * proposent : « 4 et plus / 6 et plus / 20 et plus ». Un champ libre
     * laisserait saisir 7 convives et ne renverrait rien de différent de 6.
     *
     * @var list<int>
     */
    public const PALIERS_CONVIVES = [4, 6, 20];

    public const TRIS = [
        'titre' => 'Ordre alphabétique',
        'prix-croissant' => 'Prix croissant',
        'prix-decroissant' => 'Prix décroissant',
        'note' => 'Les mieux notés',
    ];

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
     * @param array{theme?: ?Theme, regime?: ?Regime, prixMax?: ?string, nbPersonnes?: ?int, recherche?: ?string, tri?: ?string, seulementCommandables?: bool} $filtres
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
            ->setParameter('aujourdhui', new \DateTime('today'));

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

        if (!empty($filtres['recherche'])) {
            // LIKE sur deux colonnes : suffisant pour un catalogue de cette
            // taille, un index plein texte ne se justifierait qu'à partir de
            // plusieurs milliers de menus.
            //
            // Pas de LOWER() : l'insensibilité à la casse vient de la
            // collation de la table (utf8mb4_unicode_ci en MySQL), qui traite
            // aussi les accents. LOWER() n'y ajouterait rien, et ne sait de
            // toute façon pas abaisser « Ô » sans ICU.
            //
            // Les jokers SQL saisis par le visiteur sont neutralisés, sans quoi
            // « % » à lui seul ramènerait tout le catalogue.
            $motif = '%'.addcslashes(trim((string) $filtres['recherche']), '%_\\').'%';

            $qb->andWhere('m.titre LIKE :recherche OR m.description LIKE :recherche')
               ->setParameter('recherche', $motif);
        }

        $this->trier($qb, $filtres['tri'] ?? null);

        $page = max(1, $page);

        $qb->setFirstResult(($page - 1) * $parPage)->setMaxResults($parPage);

        return new Paginator($qb->getQuery(), fetchJoinCollection: false);
    }

    /**
     * Applique l'ordre demandé, en retombant sur le titre si la valeur reçue
     * n'est pas reconnue : le tri vient de l'URL, il n'est pas digne de
     * confiance et ne doit jamais atterrir tel quel dans du DQL.
     */
    private function trier(QueryBuilder $qb, ?string $tri): void
    {
        match ($tri) {
            'prix-croissant' => $qb->orderBy('m.prixMin', 'ASC'),
            'prix-decroissant' => $qb->orderBy('m.prixMin', 'DESC'),
            // La note moyenne n'est pas une colonne : elle est recalculée par
            // une sous-requête corrélée, déclarée HIDDEN pour pouvoir servir
            // de critère de tri sans polluer le résultat hydraté.
            'note' => $qb
                ->addSelect(
                    '(SELECT AVG(av.note) FROM App\\Entity\\Avis av'
                    .' JOIN av.commande cav'
                    .' WHERE cav.menu = m AND av.statutValidation = :avisValide) AS HIDDEN noteMoyenne'
                )
                ->setParameter('avisValide', Avis::VALIDE)
                // Un menu sans avis a une moyenne nulle : il passe après les
                // menus notés plutôt que devant, d'où le tri secondaire.
                ->orderBy('noteMoyenne', 'DESC')
                ->addOrderBy('m.titre', 'ASC'),
            default => $qb->orderBy('m.titre', 'ASC'),
        };
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
