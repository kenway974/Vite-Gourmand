<?php

namespace App\Tests\Functional;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Frontière des droits entre employé et administrateur.
 *
 * Le cahier des charges donne au personnel « les menus, les horaires, les
 * commandes et les avis », et à l'administrateur « la création des comptes
 * employés et les statistiques ». La tarification de la livraison suit les
 * comptes : elle décide de ce qui est facturé au client.
 */
class DroitsAdministrationTest extends WebTestCase
{
    private const MOT_DE_PASSE = 'Motdepasse&33!';

    /** Sections que le personnel doit pouvoir gérer. */
    private const CATALOGUE = [
        '/admin',
        '/admin/menus',
        '/admin/plats',
        '/admin/ingredients',
        '/admin/allergenes',
        '/admin/themes',
        '/admin/regimes',
        '/admin/horaires',
    ];

    /** Sections réservées à l'administrateur. */
    private const RESERVE_ADMIN = [
        '/admin/utilisateurs',
        '/admin/utilisateurs/nouveau',
        '/admin/zones',
    ];

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

    // --- Employé ----------------------------------------------------------

    #[DataProvider('sectionsDuCatalogue')]
    public function testUnEmployeGereLeCatalogue(string $url): void
    {
        $this->connecter(['ROLE_EMPLOYE']);

        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful($url.' devrait être ouvert au personnel.');
    }

    #[DataProvider('sectionsReserveesALAdmin')]
    public function testUnEmployeNAccedePasAuxSectionsReservees(string $url): void
    {
        $this->connecter(['ROLE_EMPLOYE']);

        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN, $url.' devrait être réservé à l\'admin.');
    }

    public function testLeTableauDeBordNeMontrePasAUnEmployeCeQuIlNePeutPasOuvrir(): void
    {
        $this->connecter(['ROLE_EMPLOYE']);

        $crawler = $this->client->request('GET', '/admin');
        $liens = $crawler->filter('a')->each(fn ($n) => $n->attr('href'));

        // Un lien vers une page interdite ne fait que promettre un 403.
        self::assertNotContains('/admin/utilisateurs', $liens);
        self::assertNotContains('/admin/zones', $liens);
        self::assertContains('/admin/menus', $liens);
        self::assertContains('/admin/horaires', $liens);
    }

    // --- Administrateur ---------------------------------------------------

    #[DataProvider('toutesLesSections')]
    public function testUnAdministrateurAccedeATout(string $url): void
    {
        $this->connecter(['ROLE_ADMIN']);

        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful($url.' devrait être ouvert à l\'admin.');
    }

    public function testUnAdministrateurCreeUnCompteEmploye(): void
    {
        $this->connecter(['ROLE_ADMIN']);

        $this->client->request('GET', '/admin/utilisateurs/nouveau');
        $this->client->submitForm('Créer le compte', [
            'compte_interne[prenom]' => 'Marie',
            'compte_interne[nom]' => 'Lasserre',
            'compte_interne[email]' => 'marie@vite-gourmand.fr',
            'compte_interne[role]' => 'ROLE_EMPLOYE',
            'compte_interne[plainPassword]' => 'ProvisoireEmploye&33!',
        ]);

        self::assertResponseRedirects('/admin/utilisateurs');

        $this->em->clear();
        $cree = $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => 'marie@vite-gourmand.fr']);

        self::assertNotNull($cree);
        self::assertContains('ROLE_EMPLOYE', $cree->getRoles());
        self::assertNotContains('ROLE_ADMIN', $cree->getRoles());
        self::assertTrue($cree->isActif());
        self::assertNotSame('ProvisoireEmploye&33!', $cree->getPassword(), 'Le mot de passe doit être haché.');
    }

    public function testUnMotDePasseFaibleEstRefuseALaCreation(): void
    {
        $this->connecter(['ROLE_ADMIN']);

        $this->client->request('GET', '/admin/utilisateurs/nouveau');
        $this->client->submitForm('Créer le compte', [
            'compte_interne[prenom]' => 'Marie',
            'compte_interne[nom]' => 'Lasserre',
            'compte_interne[email]' => 'marie@vite-gourmand.fr',
            'compte_interne[role]' => 'ROLE_EMPLOYE',
            'compte_interne[plainPassword]' => 'motdepasse',
        ]);

        // Même politique que l'inscription publique : un compte interne ne
        // doit pas être plus faible qu'un compte client.
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(1, $this->em->getRepository(Utilisateur::class)->count([]));
    }

    // --- Client -----------------------------------------------------------

    #[DataProvider('toutesLesSections')]
    public function testUnClientNAccedeARien(string $url): void
    {
        $this->connecter([]);

        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN, $url.' devrait rester fermé à un client.');
    }

    public function testUnVisiteurAnonymeEstRenvoyeVersLaConnexion(): void
    {
        $this->client->request('GET', '/admin/menus');

        self::assertResponseRedirects();
        self::assertStringContainsString('/connexion', $this->client->getResponse()->headers->get('Location') ?? '');
    }

    // --- Jeux de données --------------------------------------------------

    public static function sectionsDuCatalogue(): iterable
    {
        foreach (self::CATALOGUE as $url) {
            yield $url => [$url];
        }
    }

    public static function sectionsReserveesALAdmin(): iterable
    {
        foreach (self::RESERVE_ADMIN as $url) {
            yield $url => [$url];
        }
    }

    public static function toutesLesSections(): iterable
    {
        foreach ([...self::CATALOGUE, ...self::RESERVE_ADMIN] as $url) {
            yield $url => [$url];
        }
    }

    // --- Fixtures ---------------------------------------------------------

    private function connecter(array $roles): void
    {
        $u = new Utilisateur();
        $u->setEmail('personne@example.com')->setNom('T')->setPrenom('U')
            ->setRoles($roles)->setActif(true);
        $u->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)
            ->hashPassword($u, self::MOT_DE_PASSE));
        $this->em->persist($u);
        $this->em->flush();

        $this->client->request('GET', '/connexion');
        $this->client->submitForm('Se connecter', [
            'email' => 'personne@example.com',
            'password' => self::MOT_DE_PASSE,
        ]);
        $this->client->followRedirect();
    }
}
