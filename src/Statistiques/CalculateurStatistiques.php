<?php

namespace App\Statistiques;

use App\Entity\Commande;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Calcule les statistiques d'activité à partir des commandes.
 *
 * La source reste MySQL : c'est là que vivent les commandes. Mongo ne sert
 * qu'à conserver les relevés successifs, ce qu'une table relationnelle ferait
 * mal — chaque relevé porte une ventilation par menu de taille variable, et
 * ajouter une métrique demain ne doit pas demander de migration.
 *
 * Deux comptes distincts, et ce n'est pas un détail :
 *
 *  - le CHIFFRE D'AFFAIRES ne retient que les commandes livrées. Une commande
 *    en attente n'est pas un encaissement, et une commande annulée n'en sera
 *    jamais un.
 *  - le NOMBRE DE COMMANDES retient tout sauf les annulées : c'est l'activité,
 *    pas la recette.
 */
final class CalculateurStatistiques
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function calculer(?\DateTimeImmutable $maintenant = null): Instantane
    {
        $lignes = $this->em->createQuery(
            'SELECT m.titre AS menu, c.statut AS statut, COUNT(c.id) AS nb, SUM(c.prixTotal) AS total
             FROM App\Entity\Commande c
             JOIN c.menu m
             GROUP BY m.titre, c.statut'
        )->getArrayResult();

        $parMenu = [];
        $parStatut = array_fill_keys(Commande::STATUTS, 0);
        $centimesCA = 0;
        $nbCommandes = 0;
        $nbLivrees = 0;

        foreach ($lignes as $ligne) {
            $titre = $ligne['menu'];
            $nb = (int) $ligne['nb'];
            $statut = $ligne['statut'];

            $parStatut[$statut] = ($parStatut[$statut] ?? 0) + $nb;

            if (Commande::ANNULEE === $statut) {
                continue;
            }

            $nbCommandes += $nb;

            $parMenu[$titre] ??= ['menu' => $titre, 'commandes' => 0, 'chiffreAffaires' => 0];
            $parMenu[$titre]['commandes'] += $nb;

            if (Commande::LIVREE === $statut) {
                $centimes = self::enCentimes((string) $ligne['total']);
                $parMenu[$titre]['chiffreAffaires'] += $centimes;
                $centimesCA += $centimes;
                $nbLivrees += $nb;
            }
        }

        // Le chiffre d'affaires en tête de liste : c'est ce qu'on vient voir.
        uasort($parMenu, fn (array $a, array $b) => $b['chiffreAffaires'] <=> $a['chiffreAffaires']);

        foreach ($parMenu as $titre => $ligne) {
            $parMenu[$titre]['chiffreAffaires'] = self::enDecimal($ligne['chiffreAffaires']);
        }

        return new Instantane(
            releveLe: $maintenant ?? new \DateTimeImmutable(),
            nbCommandes: $nbCommandes,
            nbCommandesLivrees: $nbLivrees,
            chiffreAffaires: self::enDecimal($centimesCA),
            // Le panier moyen se rapporte aux commandes livrées : diviser par
            // des commandes sans recette le tirerait artificiellement vers le bas.
            panierMoyen: self::enDecimal($nbLivrees > 0 ? intdiv($centimesCA, $nbLivrees) : 0),
            parMenu: $parMenu,
            parStatut: $parStatut,
        );
    }

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
