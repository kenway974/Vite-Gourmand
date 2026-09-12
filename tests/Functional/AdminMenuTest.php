<?php

namespace App\Tests\Functional;

use App\Entity\Commande;
use App\Entity\Menu;
use App\Entity\Plat;
use App\Entity\Regime;
use App\Entity\Theme;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Espace d'administration et gestion des menus.
 */
class AdminMenuTest extends WebTestCase
{
    private const MOT_DE_PASSE = 'Admin&Gourmand974!';

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private int $themeId;
    private int $regimeId;
    private int $platId;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $theme = (new Theme())->setLibelle('Créole')->setDescription('Cuisine réunionnaise');
        $regime = (new Regime())->setLibelle('Sans gluten')->setDescription('Sans gluten');
        $plat = (new Plat())->setNom('Rougail saucisse')->setType('Plat')->setDescription('Spécialité locale');

        $this->em->persist($theme);
        $this->em->persist($regime);
        $this->em->persist($plat);
        $this->em->flush();

        // On ne garde que les identifiants : le client de test réinitialise les
        // services entre deux requêtes HTTP, ce qui détacherait ces objets.
        $this->themeId = $theme->getId();
        $this->regimeId = $regime->getId();
        $this->platId = $plat->getId();
    }

    // --- Contrôle d'accès -------------------------------------------------

    public function testUnVisiteurAnonymeEstRenvoyeVersLaConnexion(): void
    {
        $this->client->request('GET', '/admin');

        self::assertResponseRedirects();
        self::assertStringContainsString(
            '/connexion',
            $this->client->getResponse()->headers->get('Location') ?? ''
        );
    }

    public function testUnClientNePeutPasEntrerDansLAdministration(): void
    {
        $this->connecter($this->creerUtilisateur('client@example.com', ['ROLE_USER']));

        $this->client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->client->request('GET', '/admin/menus');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testUnAdministrateurAccedeAuTableauDeBord(): void
    {
        $this->connecter($this->creerUtilisateur('admin@example.com', ['ROLE_ADMIN']));

        $this->client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Administration');
    }

    // --- Création ---------------------------------------------------------

    public function testUnAdministrateurCreeUnMenuAvecSesPlats(): void
    {
        $this->connecter($this->creerUtilisateur('admin@example.com', ['ROLE_ADMIN']));

        $this->client->request('GET', '/admin/menus/nouveau');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Créer le menu', [
            'menu[titre]' => 'Menu créole',
            'menu[description]' => 'Un menu aux saveurs de La Réunion.',
            'menu[theme]' => (string) $this->themeId,
            'menu[regime]' => (string) $this->regimeId,
            'menu[nbMinPersonnes]' => '6',
            'menu[prixMin]' => '18.50',
            'menu[delaiCommandeJours]' => '3',
            'menu[stock]' => '10',
            'menu[plats]' => [(string) $this->platId],
        ]);

        self::assertResponseRedirects('/admin/menus');

        $this->em->clear();
        $menu = $this->em->getRepository(Menu::class)->findOneBy(['titre' => 'Menu créole']);

        self::assertNotNull($menu);
        // Comparaison numérique : SQLite rend '18.5' là où Postgres rend '18.50'.
        self::assertEqualsWithDelta(18.50, (float) $menu->getPrixMin(), 0.001);
        self::assertSame(6, $menu->getNbMinPersonnes());

        // Le piège : `plats` est le côté inverse du ManyToMany. Sans
        // by_reference: false, cette association serait perdue silencieusement.
        self::assertCount(1, $menu->getPlats(), "Les plats n'ont pas été rattachés au menu.");
        self::assertSame('Rougail saucisse', $menu->getPlats()->first()->getNom());
    }

    public function testUnMenuAuPrixNegatifEstRefuse(): void
    {
        $this->connecter($this->creerUtilisateur('admin@example.com', ['ROLE_ADMIN']));

        $this->client->request('GET', '/admin/menus/nouveau');
        $this->client->submitForm('Créer le menu', [
            'menu[titre]' => 'Menu invalide',
            'menu[description]' => 'Test',
            'menu[theme]' => (string) $this->themeId,
            'menu[regime]' => (string) $this->regimeId,
            'menu[nbMinPersonnes]' => '6',
            'menu[prixMin]' => '-5',
            'menu[delaiCommandeJours]' => '3',
            'menu[stock]' => '10',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertNull($this->em->getRepository(Menu::class)->findOneBy(['titre' => 'Menu invalide']));
    }

    // --- Modification -----------------------------------------------------

    public function testUnAdministrateurModifieUnMenu(): void
    {
        $this->connecter($this->creerUtilisateur('admin@example.com', ['ROLE_ADMIN']));
        $menu = $this->creerMenu('Menu à corriger');

        $this->client->request('GET', '/admin/menus/'.$menu->getId().'/modifier');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Enregistrer les modifications', [
            'menu[titre]' => 'Menu corrigé',
            'menu[stock]' => '42',
        ]);

        self::assertResponseRedirects('/admin/menus');

        $this->em->clear();
        $modifie = $this->em->getRepository(Menu::class)->find($menu->getId());

        self::assertSame('Menu corrigé', $modifie->getTitre());
        self::assertSame(42, $modifie->getStock());
    }

    // --- Suppression ------------------------------------------------------

    public function testUnAdministrateurSupprimeUnMenu(): void
    {
        $this->connecter($this->creerUtilisateur('admin@example.com', ['ROLE_ADMIN']));
        $menu = $this->creerMenu('Menu à supprimer');
        $id = $menu->getId();

        $crawler = $this->client->request('GET', '/admin/menus');
        $this->client->submit($crawler->filter('form[action$="/supprimer"] button')->form());

        self::assertResponseRedirects('/admin/menus');

        $this->em->clear();
        self::assertNull($this->em->getRepository(Menu::class)->find($id));
    }

    public function testLaSuppressionSansJetonCsrfEstRefusee(): void
    {
        $this->connecter($this->creerUtilisateur('admin@example.com', ['ROLE_ADMIN']));
        $menu = $this->creerMenu('Menu protégé');
        $id = $menu->getId();

        $this->client->request('POST', '/admin/menus/'.$id.'/supprimer');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->em->clear();
        self::assertNotNull($this->em->getRepository(Menu::class)->find($id));
    }

    public function testUnMenuDejaCommandeNePeutPasEtreSupprime(): void
    {
        $admin = $this->creerUtilisateur('admin@example.com', ['ROLE_ADMIN']);
        $adminId = $admin->getId();
        $this->connecter($admin);

        $menu = $this->creerMenu('Menu commandé');
        $id = $menu->getId();
        $this->creerCommande($adminId, $menu);

        $crawler = $this->client->request('GET', '/admin/menus');
        $this->client->submit($crawler->filter('form[action$="/supprimer"] button')->form());

        self::assertResponseRedirects('/admin/menus');
        $crawler = $this->client->followRedirect();

        // Le menu est toujours là et l'administrateur est prévenu.
        self::assertStringContainsString('ne peut pas être supprimé', $crawler->filter('.flash')->text());

        $this->em->clear();
        self::assertNotNull($this->em->getRepository(Menu::class)->find($id));
    }

    // --- Fixtures ---------------------------------------------------------

    private function creerUtilisateur(string $email, array $roles): Utilisateur
    {
        $utilisateur = new Utilisateur();
        $utilisateur->setEmail($email);
        $utilisateur->setNom('Test');
        $utilisateur->setPrenom('Utilisateur');
        $utilisateur->setRoles($roles);
        $utilisateur->setActif(true);
        $utilisateur->setPassword(
            static::getContainer()->get(UserPasswordHasherInterface::class)
                ->hashPassword($utilisateur, self::MOT_DE_PASSE)
        );

        $this->em->persist($utilisateur);
        $this->em->flush();

        return $utilisateur;
    }

    private function connecter(Utilisateur $utilisateur): void
    {
        $this->client->request('GET', '/connexion');
        $this->client->submitForm('Se connecter', [
            'email' => $utilisateur->getEmail(),
            'password' => self::MOT_DE_PASSE,
        ]);
        $this->client->followRedirect();
    }

    private function creerMenu(string $titre): Menu
    {
        $menu = new Menu();
        $menu->setTitre($titre);
        $menu->setDescription('Description de test.');
        $menu->setTheme($this->em->getRepository(Theme::class)->find($this->themeId));
        $menu->setRegime($this->em->getRepository(Regime::class)->find($this->regimeId));
        $menu->setNbMinPersonnes(4);
        $menu->setPrixMin('20.00');
        $menu->setDelaiCommandeJours(2);
        $menu->setStock(5);

        $this->em->persist($menu);
        $this->em->flush();

        return $menu;
    }

    private function creerCommande(int $utilisateurId, Menu $menu): Commande
    {
        $commande = new Commande();
        // Rechargé plutôt que réutilisé : l'objet d'origine a été détaché par
        // les requêtes HTTP de la connexion.
        $commande->setUtilisateur($this->em->getRepository(Utilisateur::class)->find($utilisateurId));
        $commande->setMenu($menu);
        $commande->setDateCommande(new \DateTime());
        $commande->setDatePrestation(new \DateTime('+7 days'));
        $commande->setHeureLivraison(new \DateTime('12:00'));
        $commande->setLieuLivraison('12 rue des Lilas, Saint-Denis');
        $commande->setNbPersonnes(8);
        $commande->setPrixTotal('160.00');
        $commande->setStatut('en attente');
        $commande->setPretMateriel(false);

        $this->em->persist($commande);
        $this->em->flush();

        return $commande;
    }
}
