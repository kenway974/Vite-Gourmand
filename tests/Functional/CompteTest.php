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
 * Gestion de son propre compte : coordonnées et mot de passe.
 */
class CompteTest extends WebTestCase
{
    private const MOT_DE_PASSE = 'Motdepasse&33!';
    private const NOUVEAU = 'NouveauMotdepasse&33!';

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $st = new SchemaTool($this->em);
        $md = $this->em->getMetadataFactory()->getAllMetadata();
        $st->dropSchema($md);
        $st->createSchema($md);
    }

    // --- Accès ------------------------------------------------------------

    public function testLeCompteEstFermeAuxVisiteursAnonymes(): void
    {
        foreach (['', '/modifier', '/mot-de-passe'] as $page) {
            $this->client->request('GET', '/mon-compte'.$page);

            self::assertResponseRedirects();
            self::assertStringContainsString(
                '/connexion',
                $this->client->getResponse()->headers->get('Location') ?? '',
                'Page /mon-compte'.$page,
            );
        }
    }

    // --- Coordonnées ------------------------------------------------------

    public function testUnClientMetAJourSesCoordonnees(): void
    {
        $id = $this->utilisateur('client@example.com')->getId();
        $this->connecter('client@example.com');

        $this->client->request('GET', '/mon-compte/modifier');
        $this->client->submitForm('Enregistrer', [
            'profil[prenom]' => 'Camille',
            'profil[nom]' => 'Lartigue',
            'profil[email]' => 'client@example.com',
            'profil[gsm]' => '0556000000',
            'profil[adressePostale]' => '24 rue Notre-Dame, 33000 Bordeaux',
        ]);

        self::assertResponseRedirects('/mon-compte');

        $this->em->clear();
        $client = $this->em->getRepository(Utilisateur::class)->find($id);

        self::assertSame('Camille', $client->getPrenom());
        self::assertSame('0556000000', $client->getGsm());
        self::assertSame('24 rue Notre-Dame, 33000 Bordeaux', $client->getAdressePostale());
    }

    public function testUneAdresseDejaPriseParUnAutreCompteEstRefusee(): void
    {
        $this->utilisateur('voisin@example.com');
        $this->utilisateur('client@example.com');
        $this->connecter('client@example.com');

        $this->client->request('GET', '/mon-compte/modifier');
        $this->client->submitForm('Enregistrer', [
            'profil[prenom]' => 'Camille',
            'profil[nom]' => 'Lartigue',
            'profil[email]' => 'voisin@example.com',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('body', 'Un compte existe déjà avec cette adresse');
    }

    public function testUnClientNePeutPasSeDonnerUnRole(): void
    {
        $id = $this->utilisateur('client@example.com')->getId();
        $this->connecter('client@example.com');

        // Le formulaire n'expose ni rôle ni activation : un champ ajouté à la
        // main est ignoré par Symfony, il n'est pas dans la définition.
        $this->client->request('GET', '/mon-compte/modifier');
        $this->client->request('POST', '/mon-compte/modifier', [
            'profil' => [
                'prenom' => 'Camille',
                'nom' => 'Lartigue',
                'email' => 'client@example.com',
                'roles' => ['ROLE_ADMIN'],
                'actif' => '1',
            ],
        ]);

        $this->em->clear();
        $client = $this->em->getRepository(Utilisateur::class)->find($id);

        self::assertSame(['ROLE_USER'], $client->getRoles());
    }

    // --- Mot de passe -----------------------------------------------------

    public function testUnClientChangeSonMotDePasse(): void
    {
        $this->utilisateur('client@example.com');
        $this->connecter('client@example.com');

        $this->client->request('GET', '/mon-compte/mot-de-passe');
        $this->client->submitForm('Changer le mot de passe', [
            'changement_mot_de_passe[actuel]' => self::MOT_DE_PASSE,
            'changement_mot_de_passe[nouveau][first]' => self::NOUVEAU,
            'changement_mot_de_passe[nouveau][second]' => self::NOUVEAU,
        ]);

        self::assertResponseRedirects('/mon-compte');

        // Le nouveau mot de passe ouvre bien la session.
        $this->deconnecter();
        self::assertTrue($this->connexionReussie('client@example.com', self::NOUVEAU));

        // L'ancien est vérifié contre le hachage, pas par une tentative de
        // connexion : un échec consomme un essai du login_throttling, dont le
        // compteur est partagé par toute la suite de tests.
        self::assertFalse($this->motDePasseValide('client@example.com', self::MOT_DE_PASSE));
    }

    public function testLeMotDePasseActuelEstExige(): void
    {
        $this->utilisateur('client@example.com');
        $this->connecter('client@example.com');

        $this->client->request('GET', '/mon-compte/mot-de-passe');
        $this->client->submitForm('Changer le mot de passe', [
            'changement_mot_de_passe[actuel]' => 'CeNestPasLeBon&33!',
            'changement_mot_de_passe[nouveau][first]' => self::NOUVEAU,
            'changement_mot_de_passe[nouveau][second]' => self::NOUVEAU,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('body', 'ne correspond pas à votre mot de passe actuel');

        // Le mot de passe d'origine est resté en place.
        self::assertTrue($this->motDePasseValide('client@example.com', self::MOT_DE_PASSE));
        self::assertFalse($this->motDePasseValide('client@example.com', self::NOUVEAU));
    }

    public function testUnNouveauMotDePasseFaibleEstRefuse(): void
    {
        $this->utilisateur('client@example.com');
        $this->connecter('client@example.com');

        $this->client->request('GET', '/mon-compte/mot-de-passe');
        $this->client->submitForm('Changer le mot de passe', [
            'changement_mot_de_passe[actuel]' => self::MOT_DE_PASSE,
            'changement_mot_de_passe[nouveau][first]' => 'motdepasse',
            'changement_mot_de_passe[nouveau][second]' => 'motdepasse',
        ]);

        // Même politique qu'à l'inscription : un compte ne doit pas pouvoir
        // s'affaiblir après coup.
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testLesDeuxSaisiesDoiventCorrespondre(): void
    {
        $this->utilisateur('client@example.com');
        $this->connecter('client@example.com');

        $this->client->request('GET', '/mon-compte/mot-de-passe');
        $this->client->submitForm('Changer le mot de passe', [
            'changement_mot_de_passe[actuel]' => self::MOT_DE_PASSE,
            'changement_mot_de_passe[nouveau][first]' => self::NOUVEAU,
            'changement_mot_de_passe[nouveau][second]' => 'AutreChose&33!',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('body', 'doivent être identiques');
    }

    // --- Fixtures ---------------------------------------------------------

    private function utilisateur(string $email): Utilisateur
    {
        $u = new Utilisateur();
        $u->setEmail($email)->setNom('Lartigue')->setPrenom('Camille')->setRoles([])->setActif(true);
        $u->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)
            ->hashPassword($u, self::MOT_DE_PASSE));
        $this->em->persist($u);
        $this->em->flush();

        return $u;
    }

    private function connecter(string $email): void
    {
        $this->client->request('GET', '/connexion');
        $this->client->submitForm('Se connecter', ['email' => $email, 'password' => self::MOT_DE_PASSE]);
        $this->client->followRedirect();
    }

    private function deconnecter(): void
    {
        // Le cookie plutôt que la route /deconnexion : celle-ci exige un jeton
        // CSRF qu'on ne peut pas forger hors d'une requête, et ce n'est pas la
        // déconnexion qu'on teste ici.
        $this->client->getCookieJar()->clear();
    }

    private function motDePasseValide(string $email, string $motDePasse): bool
    {
        $this->em->clear();
        $utilisateur = $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);

        return static::getContainer()->get(UserPasswordHasherInterface::class)
            ->isPasswordValid($utilisateur, $motDePasse);
    }

    private function connexionReussie(string $email, string $motDePasse): bool
    {
        $this->client->request('GET', '/connexion');
        $this->client->submitForm('Se connecter', ['email' => $email, 'password' => $motDePasse]);

        // Une connexion réussie renvoie ailleurs que sur le formulaire.
        $reussie = !str_contains($this->client->getResponse()->headers->get('Location') ?? '', '/connexion');

        if ($reussie) {
            $this->deconnecter();
        }

        return $reussie;
    }
}
