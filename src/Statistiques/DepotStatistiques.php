<?php

namespace App\Statistiques;

/**
 * Rangement des relevés d'activité.
 *
 * Interface plutôt qu'appel direct à MongoDB : le calcul des statistiques est
 * du métier et doit pouvoir être éprouvé sans serveur NoSQL. Seul
 * l'implémentation qui parle à Mongo dépend de l'extension PHP.
 */
interface DepotStatistiques
{
    public function enregistrer(Instantane $instantane): void;

    public function dernier(): ?Instantane;

    /**
     * Du plus récent au plus ancien.
     *
     * @return Instantane[]
     */
    public function historique(int $limite = 30): array;

    /**
     * Le dépôt répond-il ? La page d'administration reste utile sans lui — les
     * chiffres du jour se recalculent depuis MySQL — mais l'historique, non.
     */
    public function disponible(): bool;
}
