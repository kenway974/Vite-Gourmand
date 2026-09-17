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

    public function disponible(): bool
    {
        return true;
    }
}
