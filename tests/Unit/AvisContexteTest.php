<?php

namespace App\Tests\Unit;

use App\Entity\Avis;
use App\Entity\Commande;
use PHPUnit\Framework\TestCase;

/**
 * Avis::contexte() : la ligne « Décembre 2024 · 8 convives » des témoignages.
 */
class AvisContexteTest extends TestCase
{
    public function testLeContexteReprendLaPrestationEtLeNombreDeConvives(): void
    {
        $commande = (new Commande())
            ->setDatePrestation(new \DateTime('2024-12-14'))
            ->setNbPersonnes(8);

        $avis = (new Avis())->setCommande($commande);

        self::assertSame('Décembre 2024 · 8 convives', $avis->contexte());
    }

    public function testUnAvisSansCommandeNAPasDeContexte(): void
    {
        self::assertNull((new Avis())->contexte());
    }

    public function testUneCommandeIncompleteNeDonnePasDeContexte(): void
    {
        $sansConvives = (new Commande())->setDatePrestation(new \DateTime('2024-12-14'));
        $sansDate = (new Commande())->setNbPersonnes(8);

        self::assertNull((new Avis())->setCommande($sansConvives)->contexte());
        self::assertNull((new Avis())->setCommande($sansDate)->contexte());
    }
}
