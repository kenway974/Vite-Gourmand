<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose les horaires d'ouverture au pied de page, présent sur toutes les
 * pages. Le travail est délégué à un runtime : la requête n'est émise que
 * si le gabarit appelle réellement la fonction.
 */
class HoraireExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('horaires_semaine', [HoraireRuntime::class, 'semaine']),
            new TwigFunction('etablissement_ouvert', [HoraireRuntime::class, 'ouvertMaintenant']),
        ];
    }
}
