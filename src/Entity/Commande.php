<?php

namespace App\Entity;

use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
class Commande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'commandes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\ManyToOne(inversedBy: 'commandes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Menu $menu = null;

    #[ORM\OneToOne(mappedBy: 'commande', targetEntity: Avis::class, orphanRemoval: true)]
    private ?Avis $avis = null;

    #[ORM\Column]
    private ?\DateTime $dateCommande = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $datePrestation = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTime $heureLivraison = null;

    #[ORM\Column(length: 255)]
    private ?string $lieuLivraison = null;

    #[ORM\Column]
    private ?int $nbPersonnes = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $prixTotal = null;

    #[ORM\Column(length: 30)]
    private ?string $statut = null;

    #[ORM\Column]
    private ?bool $pretMateriel = null;

    /**
     * @var Collection<int, SuiviCommande>
     */
    #[ORM\OneToMany(targetEntity: SuiviCommande::class, mappedBy: 'commande', orphanRemoval: true)]
    private Collection $suiviCommandes;

    public function __construct()
    {
        $this->suiviCommandes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getMenu(): ?Menu
    {
        return $this->menu;
    }

    public function setMenu(?Menu $menu): static
    {
        $this->menu = $menu;

        return $this;
    }

    public function getAvis(): ?Avis
    {
        return $this->avis;
    }

    public function setAvis(?Avis $avis): static
    {
        // unset the owning side of the relation if necessary
        if ($avis === null && $this->avis !== null) {
            $this->avis->setCommande(null);
        }

        // set the owning side of the relation if necessary
        if ($avis !== null && $avis->getCommande() !== $this) {
            $avis->setCommande($this);
        }

        $this->avis = $avis;

        return $this;
    }

    public function getDateCommande(): ?\DateTime
    {
        return $this->dateCommande;
    }

    public function setDateCommande(\DateTime $dateCommande): static
    {
        $this->dateCommande = $dateCommande;

        return $this;
    }

    public function getDatePrestation(): ?\DateTime
    {
        return $this->datePrestation;
    }

    public function setDatePrestation(\DateTime $datePrestation): static
    {
        $this->datePrestation = $datePrestation;

        return $this;
    }

    public function getHeureLivraison(): ?\DateTime
    {
        return $this->heureLivraison;
    }

    public function setHeureLivraison(\DateTime $heureLivraison): static
    {
        $this->heureLivraison = $heureLivraison;

        return $this;
    }

    public function getLieuLivraison(): ?string
    {
        return $this->lieuLivraison;
    }

    public function setLieuLivraison(string $lieuLivraison): static
    {
        $this->lieuLivraison = $lieuLivraison;

        return $this;
    }

    public function getNbPersonnes(): ?int
    {
        return $this->nbPersonnes;
    }

    public function setNbPersonnes(int $nbPersonnes): static
    {
        $this->nbPersonnes = $nbPersonnes;

        return $this;
    }

    public function getPrixTotal(): ?string
    {
        return $this->prixTotal;
    }

    public function setPrixTotal(string $prixTotal): static
    {
        $this->prixTotal = $prixTotal;

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

    public function isPretMateriel(): ?bool
    {
        return $this->pretMateriel;
    }

    public function setPretMateriel(bool $pretMateriel): static
    {
        $this->pretMateriel = $pretMateriel;

        return $this;
    }

    /**
     * @return Collection<int, SuiviCommande>
     */
    public function getSuiviCommandes(): Collection
    {
        return $this->suiviCommandes;
    }

    public function addSuiviCommande(SuiviCommande $suiviCommande): static
    {
        if (!$this->suiviCommandes->contains($suiviCommande)) {
            $this->suiviCommandes->add($suiviCommande);
            $suiviCommande->setCommande($this);
        }

        return $this;
    }

    public function removeSuiviCommande(SuiviCommande $suiviCommande): static
    {
        if ($this->suiviCommandes->removeElement($suiviCommande)) {
            // set the owning side to null (unless already changed)
            if ($suiviCommande->getCommande() === $this) {
                $suiviCommande->setCommande(null);
            }
        }

        return $this;
    }
}
