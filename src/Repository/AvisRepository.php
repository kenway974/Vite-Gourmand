<?php

namespace App\Repository;

use App\Entity\Avis;
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
