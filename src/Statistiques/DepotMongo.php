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

    public function enregistrer(Instantane $instantane): void
    {
        $this->collection()->insertOne($instantane->enDocument());
    }

    public function dernier(): ?Instantane
    {
        $document = $this->collection()->findOne([], ['sort' => ['releveLe' => -1]]);

        return null === $document
            ? null
            : Instantane::depuisDocument((array) $document);
    }

    public function historique(int $limite = 30): array
    {
        $documents = $this->collection()->find(
            [],
            ['sort' => ['releveLe' => -1], 'limit' => $limite],
        );

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
        return $this->collection ??= (new Client($this->dsn))
            ->selectDatabase($this->base)
            ->selectCollection($this->collectionNom);
    }
}
