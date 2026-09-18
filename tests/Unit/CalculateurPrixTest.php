<?php

namespace App\Tests\Unit;

use App\Entity\Menu;
use App\Service\CalculateurPrix;
use PHPUnit\Framework\TestCase;

/**
 * Calcul du prix d'une commande.
 *
 * Les deux règles éprouvées ici viennent des maquettes : le prix est unitaire
 * et par personne, et la remise de 10 % se déclenche à un seuil relatif au
 * minimum de chaque menu (voir docs/regles-metier.md).
 */
class CalculateurPrixTest extends TestCase
{
    private CalculateurPrix $calculateur;

    protected function setUp(): void
    {
        $this->calculateur = new CalculateurPrix();
    }

    public function testLePrixEstMultipliedParLeNombreDeConvives(): void
    {
        $menu = $this->menu(prix: '24.00', minimum: 6);

        $detail = $this->calculateur->calculer($menu, 6);

        self::assertSame('144.00', $detail->montantBrut);
        self::assertSame('144.00', $detail->montantTotal);
        self::assertFalse($detail->beneficieDeLaRemise());
    }

    public function testLaRemiseSApplliqueAuSeuil(): void
    {
        // Minimum 6, donc seuil à 11 convives.
        $menu = $this->menu(prix: '24.00', minimum: 6);

        $detail = $this->calculateur->calculer($menu, 11);

        self::assertSame(11, $detail->seuilRemise);
        self::assertTrue($detail->beneficieDeLaRemise());
        self::assertSame('264.00', $detail->montantBrut);
        self::assertSame('26.40', $detail->montantRemise);
        self::assertSame('237.60', $detail->montantTotal);
    }

    public function testAucuneRemiseJusteEnDessousDuSeuil(): void
    {
        $menu = $this->menu(prix: '24.00', minimum: 6);

        $detail = $this->calculateur->calculer($menu, 10);

        self::assertFalse($detail->beneficieDeLaRemise());
        self::assertSame('0.00', $detail->montantRemise);
        self::assertSame('240.00', $detail->montantTotal);
        self::assertSame(1, $detail->convivesManquantsPourRemise);
    }

    public function testLeSeuilEstRelatifAuMenuEtNonAbsolu(): void
    {
        // Un menu à 20 convives minimum ne donne pas droit à la remise à 11 :
        // son seuil à lui est 25. C'est le point que les maquettes précisent.
        $menu = $this->menu(prix: '36.00', minimum: 20);

        $sansRemise = $this->calculateur->calculer($menu, 24);
        $avecRemise = $this->calculateur->calculer($menu, 25);

        self::assertSame(25, $sansRemise->seuilRemise);
        self::assertFalse($sansRemise->beneficieDeLaRemise());
        self::assertSame('864.00', $sansRemise->montantTotal);

        self::assertTrue($avecRemise->beneficieDeLaRemise());
        self::assertSame('900.00', $avecRemise->montantBrut);
        self::assertSame('90.00', $avecRemise->montantRemise);
        self::assertSame('810.00', $avecRemise->montantTotal);
    }

    public function testLaRemiseResteAcquiseAuDelaDuSeuil(): void
    {
        $menu = $this->menu(prix: '18.50', minimum: 6);

        $detail = $this->calculateur->calculer($menu, 12);

        self::assertSame('222.00', $detail->montantBrut);
        self::assertSame('22.20', $detail->montantRemise);
        self::assertSame('199.80', $detail->montantTotal);
        self::assertSame(0, $detail->convivesManquantsPourRemise);
    }

    public function testUnNombreDeConvivesInsuffisantEstRefuse(): void
    {
        $menu = $this->menu(prix: '24.00', minimum: 6);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('demande 6 convives au minimum, 5 demandés');

        $this->calculateur->calculer($menu, 5);
    }

    public function testLesCentimesNeSePerdentPasEnRoute(): void
    {
        // 24,05 € × 11 = 264,55 €. La remise vaut 26,455 € : elle doit être
        // arrondie au centime, pas tronquée ni laissée en flottant.
        $menu = $this->menu(prix: '24.05', minimum: 6);

        $detail = $this->calculateur->calculer($menu, 11);

        self::assertSame('264.55', $detail->montantBrut);
        self::assertSame('26.46', $detail->montantRemise);
        self::assertSame('238.09', $detail->montantTotal);
    }

    public function testLeTotalEstToujoursLeBrutMoinsLaRemise(): void
    {
        // Éprouvé sur un éventail de prix et d'effectifs : aucune combinaison
        // ne doit produire un total incohérent avec ses composantes.
        foreach (['12.00', '18.50', '24.05', '33.33', '58.00'] as $prix) {
            foreach ([4, 6, 11, 25, 60] as $convives) {
                $menu = $this->menu(prix: $prix, minimum: 4);
                $d = $this->calculateur->calculer($menu, $convives);

                $centimes = fn (string $m): int => (int) str_replace('.', '', $m);

                self::assertSame(
                    $centimes($d->montantBrut) - $centimes($d->montantRemise),
                    $centimes($d->montantTotal),
                    sprintf('Incohérence à %s € pour %d convives.', $prix, $convives)
                );
            }
        }
    }

    public function testLeDetailExposeLePrixUnitaireEtLEffectif(): void
    {
        $detail = $this->calculateur->calculer($this->menu(prix: '42.00', minimum: 6), 8);

        self::assertSame('42.00', $detail->prixUnitaire);
        self::assertSame(8, $detail->nbPersonnes);
        self::assertSame(3, $detail->convivesManquantsPourRemise);
    }

    private function menu(string $prix, int $minimum): Menu
    {
        return (new Menu())
            ->setTitre('Menu de test')
            ->setDescription('Description.')
            ->setPrixMin($prix)
            ->setNbMinPersonnes($minimum)
            ->setDelaiCommandeJours(3)
            ->setStock(10);
    }
}
