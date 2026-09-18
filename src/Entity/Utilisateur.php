<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cette adresse e-mail.')]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Merci de saisir une adresse e-mail.')]
    #[Assert\Email(message: "L'adresse {{ value }} n'est pas une adresse e-mail valide.")]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Merci de saisir un nom.')]
    #[Assert\Length(max: 100, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $nom = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Merci de saisir un prénom.')]
    #[Assert\Length(max: 100, maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $prenom = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    #[Assert\Regex(
        pattern: '/^[0-9 +().-]{6,20}$/',
        message: "Ce numéro de téléphone n'est pas valide.",
    )]
    private ?string $gsm = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $adressePostale = null;

    #[ORM\Column]
    private ?bool $actif = null;

    /**
     * Empreinte SHA-256 du jeton de réinitialisation, jamais le jeton lui-même :
     * une fuite de la base ne doit pas permettre de prendre les comptes.
     */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $jetonReinitialisation = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $jetonExpiration = null;

    /**
     * @var Collection<int, Commande>
     */
    #[ORM\OneToMany(targetEntity: Commande::class, mappedBy: 'utilisateur')]
    private Collection $commandes;

    /**
     * @var Collection<int, Avis>
     */
    #[ORM\OneToMany(targetEntity: Avis::class, mappedBy: 'utilisateur')]
    private Collection $avis;

    public function __construct()
    {
        $this->commandes = new ArrayCollection();
        $this->avis = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);
        
        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getGsm(): ?string
    {
        return $this->gsm;
    }

    public function setGsm(?string $gsm): static
    {
        $this->gsm = $gsm;

        return $this;
    }

    public function getAdressePostale(): ?string
    {
        return $this->adressePostale;
    }

    public function setAdressePostale(?string $adressePostale): static
    {
        $this->adressePostale = $adressePostale;

        return $this;
    }

    public function isActif(): ?bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;

        return $this;
    }

    /**
     * @return Collection<int, Commande>
     */
    public function getJetonReinitialisation(): ?string
    {
        return $this->jetonReinitialisation;
    }

    public function getJetonExpiration(): ?\DateTime
    {
        return $this->jetonExpiration;
    }

    /**
     * Enregistre une demande de réinitialisation.
     *
     * Le jeton en clair n'est pas conservé : il part par courriel, seul son
     * empreinte reste en base.
     */
    public function demanderReinitialisation(string $empreinte, \DateTimeInterface $expiration): static
    {
        $this->jetonReinitialisation = $empreinte;
        $this->jetonExpiration = \DateTime::createFromInterface($expiration);

        return $this;
    }

    /**
     * Un jeton ne sert qu'une fois : il est effacé dès qu'il a rempli son
     * office, ou dès que la demande est abandonnée.
     */
    public function oublierReinitialisation(): static
    {
        $this->jetonReinitialisation = null;
        $this->jetonExpiration = null;

        return $this;
    }

    public function reinitialisationEnCours(?\DateTimeInterface $date = null): bool
    {
        return null !== $this->jetonReinitialisation
            && null !== $this->jetonExpiration
            && $this->jetonExpiration > ($date ?? new \DateTime());
    }

    /**
     * Efface l'identité tout en conservant les commandes.
     *
     * Le droit à l'effacement (art. 17 RGPD) ne peut pas s'exercer par un
     * simple DELETE : le code de commerce impose de conserver les pièces
     * comptables dix ans, et les commandes en sont. Supprimer la ligne
     * emporterait aussi les avis et le suivi, et laisserait la comptabilité
     * avec des montants sans origine.
     *
     * On efface donc ce qui identifie, et on garde ce qui compte : le compte
     * reste, vidé de toute donnée personnelle, désactivé, et ne peut plus
     * servir à se connecter.
     */
    public function anonymiser(): static
    {
        // Un identifiant unique et stable, pour ne pas violer l'index sur
        // l'e-mail si plusieurs comptes sont anonymisés.
        $this->email = sprintf('anonyme-%s@invalide.local', bin2hex(random_bytes(8)));
        $this->nom = 'Compte supprimé';
        $this->prenom = 'Anonyme';
        $this->gsm = null;
        $this->adressePostale = null;
        $this->actif = false;
        $this->roles = [];

        // Un mot de passe aléatoire que personne ne connaît : plus court
        // qu'un vrai hachage, il rendrait la connexion impossible de toute
        // façon, mais autant ne rien laisser deviner.
        $this->password = bin2hex(random_bytes(32));

        // Une réinitialisation en cours rouvrirait une porte sur un compte
        // qu'on vient de fermer.
        $this->oublierReinitialisation();

        return $this;
    }

    public function estAnonymise(): bool
    {
        return str_ends_with((string) $this->email, '@invalide.local');
    }

    public function getCommandes(): Collection
    {
        return $this->commandes;
    }

    public function addCommande(Commande $commande): static
    {
        if (!$this->commandes->contains($commande)) {
            $this->commandes->add($commande);
            $commande->setUtilisateur($this);
        }

        return $this;
    }

    public function removeCommande(Commande $commande): static
    {
        if ($this->commandes->removeElement($commande)) {
            // set the owning side to null (unless already changed)
            if ($commande->getUtilisateur() === $this) {
                $commande->setUtilisateur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Avis>
     */
    public function getAvis(): Collection
    {
        return $this->avis;
    }

    public function addAvi(Avis $avi): static
    {
        if (!$this->avis->contains($avi)) {
            $this->avis->add($avi);
            $avi->setUtilisateur($this);
        }

        return $this;
    }

    public function removeAvi(Avis $avi): static
    {
        if ($this->avis->removeElement($avi)) {
            // set the owning side to null (unless already changed)
            if ($avi->getUtilisateur() === $this) {
                $avi->setUtilisateur(null);
            }
        }

        return $this;
    }
}
