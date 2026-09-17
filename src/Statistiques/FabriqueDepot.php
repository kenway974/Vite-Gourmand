<?php

namespace App\Statistiques;

/**
 * Choisit le dépôt de statistiques utilisable ici et maintenant.
 *
 * L'extension PHP mongodb n'est pas toujours installée — elle ne l'est pas sur
 * la machine de développement de ce projet, et elle ne l'est jamais pendant les
 * tests. Plutôt que de laisser l'application refuser de démarrer, on retombe
 * sur un dépôt en mémoire : les chiffres du jour restent calculables depuis
 * MySQL, seul l'historique manque.
 */
final class FabriqueDepot
{
    public static function creer(string $dsn, string $base = 'vite_gourmand'): DepotStatistiques
    {
        if ('' === trim($dsn) || !\extension_loaded('mongodb')) {
            return new DepotEnMemoire();
        }

        return new DepotMongo($dsn, $base);
    }
}
