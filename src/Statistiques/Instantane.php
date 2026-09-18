<?php

namespace App\Statistiques;

/**
 * Photographie de l'activité à un instant donné.
 *
 * Les montants sont des chaînes décimales, comme partout ailleurs dans le
 * projet : un chiffre d'affaires ne se manipule pas en flottant.
 */
final readonly class Instantane
{
    /**
     * @param array<string, array{menu: string, commandes: int, chiffreAffaires: string}> $parMenu
     * @param array<string, int>                                                          $parStatut
     */
    public function __construct(
        public \DateTimeImmutable $releveLe,
        public int $nbCommandes,
        public int $nbCommandesLivrees,
        public string $chiffreAffaires,
        public string $panierMoyen,
        public array $parMenu,
        public array $parStatut,
    ) {
    }

    /**
     * Forme document, pour le dépôt NoSQL.
     *
     * Un tableau imbriqué plutôt qu'un schéma plat : c'est précisément ce que
     * le document sait faire et que la table relationnelle ferait mal. Ajouter
     * une métrique demain ne demandera aucune migration.
     *
     * @return array<string, mixed>
     */
    public function enDocument(): array
    {
        return [
            'releveLe' => $this->releveLe->format(\DateTimeInterface::ATOM),
            'nbCommandes' => $this->nbCommandes,
            'nbCommandesLivrees' => $this->nbCommandesLivrees,
            'chiffreAffaires' => $this->chiffreAffaires,
            'panierMoyen' => $this->panierMoyen,
            'parMenu' => array_values($this->parMenu),
            'parStatut' => $this->parStatut,
        ];
    }

    /**
     * @param array<string, mixed> $document
     */
    public static function depuisDocument(array $document): self
    {
        $parMenu = [];

        foreach ($document['parMenu'] ?? [] as $ligne) {
            $parMenu[$ligne['menu']] = [
                'menu' => $ligne['menu'],
                'commandes' => (int) $ligne['commandes'],
                'chiffreAffaires' => (string) $ligne['chiffreAffaires'],
            ];
        }

        return new self(
            releveLe: new \DateTimeImmutable($document['releveLe']),
            nbCommandes: (int) $document['nbCommandes'],
            nbCommandesLivrees: (int) $document['nbCommandesLivrees'],
            chiffreAffaires: (string) $document['chiffreAffaires'],
            panierMoyen: (string) $document['panierMoyen'],
            parMenu: $parMenu,
            parStatut: array_map('intval', (array) ($document['parStatut'] ?? [])),
        );
    }
}
