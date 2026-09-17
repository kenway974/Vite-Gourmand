<?php

namespace App\Statistiques;

use MongoDB\Client;
use MongoDB\Collection;

/**
 * Relevés d'activité conservés dans MongoDB.
 *
 * Le client est construit à la demande et non dans le constructeur : la page
 * d'administration doit rester consultable quand le serveur NoSQL est
 * injoignable, avec les chiffres du jour recalculés depuis MySQL. Une panne de
 * l'historique ne doit pas fermer l'écran.
 */
final class DepotMongo implements DepotStatistiques
{
    private ?Collection $collection = null;
    private ?bool $joignable = null;

    public function __construct(
        private readonly string $dsn,
        private readonly string $base = 'vite_gourmand',
        private readonly string $collectionNom = 'statistiques',
    ) {
    }

    /**
     * Volontairement sans filet : l'écriture n'a lieu que depuis la commande de
     * nuit, où une panne doit remonter en clair et faire échouer l'exécution.
     * L'avaler ferait croire à un relevé enregistré qui n'existe pas.
     */
    public function enregistrer(Instantane $instantane): void
    {
        $this->collection()->insertOne($instantane->enDocument());
    }

    public function dernier(): ?Instantane
    {
        try {
            $document = $this->collection()->findOne([], ['sort' => ['releveLe' => -1]]);
        } catch (\Throwable) {
            // Le serveur peut tomber entre disponible() et cet appel. Une page
            // d'administration ne doit pas rendre 500 pour un historique
            // manquant : les chiffres du jour viennent de MySQL et restent bons.
            $this->joignable = false;

            return null;
        }

        return null === $document
            ? null
            : Instantane::depuisDocument((array) $document);
    }

    public function historique(int $limite = 30): array
    {
        try {
            $documents = $this->collection()->find(
                [],
                ['sort' => ['releveLe' => -1], 'limit' => $limite],
            );
        } catch (\Throwable) {
            // Même raison que dans dernier() : l'écran survit à la panne.
            $this->joignable = false;

            return [];
        }

        return array_map(
            static fn ($d) => Instantane::depuisDocument((array) $d),
            iterator_to_array($documents, false),
        );
    }

    public function disponible(): bool
    {
        if (null !== $this->joignable) {
            return $this->joignable;
        }

        try {
            // Un ping plutôt qu'une lecture : on veut savoir si le serveur
            // répond, pas s'il contient quelque chose.
            $this->collection()->countDocuments([], ['limit' => 1]);

            return $this->joignable = true;
        } catch (\Throwable) {
            return $this->joignable = false;
        }
    }

    private function collection(): Collection
    {
        return $this->collection ??= (new Client($this->dsn, [
            // Sans borne, le pilote attend trente secondes avant de renoncer :
            // chaque affichage de la page bloquerait une demi-minute quand le
            // serveur ne répond pas, ce qui revient à fermer l'écran.
            'serverSelectionTimeoutMS' => 2000,
            'connectTimeoutMS' => 2000,
        ]))
            ->selectDatabase($this->base)
            ->selectCollection($this->collectionNom);
    }
}
