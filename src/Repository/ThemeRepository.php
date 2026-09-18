<?php

namespace App\Repository;

use App\Entity\Theme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Theme>
 */
class ThemeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Theme::class);
    }

//    /**
//     * @return Theme[] Returns an array of Theme objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('t.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Theme
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    /**
     * Liste des thèmes avec leur nombre de menus.
     *
     * Les compteurs sont calculés par la base en une seule requête. Les lire
     * depuis les collections dans le gabarit déclencherait une requête par
     * ligne affichée (N+1), et le coût grandirait avec le catalogue.
     *
     * COUNT(DISTINCT) est indispensable : sans lui, plusieurs jointures sur
     * des collections différentes multiplient les lignes entre elles et
     * faussent les totaux.
     *
     * @return array<int, array{entite: Theme, nbMenus: int}>
     */
    public function findPourAdministration(): array
    {
        return $this->createQueryBuilder('t')
            ->select('t AS entite', 'COUNT(DISTINCT m.id) AS nbMenus')
            ->leftJoin('t.menus', 'm')
            ->groupBy('t.id')
            ->orderBy('t.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Thèmes mis en avant sur la page d'accueil, avec le nombre de menus
     * encore proposés dans chacun.
     *
     * Un thème sans menu proposé est écarté : il mènerait à un catalogue
     * vide, ce qui donne l'impression d'un site en panne plutôt que d'un
     * choix éditorial.
     *
     * Le filtre de période reprend celui du catalogue — un menu dont la
     * saison est passée n'est plus proposé — faute de quoi l'accueil
     * annoncerait un nombre que le catalogue ne confirmerait pas.
     *
     * @return array<int, array{entite: Theme, nbMenus: int}>
     */
    public function findPourAccueil(int $limite = 4): array
    {
        return $this->createQueryBuilder('t')
            ->select('t AS entite', 'COUNT(DISTINCT m.id) AS nbMenus')
            ->join('t.menus', 'm')
            ->andWhere('m.dateFin IS NULL OR m.dateFin >= :aujourdhui')
            ->setParameter('aujourdhui', new \DateTime('today'))
            ->groupBy('t.id')
            // Le thème le mieux fourni d'abord : c'est celui qui a le plus de
            // chances de retenir un visiteur qui ne sait pas encore ce qu'il
            // cherche.
            ->orderBy('nbMenus', 'DESC')
            ->addOrderBy('t.libelle', 'ASC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }
}
