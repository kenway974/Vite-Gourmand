<?php

namespace App\Twig;

use App\Entity\Horaire;
use App\Repository\HoraireRepository;
use Twig\Extension\RuntimeExtensionInterface;

class HoraireRuntime implements RuntimeExtensionInterface
{
    /** @var Horaire[]|null */
    private ?array $semaine = null;

    public function __construct(private readonly HoraireRepository $depot)
    {
    }

    /**
     * @return Horaire[]
     */
    public function semaine(): array
    {
        // Le pied de page et l'indicateur d'ouverture lisent la même semaine :
        // une seule requête par page, quel que soit le nombre d'appels.
        return $this->semaine ??= $this->depot->semaine();
    }

    public function ouvertMaintenant(): bool
    {
        $maintenant = new \DateTime();
        $jour = Horaire::JOURS[(int) $maintenant->format('N') - 1];

        foreach ($this->semaine() as $horaire) {
            if ($horaire->getJour() === $jour) {
                return $horaire->couvre($maintenant);
            }
        }

        return false;
    }
}
