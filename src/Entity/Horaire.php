<?php

namespace App\Entity;

use App\Repository\HoraireRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: HoraireRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_horaire_jour', columns: ['jour'])]
#[UniqueEntity(fields: ['jour'], message: 'Les horaires de {{ value }} sont déjà renseignés.')]
class Horaire
{
    /**
     * L'ordre de ce tableau fait foi : le champ `jour` est une chaîne, un tri
     * alphabétique placerait dimanche en tête de semaine. La position y est
     * recopiée dans la colonne `ordre` à chaque affectation du jour.
     */
    public const JOURS = [
        'Lundi',
        'Mardi',
        'Mercredi',
        'Jeudi',
        'Vendredi',
        'Samedi',
        'Dimanche',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le jour est obligatoire.')]
    #[Assert\Choice(choices: self::JOURS, message: 'Choisissez un jour de la semaine.')]
    private ?string $jour = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $ordre = 0;

    /**
     * Un jour de fermeture n'a pas d'horaire : mettre 00:00 – 00:00 forcerait
     * l'affichage à deviner que « minuit à minuit » signifie fermé.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $ferme = false;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $heureOuverture = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $heureFermeture = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJour(): ?string
    {
        return $this->jour;
    }

    public function setJour(string $jour): static
    {
        $this->jour = $jour;

        $position = array_search($jour, self::JOURS, true);
        $this->ordre = false === $position ? \count(self::JOURS) : $position;

        return $this;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function isFerme(): bool
    {
        return $this->ferme;
    }

    public function setFerme(bool $ferme): static
    {
        $this->ferme = $ferme;

        if ($ferme) {
            $this->heureOuverture = null;
            $this->heureFermeture = null;
        }

        return $this;
    }

    public function getHeureOuverture(): ?\DateTime
    {
        return $this->heureOuverture;
    }

    public function setHeureOuverture(?\DateTime $heureOuverture): static
    {
        $this->heureOuverture = $heureOuverture;

        return $this;
    }

    public function getHeureFermeture(): ?\DateTime
    {
        return $this->heureFermeture;
    }

    public function setHeureFermeture(?\DateTime $heureFermeture): static
    {
        $this->heureFermeture = $heureFermeture;

        return $this;
    }

    #[Assert\Callback]
    public function validerPlage(ExecutionContextInterface $context): void
    {
        if ($this->ferme) {
            return;
        }

        foreach (['heureOuverture' => $this->heureOuverture, 'heureFermeture' => $this->heureFermeture] as $champ => $heure) {
            if (null === $heure) {
                $context->buildViolation('Indiquez une heure, ou cochez « fermé » pour ce jour.')
                    ->atPath($champ)
                    ->addViolation();
            }
        }

        if (null === $this->heureOuverture || null === $this->heureFermeture) {
            return;
        }

        if ($this->heureFermeture <= $this->heureOuverture) {
            $context->buildViolation('La fermeture doit être postérieure à l\'ouverture.')
                ->atPath('heureFermeture')
                ->addViolation();
        }
    }

    /**
     * Libellé prêt à afficher : « 09:00 – 18:00 » ou « Fermé ».
     */
    public function plage(): string
    {
        if ($this->ferme || null === $this->heureOuverture || null === $this->heureFermeture) {
            return 'Fermé';
        }

        return sprintf(
            '%s – %s',
            $this->heureOuverture->format('H:i'),
            $this->heureFermeture->format('H:i'),
        );
    }

    /**
     * L'établissement accueille-t-il du public à cette heure-là ?
     */
    public function couvre(\DateTimeInterface $heure): bool
    {
        if ($this->ferme || null === $this->heureOuverture || null === $this->heureFermeture) {
            return false;
        }

        $minutes = (int) $heure->format('H') * 60 + (int) $heure->format('i');
        $debut = (int) $this->heureOuverture->format('H') * 60 + (int) $this->heureOuverture->format('i');
        $fin = (int) $this->heureFermeture->format('H') * 60 + (int) $this->heureFermeture->format('i');

        return $minutes >= $debut && $minutes < $fin;
    }
}
