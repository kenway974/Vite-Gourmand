<?php

namespace App\Entity;

use App\Repository\IngredientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: IngredientRepository::class)]
class Ingredient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Merci de saisir un nom.')]
    #[Assert\Length(max: 100, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $image = null;

    /**
     * @var Collection<int, Allergene>
     */
    #[ORM\ManyToMany(targetEntity: Allergene::class, inversedBy: 'ingredients')]
    private Collection $allergenes;

    /**
     * @var Collection<int, Plat>
     */
    #[ORM\ManyToMany(targetEntity: Plat::class, mappedBy: 'ingredients')]
    private Collection $plats;

    public function __construct()
    {
        $this->allergenes = new ArrayCollection();
        $this->plats = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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
     * @return Collection<int, Allergene>
     */
    public function getAllergenes(): Collection
    {
        return $this->allergenes;
    }

    public function addAllergene(Allergene $allergene): static
    {
        if (!$this->allergenes->contains($allergene)) {
            $this->allergenes->add($allergene);
        }

        return $this;
    }

    public function removeAllergene(Allergene $allergene): static
    {
        $this->allergenes->removeElement($allergene);

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
            $plat->addIngredient($this);
        }

        return $this;
    }

    public function removePlat(Plat $plat): static
    {
        if ($this->plats->removeElement($plat)) {
            $plat->removeIngredient($this);
        }

        return $this;
    }
}
