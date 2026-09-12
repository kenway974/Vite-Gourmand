<?php

namespace App\Tests\Functional;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Commande de création d'un compte administrateur.
 */
class CreerAdminCommandTest extends WebTestCase
{
    private const MOT_DE_PASSE_VALIDE = 'Admin&Gourmand974!';

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private CommandTester $commande;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $application = new Application(static::$kernel);
        $this->commande = new CommandTester($application->find('app:creer-admin'));
    }

    public function testElleCreeUnAdministrateurActif(): void
    {
        $code = $this->executer('admin@example.com');

        self::assertSame(Command::SUCCESS, $code);

        $admin = $this->em->getRepository(Utilisateur::class)
            ->findOneBy(['email' => 'admin@example.com']);

        self::assertNotNull($admin);
        self::assertTrue($admin->isActif());
        self::assertContains('ROLE_ADMIN', $admin->getRoles());
        self::assertNotSame(self::MOT_DE_PASSE_VALIDE, $admin->getPassword());
        self::assertTrue(
            static::getContainer()->get(UserPasswordHasherInterface::class)
                ->isPasswordValid($admin, self::MOT_DE_PASSE_VALIDE)
        );
    }

    public function testLAdministrateurCreePeutSeConnecter(): void
    {
        $this->executer('admin@example.com');

        $this->client->request('GET', '/connexion');
        $this->client->submitForm('Se connecter', [
            'email' => 'admin@example.com',
            'password' => self::MOT_DE_PASSE_VALIDE,
        ]);

        self::assertResponseRedirects('/');

        $this->client->request('GET', '/mon-compte');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('dl', 'ROLE_ADMIN');
    }

    public function testElleRefuseUnMotDePasseTropFaible(): void
    {
        $code = $this->executer('faible@example.com', motDePasse: 'azerty');

        self::assertSame(Command::FAILURE, $code);
        self::assertNull(
            $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => 'faible@example.com'])
        );
    }

    public function testElleRefuseUneAdresseInvalide(): void
    {
        $code = $this->executer('pas-une-adresse');

        self::assertSame(Command::FAILURE, $code);
        self::assertStringContainsString('email', $this->commande->getDisplay());
    }

    public function testElleRefuseUneAdresseDejaUtilisee(): void
    {
        self::assertSame(Command::SUCCESS, $this->executer('doublon@example.com'));

        $code = $this->executer('doublon@example.com');

        self::assertSame(Command::FAILURE, $code);
        self::assertStringContainsString('existe déjà', $this->commande->getDisplay());

        // Un seul compte, le premier : la seconde exécution n'a rien écrasé.
        self::assertCount(
            1,
            $this->em->getRepository(Utilisateur::class)->findBy(['email' => 'doublon@example.com'])
        );
    }

    private function executer(string $email, string $motDePasse = self::MOT_DE_PASSE_VALIDE): int
    {
        $this->em->clear();

        return $this->commande->execute([
            'email' => $email,
            'prenom' => 'Kenny',
            'nom' => 'Pignolet',
            '--mot-de-passe' => $motDePasse,
        ], ['interactive' => false]);
    }
}
