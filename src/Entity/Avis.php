<?php

namespace App\Entity;

use App\Repository\AvisRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AvisRepository::class)]
class Avis
{
    public const EN_ATTENTE = 'en attente';
    public const VALIDE = 'validé';
    public const REFUSE = 'refusé';

    /** @var list<string> */
    public const STATUTS = [self::EN_ATTENTE, self::VALIDE, self::REFUSE];

    /**
     * Les maquettes datent chaque avis en toutes lettres — « Décembre 2024 ».
     * Douze chaînes évitent d'ajouter twig/intl-extra pour ce seul format.
     */
    private const MOIS = [
        1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

   #[ORM\OneToOne(inversedBy: 'avis', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Commande $commande = null;

    #[ORM\ManyToOne(inversedBy: 'avis')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Merci de donner une note.')]
    #[Assert\Range(min: 1, max: 5, notInRangeMessage: 'La note doit être comprise entre {{ min }} et {{ max }}.')]
    private ?int $note = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $commentaire = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: self::STATUTS, message: 'Statut de validation inconnu.')]
    private ?string $statutValidation = null;

    #[ORM\Column]
    private ?\DateTime $dateCreation = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommande(): ?Commande
    {
        return $this->commande;
    }

    /**
     * Contexte de l'avis tel que les maquettes l'affichent :
     * « Décembre 2024 · 8 convives ».
     *
     * C'est la date de la PRESTATION qui fait foi, pas celle de l'avis : le
     * lecteur veut savoir quand le repas a eu lieu, pas quand quelqu'un a
     * trouvé le temps d'écrire.
     */
    public function contexte(): ?string
    {
        $prestation = $this->commande?->getDatePrestation();

        if (null === $prestation || null === $this->commande?->getNbPersonnes()) {
            return null;
        }

        return sprintf(
            '%s %s · %d convives',
            ucfirst(self::MOIS[(int) $prestation->format('n')]),
            $prestation->format('Y'),
            $this->commande->getNbPersonnes(),
        );
    }

    public function setCommande(?Commande $commande): static
    {
        $this->commande = $commande;

        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getNote(): ?int
    {
        return $this->note;
    }

    public function setNote(int $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(string $commentaire): static
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getStatutValidation(): ?string
    {
        return $this->statutValidation;
    }

    public function setStatutValidation(string $statutValidation): static
    {
        $this->statutValidation = $statutValidation;

        return $this;
    }

    public function getDateCreation(): ?\DateTime
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTime $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    public function estPublie(): bool
    {
        return self::VALIDE === $this->statutValidation;
    }

    public function attendModeration(): bool
    {
        return self::EN_ATTENTE === $this->statutValidation;
    }
}
