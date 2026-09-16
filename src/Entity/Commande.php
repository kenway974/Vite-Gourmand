<?php

namespace App\Entity;

use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use App\Service\DetailPrix;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
class Commande
{
    /**
     * Statuts possibles d'une commande, dans leur ordre de progression.
     *
     * La colonne est une chaîne libre : sans ces constantes, rien n'empêche
     * une faute de frappe de créer un statut fantôme qu'aucun filtre ne
     * retrouvera.
     */
    public const EN_ATTENTE = 'en attente';
    public const CONFIRMEE = 'confirmée';
    public const EN_PREPARATION = 'en préparation';
    public const LIVREE = 'livrée';
    public const ANNULEE = 'annulée';

    /** @var list<string> */
    public const STATUTS = [
        self::EN_ATTENTE,
        self::CONFIRMEE,
        self::EN_PREPARATION,
        self::LIVREE,
        self::ANNULEE,
    ];

    /** Statuts après lesquels plus rien ne bouge. */
    public const STATUTS_FINAUX = [self::LIVREE, self::ANNULEE];

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
    #[Assert\NotNull(message: 'Merci de choisir une date de prestation.')]
    private ?\DateTime $datePrestation = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTime $heureLivraison = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Merci d\'indiquer un lieu de livraison.')]
    #[Assert\Length(max: 255)]
    private ?string $lieuLivraison = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Merci d\'indiquer le nombre de convives.')]
    #[Assert\Positive(message: 'Le nombre de convives doit être supérieur à zéro.')]
    private ?int $nbPersonnes = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $prixTotal = null;

    /**
     * Taux de remise appliqué, en pourcentage (10.00 pour 10 %).
     *
     * Figé au moment de la commande : la règle peut évoluer, une facture
     * émise ne doit pas changer rétroactivement.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['default' => '0.00'])]
    private string $tauxRemise = '0.00';

    /** Montant de la remise, conservé pour que le total reste justifiable. */
    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, options: ['default' => '0.00'])]
    private string $montantRemise = '0.00';

    #[ORM\Column(length: 30)]
    #[Assert\Choice(choices: self::STATUTS, message: 'Statut de commande inconnu.')]
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

    public function getTauxRemise(): string
    {
        return $this->tauxRemise;
    }

    public function setTauxRemise(string $tauxRemise): static
    {
        $this->tauxRemise = $tauxRemise;

        return $this;
    }

    public function getMontantRemise(): string
    {
        return $this->montantRemise;
    }

    public function setMontantRemise(string $montantRemise): static
    {
        $this->montantRemise = $montantRemise;

        return $this;
    }

    /**
     * Applique un calcul de prix à la commande, remise comprise.
     *
     * Passer par ce point unique évite qu'un appelant enregistre un total sans
     * la remise qui l'explique.
     */
    public function appliquerPrix(DetailPrix $detail): static
    {
        $this->nbPersonnes = $detail->nbPersonnes;
        $this->prixTotal = $detail->montantTotal;
        $this->montantRemise = $detail->montantRemise;
        $this->tauxRemise = sprintf('%.2f', $detail->tauxRemise * 100);

        return $this;
    }

    public function estTerminee(): bool
    {
        return \in_array($this->statut, self::STATUTS_FINAUX, true);
    }

    /**
     * Le client peut-il encore annuler ?
     *
     * Une commande déjà en préparation ne s'annule plus en libre-service :
     * les achats sont engagés.
     */
    public function estAnnulableParLeClient(): bool
    {
        return \in_array($this->statut, [self::EN_ATTENTE, self::CONFIRMEE], true);
    }

    /**
     * Vérifie que la commande est réalisable pour le menu choisi.
     *
     * Ces règles vivent sur l'entité plutôt que dans un contrôleur : elles
     * valent quel que soit le chemin emprunté pour créer la commande, qu'il
     * s'agisse du site, d'une commande console ou d'une future API.
     */
    #[Assert\Callback]
    public function validerFaisabilite(ExecutionContextInterface $context): void
    {
        if (null === $this->menu) {
            return;
        }

        $minimum = $this->menu->getNbMinPersonnes();

        if (null !== $this->nbPersonnes && null !== $minimum && $this->nbPersonnes < $minimum) {
            $context->buildViolation('Ce menu se commande à partir de {{ minimum }} convives.')
                ->setParameter('{{ minimum }}', (string) $minimum)
                ->atPath('nbPersonnes')
                ->addViolation();
        }

        if (null !== $this->datePrestation) {
            $delai = $this->menu->getDelaiCommandeJours() ?? 0;
            $premiereDate = (new \DateTime('today'))->modify(sprintf('+%d days', $delai));

            // Le délai n'est pas négociable : les approvisionnements sont
            // engagés auprès des producteurs dès la confirmation.
            if ($this->datePrestation < $premiereDate) {
                $context->buildViolation(
                    'Ce menu demande {{ delai }} jours de préparation : la première date possible est le {{ date }}.'
                )
                    ->setParameter('{{ delai }}', (string) $delai)
                    ->setParameter('{{ date }}', $premiereDate->format('d/m/Y'))
                    ->atPath('datePrestation')
                    ->addViolation();
            }

            if (!$this->menu->estDansSaPeriode($this->datePrestation)) {
                $context->buildViolation('Ce menu n\'est pas proposé à cette date.')
                    ->atPath('datePrestation')
                    ->addViolation();
            }
        }

        if (($this->menu->getStock() ?? 0) <= 0) {
            $context->buildViolation('Ce menu n\'est plus disponible à la commande.')
                ->atPath('menu')
                ->addViolation();
        }
    }
}
