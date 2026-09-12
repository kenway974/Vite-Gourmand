<?php

namespace App\Tests\Functional;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Parcours complet d'inscription et de connexion.
 */
class AuthentificationTest extends WebTestCase
{
    private const MOT_DE_PASSE_VALIDE = 'Vite&Gourmand974!';

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        // Base repartie de zero avant chaque test.
        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testLaPageDInscriptionEstAccessibleAnonymement(): void
    {
        $this->client->request('GET', '/inscription');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Créer un compte');
    }

    public function testLInscriptionCreeUnCompteActifAvecMotDePasseHache(): void
    {
        $this->client->request('GET', '/inscription');

        $this->client->submitForm('Créer mon compte', [
            'registration_form[prenom]' => 'Kenny',
            'registration_form[nom]' => 'Pignolet',
            'registration_form[email]' => 'kenny@example.com',
            'registration_form[plainPassword][first]' => self::MOT_DE_PASSE_VALIDE,
            'registration_form[plainPassword][second]' => self::MOT_DE_PASSE_VALIDE,
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseRedirects('/connexion');

        $utilisateur = $this->em->getRepository(Utilisateur::class)
            ->findOneBy(['email' => 'kenny@example.com']);

        self::assertNotNull($utilisateur, "Le compte n'a pas été enregistré.");
        self::assertTrue($utilisateur->isActif(), 'Un nouveau compte doit être actif.');
        self::assertSame(['ROLE_USER'], $utilisateur->getRoles());

        // Le mot de passe ne doit jamais être stocké en clair.
        self::assertNotSame(self::MOT_DE_PASSE_VALIDE, $utilisateur->getPassword());
        self::assertTrue(
            static::getContainer()->get(UserPasswordHasherInterface::class)
                ->isPasswordValid($utilisateur, self::MOT_DE_PASSE_VALIDE)
        );
    }

    public function testUnMotDePasseTropFaibleEstRefuse(): void
    {
        $this->client->request('GET', '/inscription');

        $this->client->submitForm('Créer mon compte', [
            'registration_form[prenom]' => 'Kenny',
            'registration_form[nom]' => 'Pignolet',
            'registration_form[email]' => 'faible@example.com',
            'registration_form[plainPassword][first]' => 'azerty',
            'registration_form[plainPassword][second]' => 'azerty',
            'registration_form[agreeTerms]' => true,
        ]);

        // Le formulaire est réaffiché (200) et aucun compte n'est créé.
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertNull(
            $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => 'faible@example.com'])
        );
    }

    public function testLesCgvDoiventEtreAcceptees(): void
    {
        $this->client->request('GET', '/inscription');

        $this->client->submitForm('Créer mon compte', [
            'registration_form[prenom]' => 'Kenny',
            'registration_form[nom]' => 'Pignolet',
            'registration_form[email]' => 'sanscgv@example.com',
            'registration_form[plainPassword][first]' => self::MOT_DE_PASSE_VALIDE,
            'registration_form[plainPassword][second]' => self::MOT_DE_PASSE_VALIDE,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertNull(
            $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => 'sanscgv@example.com'])
        );
    }

    public function testUnUtilisateurActifPeutSeConnecter(): void
    {
        $this->creerUtilisateur('actif@example.com', actif: true);

        $this->client->request('GET', '/connexion');
        $this->client->submitForm('Se connecter', [
            'email' => 'actif@example.com',
            'password' => self::MOT_DE_PASSE_VALIDE,
        ]);

        self::assertResponseRedirects('/');
        $this->client->followRedirect();

        // La page protégée est maintenant accessible.
        $this->client->request('GET', '/mon-compte');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mon compte');
    }

    public function testUnCompteDesactiveNePeutPasSeConnecter(): void
    {
        $this->creerUtilisateur('inactif@example.com', actif: false);

        $this->client->request('GET', '/connexion');
        $this->client->submitForm('Se connecter', [
            'email' => 'inactif@example.com',
            'password' => self::MOT_DE_PASSE_VALIDE,
        ]);

        // Rejeté par UtilisateurChecker, malgré des identifiants corrects.
        self::assertResponseRedirects('/connexion');
        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('désactivé', $crawler->filter('.erreur')->text());

        $this->client->request('GET', '/mon-compte');
        self::assertResponseRedirects();
    }

    public function testMonCompteEstInterditAuxVisiteursAnonymes(): void
    {
        $this->client->request('GET', '/mon-compte');

        self::assertResponseRedirects();
        self::assertStringContainsString(
            '/connexion',
            $this->client->getResponse()->headers->get('Location') ?? ''
        );
    }

    public function testLaDeconnexionTermineLaSession(): void
    {
        $this->creerUtilisateur('deco@example.com', actif: true);

        $this->client->request('GET', '/connexion');
        $this->client->submitForm('Se connecter', [
            'email' => 'deco@example.com',
            'password' => self::MOT_DE_PASSE_VALIDE,
        ]);
        $this->client->followRedirect();

        $this->client->request('GET', '/deconnexion');
        self::assertResponseRedirects('/');

        // Une fois déconnecté, la page protégée renvoie vers la connexion.
        $this->client->request('GET', '/mon-compte');
        self::assertResponseRedirects();
        self::assertStringContainsString(
            '/connexion',
            $this->client->getResponse()->headers->get('Location') ?? ''
        );
    }

    private function creerUtilisateur(string $email, bool $actif): Utilisateur
    {
        $utilisateur = new Utilisateur();
        $utilisateur->setEmail($email);
        $utilisateur->setNom('Test');
        $utilisateur->setPrenom('Utilisateur');
        $utilisateur->setRoles([]);
        $utilisateur->setActif($actif);
        $utilisateur->setPassword(
            static::getContainer()->get(UserPasswordHasherInterface::class)
                ->hashPassword($utilisateur, self::MOT_DE_PASSE_VALIDE)
        );

        $this->em->persist($utilisateur);
        $this->em->flush();

        return $utilisateur;
    }
}
