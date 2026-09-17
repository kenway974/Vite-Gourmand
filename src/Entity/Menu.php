<?php

namespace App\Entity;

use App\Repository\MenuRepository;
use App\Service\Normalisateur;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MenuRepository::class)]
#[Assert\Expression(
    'this.getDateFin() === null or this.getDateDebut() === null or this.getDateFin() >= this.getDateDebut()',
    message: 'La date de fin de disponibilité doit être postérieure à la date de début.',
)]
class Menu
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Merci de saisir un titre.')]
    #[Assert\Length(max: 150, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Merci de saisir une description.')]
    private ?string $description = null;

    /**
     * Titre et description réduits à une forme comparable : minuscules, sans
     * accents. C'est sur cette colonne que porte la recherche du catalogue.
     *
     * Tenue à jour par les setters plutôt que par un événement Doctrine :
     * modifier un champ dans preUpdate ne le persiste pas sans passer par
     * l'API des changesets, un piège classique.
     *
     * Pas d'index : un LIKE commençant par « % » n'en utiliserait aucun.
     */
    #[ORM\Column(type: Types::TEXT)]
    private string $recherche = '';

    #[ORM\ManyToOne(inversedBy: 'menus')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Merci de choisir un thème.')]
    private ?Theme $theme = null;

    #[ORM\ManyToOne(inversedBy: 'menus')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Merci de choisir un régime.')]
    private ?Regime $regime = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Merci d\'indiquer un nombre minimum de personnes.')]
    #[Assert\Positive(message: 'Le nombre minimum de personnes doit être supérieur à zéro.')]
    private ?int $nbMinPersonnes = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2)]
    #[Assert\NotNull(message: 'Merci d\'indiquer un prix.')]
    #[Assert\Positive(message: 'Le prix doit être supérieur à zéro.')]
    private ?string $prixMin = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Merci d\'indiquer un délai de commande.')]
    #[Assert\PositiveOrZero(message: 'Le délai de commande ne peut pas être négatif.')]
    private ?int $delaiCommandeJours = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $precautions = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Merci d\'indiquer un stock.')]
    #[Assert\PositiveOrZero(message: 'Le stock ne peut pas être négatif.')]
    private ?int $stock = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $image = null;

    /**
     * Début de la période pendant laquelle le menu est proposé.
     * null signifie « disponible toute l'année ».
     */
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $dateDebut = null;

    /**
     * Fin de la période. null signifie « sans date de fin ».
     */
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $dateFin = null;

    /**
     * @var Collection<int, Plat>
     */
    #[ORM\ManyToMany(targetEntity: Plat::class, mappedBy: 'menus')]
    private Collection $plats;

    /**
     * @var Collection<int, Commande>
     */
    #[ORM\OneToMany(targetEntity: Commande::class, mappedBy: 'menu')]
    private Collection $commandes;

    public function __construct()
    {
        $this->plats = new ArrayCollection();
        $this->commandes = new ArrayCollection();
    }

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
        $this->majRecherche();

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        $this->majRecherche();

        return $this;
    }

    public function getRecherche(): string
    {
        return $this->recherche;
    }

    private function majRecherche(): void
    {
        $this->recherche = Normalisateur::pourRecherche(
            trim($this->titre.' '.$this->description),
        );
    }

    public function getTheme(): ?Theme
    {
        return $this->theme;
    }

    public function setTheme(?Theme $theme): static
    {
        $this->theme = $theme;

        return $this;
    }

    public function getRegime(): ?Regime
    {
        return $this->regime;
    }

    public function setRegime(?Regime $regime): static
    {
        $this->regime = $regime;

        return $this;
    }

    public function getNbMinPersonnes(): ?int
    {
        return $this->nbMinPersonnes;
    }

    public function setNbMinPersonnes(int $nbMinPersonnes): static
    {
        $this->nbMinPersonnes = $nbMinPersonnes;

        return $this;
    }

    public function getPrixMin(): ?string
    {
        return $this->prixMin;
    }

    public function setPrixMin(string $prixMin): static
    {
        $this->prixMin = $prixMin;

        return $this;
    }

    public function getDelaiCommandeJours(): ?int
    {
        return $this->delaiCommandeJours;
    }

    public function setDelaiCommandeJours(int $delaiCommandeJours): static
    {
        $this->delaiCommandeJours = $delaiCommandeJours;

        return $this;
    }

    public function getPrecautions(): ?string
    {
        return $this->precautions;
    }

    public function setPrecautions(?string $precautions): static
    {
        $this->precautions = $precautions;

        return $this;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(int $stock): static
    {
        $this->stock = $stock;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    /**
     * @return Collection<int, Plat>
     */
    public function getPlats(): Collection
    {
        return $this->plats;
    }

    public function addPlat(Plat $plat): static
    {
        if (!$this->plats->contains($plat)) {
            $this->plats->add($plat);
            $plat->addMenu($this);
        }

        return $this;
    }

    public function removePlat(Plat $plat): static
    {
        if ($this->plats->removeElement($plat)) {
            $plat->removeMenu($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Commande>
     */
    public function getCommandes(): Collection
    {
        return $this->commandes;
    }

    public function addCommande(Commande $commande): static
    {
        if (!$this->commandes->contains($commande)) {
            $this->commandes->add($commande);
            $commande->setMenu($this);
        }

        return $this;
    }

    public function removeCommande(Commande $commande): static
    {
        if ($this->commandes->removeElement($commande)) {
            // set the owning side to null (unless already changed)
            if ($commande->getMenu() === $this) {
                $commande->setMenu(null);
            }
        }

        return $this;
    }

    /**
     * États de disponibilité d'un menu, dans l'ordre où ils sont éprouvés.
     */
    public const DISPONIBLE = 'disponible';
    public const BIENTOT = 'bientot';
    public const TERMINE = 'termine';
    public const EPUISE = 'epuise';

    public function getDateDebut(): ?\DateTime
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTime $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTime
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTime $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    /**
     * Le menu est-il dans sa période de disponibilité ?
     *
     * Les deux bornes sont facultatives et incluses. Un menu sans aucune date
     * est proposé toute l'année.
     */
    public function estDansSaPeriode(?\DateTimeInterface $date = null): bool
    {
        $jour = ($date ?? new \DateTime())->format('Y-m-d');

        if (null !== $this->dateDebut && $jour < $this->dateDebut->format('Y-m-d')) {
            return false;
        }

        if (null !== $this->dateFin && $jour > $this->dateFin->format('Y-m-d')) {
            return false;
        }

        return true;
    }

    /**
     * Le menu peut-il être commandé à cette date ?
     *
     * Deux conditions distinctes : être dans sa période, et avoir du stock.
     */
    public function estCommandable(?\DateTimeInterface $date = null): bool
    {
        return $this->estDansSaPeriode($date) && $this->stock > 0;
    }

    /**
     * État à afficher au visiteur.
     *
     * La période est éprouvée avant le stock : un menu de Noël consulté en
     * juillet doit annoncer « bientôt disponible », pas « épuisé ».
     */
    public function disponibilite(?\DateTimeInterface $date = null): string
    {
        $jour = ($date ?? new \DateTime())->format('Y-m-d');

        if (null !== $this->dateDebut && $jour < $this->dateDebut->format('Y-m-d')) {
            return self::BIENTOT;
        }

        if (null !== $this->dateFin && $jour > $this->dateFin->format('Y-m-d')) {
            return self::TERMINE;
        }

        return $this->stock > 0 ? self::DISPONIBLE : self::EPUISE;
    }
}
