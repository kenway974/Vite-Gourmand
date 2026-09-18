<?php

namespace App\Service;

/**
 * Résultat détaillé d'un calcul de prix.
 *
 * Le détail est conservé plutôt qu'un simple total : une facture doit pouvoir
 * justifier son montant, et l'interface a besoin de savoir combien de convives
 * manquent pour déclencher la remise.
 *
 * Tous les montants sont exprimés en chaînes décimales à deux décimales, au
 * format attendu par les colonnes NUMERIC de la base.
 */
final readonly class DetailPrix
{
    public function __construct(
        public string $prixUnitaire,
        public int $nbPersonnes,
        public string $montantBrut,
        public float $tauxRemise,
        public string $montantRemise,
        public string $fraisLivraison,
        public string $montantTotal,
        public int $seuilRemise,
        public int $convivesManquantsPourRemise,
    ) {
    }

    public function beneficieDeLaRemise(): bool
    {
        return $this->tauxRemise > 0.0;
    }

    public function livraisonComprise(): bool
    {
        return 0.0 === (float) $this->fraisLivraison;
    }
}
