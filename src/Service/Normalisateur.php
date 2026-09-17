<?php

namespace App\Service;

/**
 * Réduit un texte à une forme comparable : minuscules, sans accents,
 * espaces resserrés.
 *
 * Existe parce qu'une recherche ne doit pas dépendre du SGBD. S'en remettre à
 * la collation de la base marche sur MySQL, mais le comportement change selon
 * l'hébergeur, et SQLite — sur lequel tourne la suite de tests — ne plie pas
 * les accents du tout. Une exigence qu'on ne peut pas tester n'est pas tenue.
 *
 * « Pâté de foie » devient « pate de foie », des deux côtés de la comparaison.
 */
final class Normalisateur
{
    /**
     * Caractères accentués du français, et quelques voisins courants dans les
     * noms de plats. Une table explicite plutôt qu'iconv() ou Transliterator :
     * les deux dépendent d'extensions ou de réglages de locale qui varient
     * d'un serveur à l'autre, ce qui est précisément ce qu'on veut éviter.
     */
    private const REMPLACEMENTS = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'ý' => 'y', 'ÿ' => 'y',
        'ñ' => 'n', 'ç' => 'c',
        'œ' => 'oe', 'æ' => 'ae', 'ß' => 'ss',
    ];

    public static function pourRecherche(?string $texte): string
    {
        if (null === $texte || '' === trim($texte)) {
            return '';
        }

        // Minuscules d'abord : la table ne contient que des minuscules, et
        // mb_strtolower gère « É » là où strtolower le laisserait tel quel.
        $texte = mb_strtolower($texte, 'UTF-8');
        $texte = strtr($texte, self::REMPLACEMENTS);

        // Espaces resserrés : « pâté   de  foie » et « pâté de foie » doivent
        // se ramener à la même chaîne.
        return trim((string) preg_replace('/\s+/u', ' ', $texte));
    }
}
