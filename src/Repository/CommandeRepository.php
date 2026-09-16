<?php

namespace App\Repository;

use App\Entity\Commande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use App\Entity\Utilisateur;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commande>
 */
class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

//    /**
//     * @return Commande[] Returns an array of Commande objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Commande
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    /**
     * Commandes d'un client, menu et thème compris.
     *
     * @return Commande[]
     */
    public function findPourClient(Utilisateur $client): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('m', 't')
            ->join('c.menu', 'm')
            ->join('m.theme', 't')
            ->andWhere('c.utilisateur = :client')
            ->setParameter('client', $client)
            ->orderBy('c.dateCommande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Une commande d'un client, avec son historique de suivi et son avis.
     *
     * Le propriétaire fait partie du WHERE, ce n'est pas un contrôle ajouté
     * après coup : une commande qui n'appartient pas au client demandé
     * n'existe pas de son point de vue, et aucun oubli d'appelant ne peut
     * exposer la commande d'autrui.
     */
    public function findUneDuClient(int $id, Utilisateur $client): ?Commande
    {
        return $this->createQueryBuilder('c')
            ->addSelect('m', 't', 's', 'a')
            ->join('c.menu', 'm')
            ->join('m.theme', 't')
            ->leftJoin('c.suiviCommandes', 's')
            ->leftJoin('c.avis', 'a')
            ->andWhere('c.id = :id')
            ->andWhere('c.utilisateur = :client')
            ->setParameter('id', $id)
            ->setParameter('client', $client)
            ->orderBy('s.dateModification', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
