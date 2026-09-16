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

    /**
     * Commandes pour le suivi interne, filtrables par statut.
     *
     * @return Commande[]
     */
    public function findPourSuivi(?string $statut = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->addSelect('u', 'm')
            ->join('c.utilisateur', 'u')
            ->join('c.menu', 'm')
            ->orderBy('c.datePrestation', 'ASC');

        if (null !== $statut && '' !== $statut) {
            $qb->andWhere('c.statut = :statut')->setParameter('statut', $statut);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Commandes encore à honorer vers un code postal donné.
     *
     * Sert à prévenir avant de retirer une zone de livraison : livrée ou
     * annulée, une commande n'attend plus rien.
     */
    public function compterEnCoursPour(string $codePostal): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.codePostalLivraison = :code')
            ->andWhere('c.statut NOT IN (:termines)')
            ->setParameter('code', $codePostal)
            ->setParameter('termines', Commande::STATUTS_FINAUX)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Matériel prêté et pas encore revenu, du plus ancien au plus récent :
     * les commandes les plus en retard arrivent en tête.
     *
     * Client et menu sont ramenés avec, la liste les affiche tous les deux.
     *
     * @return Commande[]
     */
    public function findMaterielPrete(bool $restitue = false): array
    {
        $qb = $this->createQueryBuilder('c')
            ->addSelect('u', 'm')
            ->join('c.utilisateur', 'u')
            ->join('c.menu', 'm')
            ->andWhere('c.pretMateriel = true')
            // Une commande annulée n'a jamais donné lieu à un prêt effectif.
            ->andWhere('c.statut = :livree')
            ->setParameter('livree', Commande::LIVREE)
            ->orderBy('c.datePrestation', 'ASC');

        $qb->andWhere($restitue
            ? 'c.dateRestitutionMateriel IS NOT NULL'
            : 'c.dateRestitutionMateriel IS NULL');

        return $qb->getQuery()->getResult();
    }

    /**
     * Feuille de route d'une journée : ce qu'il y a à préparer et à livrer.
     *
     * Triée par heure de livraison, et débarrassée des commandes annulées :
     * c'est la liste que la cuisine lit le matin.
     *
     * @return Commande[]
     */
    public function findAPreparerPour(\DateTimeInterface $jour): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('u', 'm')
            ->join('c.utilisateur', 'u')
            ->join('c.menu', 'm')
            ->andWhere('c.datePrestation = :jour')
            ->andWhere('c.statut != :annulee')
            ->setParameter('jour', $jour->format('Y-m-d'))
            ->setParameter('annulee', Commande::ANNULEE)
            ->orderBy('c.heureLivraison', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
