<?php

namespace App\Repository;

use App\Entity\Avis;
use App\Entity\Menu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Avis>
 */
class AvisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Avis::class);
    }

//    /**
//     * @return Avis[] Returns an array of Avis objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('a.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Avis
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    /**
     * File de modération : les avis déposés et pas encore tranchés.
     *
     * Client, commande et menu sont ramenés avec, faute de quoi la page en
     * déclencherait trois requêtes par ligne.
     *
     * @return Avis[]
     */
    public function findEnAttenteDeModeration(): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('u', 'c', 'm')
            ->join('a.utilisateur', 'u')
            ->join('a.commande', 'c')
            ->join('c.menu', 'm')
            ->andWhere('a.statutValidation = :attente')
            ->setParameter('attente', Avis::EN_ATTENTE)
            ->orderBy('a.dateCreation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Note moyenne et nombre d'avis publiés, menu par menu.
     *
     * Une seule requête pour toute la page : appeler une moyenne par menu
     * ferait revenir le N+1 que le catalogue évite par ailleurs. Les menus
     * sans avis publié sont simplement absents du tableau.
     *
     * @param int[] $menuIds
     *
     * @return array<int, array{moyenne: float, nombre: int}>
     */
    public function notesParMenu(array $menuIds): array
    {
        if ([] === $menuIds) {
            return [];
        }

        $lignes = $this->createQueryBuilder('a')
            ->select('IDENTITY(c.menu) AS menuId', 'AVG(a.note) AS moyenne', 'COUNT(a.id) AS nombre')
            ->join('a.commande', 'c')
            ->andWhere('c.menu IN (:menus)')
            ->andWhere('a.statutValidation = :valide')
            ->setParameter('menus', $menuIds)
            ->setParameter('valide', Avis::VALIDE)
            ->groupBy('c.menu')
            ->getQuery()
            ->getResult();

        $notes = [];

        foreach ($lignes as $ligne) {
            $notes[(int) $ligne['menuId']] = [
                'moyenne' => round((float) $ligne['moyenne'], 1),
                'nombre' => (int) $ligne['nombre'],
            ];
        }

        return $notes;
    }

    /**
     * Les avis publiés d'un menu, du plus récent au plus ancien.
     *
     * L'auteur est ramené avec : le gabarit affiche son prénom, sans quoi
     * chaque avis déclencherait sa propre requête.
     *
     * @return Avis[]
     */
    public function findPubliesPourMenu(Menu $menu): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('u')
            ->join('a.utilisateur', 'u')
            ->join('a.commande', 'c')
            ->andWhere('c.menu = :menu')
            ->andWhere('a.statutValidation = :valide')
            ->setParameter('menu', $menu)
            ->setParameter('valide', Avis::VALIDE)
            ->orderBy('a.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Note moyenne et nombre d'avis publiés, tous menus confondus.
     *
     * @return array{moyenne: ?float, nombre: int}
     */
    public function statistiquesPubliees(): array
    {
        $ligne = $this->createQueryBuilder('a')
            ->select('AVG(a.note) AS moyenne', 'COUNT(a.id) AS nombre')
            ->andWhere('a.statutValidation = :valide')
            ->setParameter('valide', Avis::VALIDE)
            ->getQuery()
            ->getSingleResult();

        return [
            'moyenne' => null !== $ligne['moyenne'] ? round((float) $ligne['moyenne'], 1) : null,
            'nombre' => (int) $ligne['nombre'],
        ];
    }
}
