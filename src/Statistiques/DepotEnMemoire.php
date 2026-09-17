<?php

namespace App\Statistiques;

/**
 * Dépôt de secours, sans serveur.
 *
 * Sert aux tests, et en développement quand l'extension mongodb n'est pas
 * installée : l'application démarre et la page de statistiques reste lisible,
 * seul l'historique est vide.
 */
final class DepotEnMemoire implements DepotStatistiques
{
    /** @var Instantane[] */
    private array $releves = [];

    public function enregistrer(Instantane $instantane): void
    {
        $this->releves[] = $instantane;
    }

    public function dernier(): ?Instantane
    {
        return $this->historique(1)[0] ?? null;
    }

    public function historique(int $limite = 30): array
    {
        $tries = $this->releves;

        usort($tries, fn (Instantane $a, Instantane $b) => $b->releveLe <=> $a->releveLe);

        return \array_slice($tries, 0, $limite);
    }

    /**
     * Toujours faux, et ce n'est pas une omission.
     *
     * Ce dépôt garde les relevés dans la mémoire du processus : tout est perdu
     * à la fin de la requête ou de la commande. Répondre « disponible » ferait
     * annoncer « relevé enregistré » à la commande de nuit alors que rien
     * n'aura survécu, et masquerait l'avertissement sur la page
     * d'administration. Mieux vaut dire la vérité : il n'y a pas de stockage.
     */
    public function disponible(): bool
    {
        return false;
    }
}
