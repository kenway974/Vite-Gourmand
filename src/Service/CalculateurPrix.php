<?php

namespace App\Service;

use App\Entity\Menu;
use App\Entity\ZoneLivraison;

/**
 * Calcul du prix d'une commande.
 *
 * Trois règles, relevées dans les maquettes (voir docs/regles-metier.md) :
 *
 *  1. Menu::prixMin est un prix PAR PERSONNE, pas un montant plancher.
 *  2. Une remise de 10 % s'applique dès que le nombre de convives atteint
 *     le minimum du menu majoré de cinq. Le seuil est donc relatif au menu :
 *     un menu à 6 convives minimum donne droit à la remise dès 11.
 *  3. La livraison est comprise dans Bordeaux intra-muros. Ailleurs, elle
 *     dépend de la zone desservie : le supplément s'ajoute APRÈS la remise,
 *     qui porte sur les prestations, pas sur le transport.
 *
 * Les montants sont calculés en centimes entiers. Les nombres à virgule
 * flottante accumulent des erreurs d'arrondi qui n'ont pas leur place dans un
 * calcul facturé.
 */
final class CalculateurPrix
{
    /** Taux de la remise appliquée au-delà du seuil. */
    public const TAUX_REMISE = 0.10;

    /** Nombre de convives au-delà du minimum du menu déclenchant la remise. */
    public const CONVIVES_AU_DELA_DU_MINIMUM = 5;

    public function calculer(Menu $menu, int $nbPersonnes, ?ZoneLivraison $zone = null): DetailPrix
    {
        $minimum = $menu->getNbMinPersonnes() ?? 0;

        if ($nbPersonnes < $minimum) {
            throw new \InvalidArgumentException(sprintf(
                'Le menu « %s » demande %d convives au minimum, %d demandés.',
                $menu->getTitre(),
                $minimum,
                $nbPersonnes,
            ));
        }

        $unitaire = self::enCentimes((string) $menu->getPrixMin());
        $brut = $unitaire * $nbPersonnes;

        $seuil = $minimum + self::CONVIVES_AU_DELA_DU_MINIMUM;
        $remiseAcquise = $nbPersonnes >= $seuil;

        $taux = $remiseAcquise ? self::TAUX_REMISE : 0.0;
        $remise = $remiseAcquise ? (int) round($brut * self::TAUX_REMISE) : 0;

        // Zone absente : la livraison est traitée comme comprise. Le refus
        // d'une adresse non desservie relève de la validation de la commande,
        // pas du calcul du prix.
        $livraison = self::enCentimes((string) ($zone?->getFrais() ?? '0.00'));

        return new DetailPrix(
            prixUnitaire: self::enDecimal($unitaire),
            nbPersonnes: $nbPersonnes,
            montantBrut: self::enDecimal($brut),
            tauxRemise: $taux,
            montantRemise: self::enDecimal($remise),
            fraisLivraison: self::enDecimal($livraison),
            montantTotal: self::enDecimal($brut - $remise + $livraison),
            seuilRemise: $seuil,
            convivesManquantsPourRemise: max(0, $seuil - $nbPersonnes),
        );
    }

    /**
     * Convertit une chaîne décimale en centimes, sans passer par un flottant.
     *
     * « 24.00 » → 2400, « 24.5 » → 2450, « 24 » → 2400.
     */
    private static function enCentimes(string $montant): int
    {
        $parties = explode('.', trim($montant), 2);

        $entier = (int) $parties[0];
        $decimales = (int) substr(str_pad($parties[1] ?? '', 2, '0'), 0, 2);

        return $entier * 100 + $decimales;
    }

    private static function enDecimal(int $centimes): string
    {
        return sprintf('%d.%02d', intdiv($centimes, 100), $centimes % 100);
    }
}
