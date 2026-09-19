<?php

namespace App\Tests\Functional;

use App\Entity\Horaire;
use App\Entity\Menu;
use App\Entity\Utilisateur;
use App\Entity\ZoneLivraison;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Commande de chargement du catalogue de démonstration en production.
 *
 * Contrairement à app:creer-admin, elle ne prend aucun argument sensible :
 * l'essentiel à vérifier est qu'elle peuple le catalogue sans toucher aux
 * comptes utilisateur, et qu'elle refuse de dupliquer sur un second lancement.
 */
class ChargerCatalogueDemoCommandTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private CommandTester $commande;

    protected function setUp(): void
    {
        static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $application = new Application(static::$kernel);
        $this->commande = new CommandTester($application->find('app:charger-catalogue-demo'));
    }

    public function testEllePeupleLeCatalogueSansAucunCompteUtilisateur(): void
    {
        $code = $this->commande->execute([], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $code);

        self::assertGreaterThan(0, $this->em->getRepository(Menu::class)->count([]));
        self::assertGreaterThan(0, $this->em->getRepository(Horaire::class)->count([]));
        self::assertGreaterThan(0, $this->em->getRepository(ZoneLivraison::class)->count([]));

        // Le point qui justifie l'existence de cette commande plutôt que les
        // fixtures complètes ou sql/jeu-de-donnees.sql : aucun compte créé,
        // donc aucun mot de passe de démonstration en production.
        self::assertSame(0, $this->em->getRepository(Utilisateur::class)->count([]));
    }

    public function testElleRefuseDeRechargerSurUnCatalogueDejaPresent(): void
    {
        self::assertSame(Command::SUCCESS, $this->commande->execute([], ['interactive' => false]));

        $nombreDeMenus = $this->em->getRepository(Menu::class)->count([]);

        $application = new Application(static::$kernel);
        $secondLancement = new CommandTester($application->find('app:charger-catalogue-demo'));
        $code = $secondLancement->execute([], ['interactive' => false]);

        self::assertSame(Command::FAILURE, $code);
        self::assertStringContainsString('existent déjà', $secondLancement->getDisplay());

        // Aucun menu en double : le second lancement n'a rien écrit.
        self::assertSame($nombreDeMenus, $this->em->getRepository(Menu::class)->count([]));
    }
}
