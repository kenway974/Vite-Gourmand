<?php

namespace App\Repository;

use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Utilisateur>
 */
class UtilisateurRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Utilisateur) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

//    /**
//     * @return Utilisateur[] Returns an array of Utilisateur objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('u.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Utilisateur
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    /**
     * Liste des comptes avec leur nombre de commandes.
     *
     * Le compteur est calculé par la base : le lire depuis la collection dans
     * le gabarit déclencherait une requête par ligne affichée.
     *
     * @return array<int, array{entite: Utilisateur, nbCommandes: int}>
     */
    public function findPourAdministration(?string $recherche = null): array
    {
        $qb = $this->createQueryBuilder('u')
            ->select('u AS entite', 'COUNT(c.id) AS nbCommandes')
            ->leftJoin('u.commandes', 'c')
            ->groupBy('u.id')
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenom', 'ASC');

        if (null !== $recherche && '' !== trim($recherche)) {
            $qb->andWhere('u.nom LIKE :recherche OR u.prenom LIKE :recherche OR u.email LIKE :recherche')
               ->setParameter('recherche', '%'.trim($recherche).'%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Nombre d'administrateurs actifs.
     *
     * Sert à refuser de retirer le dernier : sans lui, plus personne ne peut
     * administrer le site.
     */
    public function compterAdministrateursActifs(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.roles LIKE :role')
            ->andWhere('u.actif = :actif')
            ->setParameter('role', '%ROLE_ADMIN%')
            ->setParameter('actif', true)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
