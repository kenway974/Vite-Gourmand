<?php

namespace App\Entity;

use App\Repository\SuiviCommandeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SuiviCommandeRepository::class)]
class SuiviCommande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'suiviCommandes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Commande $commande = null;

    #[ORM\Column(length: 30)]
    #[Assert\Choice(choices: Commande::STATUTS, message: 'Statut de commande inconnu.')]
    private ?string $statut = null;

    #[ORM\Column]
    private ?\DateTime $dateModification = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $motif = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $modeContact = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommande(): ?Commande
    {
        return $this->commande;
    }

    public function setCommande(?Commande $commande): static
    {
        $this->commande = $commande;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getDateModification(): ?\DateTime
    {
        return $this->dateModification;
    }

    public function setDateModification(\DateTime $dateModification): static
    {
        $this->dateModification = $dateModification;

        return $this;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(?string $motif): static
    {
        $this->motif = $motif;

        return $this;
    }

    public function getModeContact(): ?string
    {
        return $this->modeContact;
    }

    public function setModeContact(?string $modeContact): static
    {
        $this->modeContact = $modeContact;

        return $this;
    }
}
