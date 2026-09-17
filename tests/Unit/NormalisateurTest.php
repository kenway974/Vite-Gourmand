<?php

namespace App\Tests\Unit;

use App\Service\Normalisateur;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NormalisateurTest extends TestCase
{
    #[DataProvider('textes')]
    public function testReduitLeTexteAUneFormeComparable(string $entree, string $attendu): void
    {
        self::assertSame($attendu, Normalisateur::pourRecherche($entree));
    }

    public static function textes(): iterable
    {
        yield 'accents' => ['Pâté de foie', 'pate de foie'];
        yield 'casse' => ['ENTRECÔTE', 'entrecote'];
        yield 'ligatures' => ['Cœur de bœuf', 'coeur de boeuf'];
        yield 'cédille' => ['Provençale', 'provencale'];
        yield 'tréma' => ['Noël', 'noel'];
        yield 'espaces multiples' => ['Pâté   de  foie', 'pate de foie'];
        yield 'espaces en bordure' => ['  Brunch  ', 'brunch'];
        yield 'déjà normalisé' => ['pate de foie', 'pate de foie'];
        yield 'chiffres conservés' => ['Menu 2026', 'menu 2026'];
    }

    public function testUnTexteVideDonneUneChaineVide(): void
    {
        self::assertSame('', Normalisateur::pourRecherche(null));
        self::assertSame('', Normalisateur::pourRecherche(''));
        self::assertSame('', Normalisateur::pourRecherche('   '));
    }

    public function testLaNormalisationEstIdempotente(): void
    {
        $une = Normalisateur::pourRecherche('Crème brûlée à la Châtaigne');

        // Relancer le normalisateur sur sa propre sortie ne doit rien changer,
        // sinon app:normaliser-menus donnerait un résultat différent à chaque
        // exécution.
        self::assertSame($une, Normalisateur::pourRecherche($une));
    }
}
