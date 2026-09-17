<?php

namespace App\Tests\Unit;

use App\Entity\Avis;
use App\Entity\Commande;
use PHPUnit\Framework\TestCase;

/**
 * Statuts des commandes et des avis.
 */
class StatutsTest extends TestCase
{
    public function testUneCommandeLivreeOuAnnuleeEstTerminee(): void
    {
        self::assertTrue((new Commande())->setStatut(Commande::LIVREE)->estTerminee());
        self::assertTrue((new Commande())->setStatut(Commande::ANNULEE)->estTerminee());

        foreach ([Commande::EN_ATTENTE, Commande::CONFIRMEE, Commande::EN_PREPARATION] as $statut) {
            self::assertFalse((new Commande())->setStatut($statut)->estTerminee(), $statut);
        }
    }

    public function testLeClientNAnnulePlusUneCommandeEnPreparation(): void
    {
        // Les achats sont engagés : l'annulation passe par le restaurant.
        self::assertTrue((new Commande())->setStatut(Commande::EN_ATTENTE)->estAnnulableParLeClient());
        self::assertTrue((new Commande())->setStatut(Commande::CONFIRMEE)->estAnnulableParLeClient());

        foreach ([Commande::EN_PREPARATION, Commande::LIVREE, Commande::ANNULEE] as $statut) {
            self::assertFalse((new Commande())->setStatut($statut)->estAnnulableParLeClient(), $statut);
        }
    }

    public function testSeulUnAvisValideEstPublie(): void
    {
        self::assertTrue((new Avis())->setStatutValidation(Avis::VALIDE)->estPublie());
        self::assertFalse((new Avis())->setStatutValidation(Avis::EN_ATTENTE)->estPublie());
        self::assertFalse((new Avis())->setStatutValidation(Avis::REFUSE)->estPublie());

        self::assertTrue((new Avis())->setStatutValidation(Avis::EN_ATTENTE)->attendModeration());
        self::assertFalse((new Avis())->setStatutValidation(Avis::REFUSE)->attendModeration());
    }

    public function testLesListesDeStatutsSontCompletes(): void
    {
        self::assertCount(5, Commande::STATUTS);
        self::assertCount(3, Avis::STATUTS);
        self::assertContains(Commande::LIVREE, Commande::STATUTS);
        self::assertContains(Avis::REFUSE, Avis::STATUTS);
    }
}
