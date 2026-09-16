<?php

namespace App\Repository;

use App\Entity\Horaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Horaire>
 */
class HoraireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Horaire::class);
    }

    /**
     * La semaine dans l'ordre, du lundi au dimanche.
     *
     * Le tri porte sur `ordre` et non sur `jour` : ce dernier est une chaîne,
     * un ORDER BY alphabétique donnerait dimanche, jeudi, lundi…
     *
     * @return Horaire[]
     */
    public function semaine(): array
    {
        return $this->createQueryBuilder('h')
            ->orderBy('h.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * L'horaire applicable à une date donnée, ou null si ce jour n'est pas
     * renseigné.
     */
    public function pourLeJour(\DateTimeInterface $date): ?Horaire
    {
        // date('N') numérote du lundi (1) au dimanche (7), JOURS s'indexe à 0.
        $jour = Horaire::JOURS[(int) $date->format('N') - 1];

        return $this->findOneBy(['jour' => $jour]);
    }

    /**
     * L'établissement est-il ouvert à cet instant précis ?
     */
    public function estOuvert(\DateTimeInterface $instant): bool
    {
        return $this->pourLeJour($instant)?->couvre($instant) ?? false;
    }

    /**
     * Les jours de la semaine pas encore renseignés — un jour ne peut figurer
     * qu'une fois, inutile de le proposer à la création.
     *
     * @return string[]
     */
    public function joursNonRenseignes(): array
    {
        $pris = array_column(
            $this->createQueryBuilder('h')->select('h.jour')->getQuery()->getScalarResult(),
            'jour',
        );

        return array_values(array_diff(Horaire::JOURS, $pris));
    }
}
