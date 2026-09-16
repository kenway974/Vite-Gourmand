<?php

namespace App\Entity;

use App\Repository\ZoneLivraisonRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Zone de livraison desservie, et son éventuel supplément.
 *
 * Les maquettes disent que « le prix inclut la livraison, le dressage et la
 * reprise du matériel dans un rayon de Bordeaux intra-muros », et se taisent
 * sur le reste. Plutôt que d'inventer un barème, les zones sont des données :
 * le traiteur décide quels codes postaux il dessert et à quel prix. Un code
 * postal absent de cette table n'est pas desservi — refuser vaut mieux que
 * facturer un tarif que personne n'a fixé.
 */
#[ORM\Entity(repositoryClass: ZoneLivraisonRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_zone_code_postal', columns: ['code_postal'])]
#[UniqueEntity(fields: ['codePostal'], message: 'Le code postal {{ value }} est déjà rattaché à une zone.')]
class ZoneLivraison
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 5)]
    #[Assert\NotBlank(message: 'Le code postal est obligatoire.')]
    #[Assert\Regex(pattern: '/^\d{5}$/', message: 'Un code postal français compte cinq chiffres.')]
    private ?string $codePostal = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom de la commune est obligatoire.')]
    #[Assert\Length(max: 100)]
    private ?string $commune = null;

    /**
     * Supplément de livraison, en euros. Zéro pour les communes où la
     * livraison est comprise dans le prix du menu.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2, options: ['default' => '0.00'])]
    #[Assert\NotBlank(message: 'Indiquez un supplément, même nul.')]
    #[Assert\PositiveOrZero(message: 'Le supplément ne peut pas être négatif.')]
    private ?string $frais = '0.00';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(string $codePostal): static
    {
        $this->codePostal = $codePostal;

        return $this;
    }

    public function getCommune(): ?string
    {
        return $this->commune;
    }

    public function setCommune(string $commune): static
    {
        $this->commune = $commune;

        return $this;
    }

    public function getFrais(): ?string
    {
        return $this->frais;
    }

    public function setFrais(string $frais): static
    {
        $this->frais = $frais;

        return $this;
    }

    public function livraisonComprise(): bool
    {
        return 0.0 === (float) $this->frais;
    }

    public function libelle(): string
    {
        return sprintf('%s (%s)', $this->commune, $this->codePostal);
    }
}
