<?php

namespace App\Entity;

use App\Repository\ContactRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContactRepository::class)]
class Contact
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Merci d\'indiquer un objet.')]
    #[Assert\Length(max: 150, maxMessage: 'L\'objet ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Merci d\'écrire votre message.')]
    #[Assert\Length(min: 20, minMessage: 'Votre message doit faire au moins {{ limit }} caractères.')]
    private ?string $message = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Merci d\'indiquer une adresse e-mail.')]
    #[Assert\Email(message: 'Cette adresse e-mail n\'est pas valide.')]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    /**
     * Le message a-t-il reçu une réponse ?
     *
     * Le site annonce une réponse sous 48 heures : sans ce suivi, rien ne
     * distingue un message traité d'un message oublié.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $traite = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $dateTraitement = null;

    #[ORM\Column]
    private ?\DateTime $dateCreation = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

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

    public function isTraite(): bool
    {
        return $this->traite;
    }

    public function getDateTraitement(): ?\DateTime
    {
        return $this->dateTraitement;
    }

    /**
     * Marque le message comme traité, ou le rouvre.
     *
     * La date suit l'état : rouvrir un message efface sa date de traitement,
     * qui ne voudrait plus rien dire.
     */
    public function marquerTraite(bool $traite = true): static
    {
        $this->traite = $traite;
        $this->dateTraitement = $traite ? new \DateTime() : null;

        return $this;
    }

    /**
     * Le délai de réponse annoncé est-il dépassé ?
     */
    public function estEnRetard(): bool
    {
        if ($this->traite || null === $this->dateCreation) {
            return false;
        }

        return $this->dateCreation < new \DateTime('-48 hours');
    }
}
