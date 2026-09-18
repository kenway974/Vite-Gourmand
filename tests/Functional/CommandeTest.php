<?php

namespace App\Tests\Functional;

use App\Entity\Commande;
use App\Entity\Menu;
use App\Entity\Regime;
use App\Entity\SuiviCommande;
use App\Entity\Theme;
use App\Entity\Utilisateur;
use App\Entity\ZoneLivraison;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Passage d'une commande.
 */
class CommandeTest extends WebTestCase
{
    private const MOT_DE_PASSE = 'Motdepasse&33!';

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private int $themeId;
    private int $regimeId;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $st = new SchemaTool($this->em);
        $md = $this->em->getMetadataFactory()->getAllMetadata();
        $st->dropSchema($md);
        $st->createSchema($md);

        $theme = (new Theme())->setLibelle('Bistrot');
        $regime = (new Regime())->setLibelle('Standard');
        $this->em->persist($theme);
        $this->em->persist($regime);
        $this->em->flush();
        $this->themeId = $theme->getId();
        $this->regimeId = $regime->getId();

        // Sans zone desservie, aucune commande ne peut aboutir.
        $this->zone('33000', 'Bordeaux', '0.00');
    }

    // --- Accès ------------------------------------------------------------

    public function testUnVisiteurAnonymeEstRenvoyeVersLaConnexion(): void
    {
        $menu = $this->menu();

        $this->client->request('GET', '/commander/'.$menu->getId());

        self::assertResponseRedirects();
        self::assertStringContainsString('/connexion', $this->client->getResponse()->headers->get('Location') ?? '');
    }

    public function testUnClientConnecteAccedeAuFormulaire(): void
    {
        $menu = $this->menu();
        $this->connecter($this->client('client@example.com'));

        $this->client->request('GET', '/commander/'.$menu->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $menu->getTitre());
    }

    // --- Cas nominal ------------------------------------------------------

    public function testUneCommandeValideEstEnregistreeAvecSonPrix(): void
    {
        $menu = $this->menu(prix: '24.00', minimum: 6, delai: 3, stock: 5);
        $this->connecter($this->client('client@example.com'));

        $this->soumettre($menu, convives: 8, dans: 10);

        self::assertResponseRedirects();

        $this->em->clear();
        $commande = $this->em->getRepository(Commande::class)->findOneBy([]);

        self::assertNotNull($commande);
        self::assertSame(Commande::EN_ATTENTE, $commande->getStatut());
        self::assertSame(8, $commande->getNbPersonnes());
        // Comparaison numérique : SQLite rend '192' là où MySQL rend '192.00'.
        self::assertEqualsWithDelta(192.00, (float) $commande->getPrixTotal(), 0.001);
        self::assertEqualsWithDelta(0.0, (float) $commande->getMontantRemise(), 0.001);
    }

    public function testLaRemiseEstAppliqueeEtConservee(): void
    {
        // Minimum 6 donc seuil à 11 convives.
        $menu = $this->menu(prix: '24.00', minimum: 6, delai: 3, stock: 5);
        $this->connecter($this->client('client@example.com'));

        $this->soumettre($menu, convives: 11, dans: 10);

        $this->em->clear();
        $commande = $this->em->getRepository(Commande::class)->findOneBy([]);

        self::assertEqualsWithDelta(237.60, (float) $commande->getPrixTotal(), 0.001);
        self::assertEqualsWithDelta(26.40, (float) $commande->getMontantRemise(), 0.001);
        self::assertEqualsWithDelta(10.0, (float) $commande->getTauxRemise(), 0.001);
    }

    public function testUnPremierSuiviEstCreeEtLeStockDecremente(): void
    {
        $menu = $this->menu(stock: 5);
        $id = $menu->getId();
        $this->connecter($this->client('client@example.com'));

        $this->soumettre($menu, convives: 6, dans: 10);

        $this->em->clear();

        $suivis = $this->em->getRepository(SuiviCommande::class)->findAll();
        self::assertCount(1, $suivis);
        self::assertSame(Commande::EN_ATTENTE, $suivis[0]->getStatut());

        self::assertSame(4, $this->em->getRepository(Menu::class)->find($id)->getStock());
    }

    // --- Refus ------------------------------------------------------------

    public function testUnEffectifInferieurAuMinimumEstRefuse(): void
    {
        $menu = $this->menu(minimum: 6);
        $this->connecter($this->client('client@example.com'));

        $this->soumettre($menu, convives: 4, dans: 10);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(0, $this->em->getRepository(Commande::class)->count([]));
    }

    public function testUneDateTropProcheEstRefusee(): void
    {
        // Le délai d'approvisionnement n'est pas négociable.
        $menu = $this->menu(minimum: 6, delai: 7);
        $this->connecter($this->client('client@example.com'));

        $this->soumettre($menu, convives: 6, dans: 2);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(0, $this->em->getRepository(Commande::class)->count([]));
    }

    public function testUnMenuEpuiseNePeutPasEtreCommande(): void
    {
        $menu = $this->menu(stock: 0);
        $this->connecter($this->client('client@example.com'));

        $this->soumettre($menu, convives: 6, dans: 10);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(0, $this->em->getRepository(Commande::class)->count([]));
    }

    public function testUneDateHorsPeriodeDuMenuEstRefusee(): void
    {
        // Menu de saison : la date demandée tombe avant son ouverture.
        $menu = $this->menu(minimum: 6, delai: 2, debut: '+60 days', fin: '+90 days');
        $this->connecter($this->client('client@example.com'));

        $this->soumettre($menu, convives: 6, dans: 10);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(0, $this->em->getRepository(Commande::class)->count([]));
    }

    // --- Cloisonnement ----------------------------------------------------

    public function testUnClientNeVoitPasLaConfirmationDUnAutre(): void
    {
        $menu = $this->menu(stock: 5);

        // Les deux comptes sont créés avant toute navigation : le redémarrage
        // du navigateur plus bas rendrait l'EntityManager inutilisable ensuite.
        $premier = $this->client('premier@example.com');
        $this->client('second@example.com');

        $this->connecter($premier);
        $this->soumettre($menu, convives: 6, dans: 10);

        $this->em->clear();
        $id = $this->em->getRepository(Commande::class)->findOneBy([])->getId();

        // Session vierge : sinon /connexion redirige le client déjà connecté.
        $this->client->restart();
        $this->connecterAvec('second@example.com');

        $this->client->request('GET', '/commande/'.$id.'/confirmation');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // --- Fixtures ---------------------------------------------------------

    private function soumettre(Menu $menu, int $convives, int $dans, string $codePostal = '33000'): void
    {
        $this->client->request('GET', '/commander/'.$menu->getId());
        $this->client->submitForm('Envoyer ma demande', [
            'commande[datePrestation]' => (new \DateTime(sprintf('+%d days', $dans)))->format('Y-m-d'),
            'commande[heureLivraison]' => '12:00',
            'commande[lieuLivraison]' => '24 rue Notre-Dame, Bordeaux',
            'commande[codePostalLivraison]' => $codePostal,
            'commande[nbPersonnes]' => (string) $convives,
        ]);
    }

    private function zone(string $codePostal, string $commune, string $frais): ZoneLivraison
    {
        $z = (new ZoneLivraison())
            ->setCodePostal($codePostal)
            ->setCommune($commune)
            ->setFrais($frais);

        $this->em->persist($z);
        $this->em->flush();

        return $z;
    }

    private function client(string $email): Utilisateur
    {
        $u = new Utilisateur();
        $u->setEmail($email)->setNom('Test')->setPrenom('Client')->setRoles([])->setActif(true);
        $u->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)
            ->hashPassword($u, self::MOT_DE_PASSE));
        $this->em->persist($u);
        $this->em->flush();

        return $u;
    }

    private function connecter(Utilisateur $u): void
    {
        $this->connecterAvec($u->getEmail());
    }

    private function connecterAvec(string $email): void
    {
        $this->client->request('GET', '/connexion');
        $this->client->submitForm('Se connecter', ['email' => $email, 'password' => self::MOT_DE_PASSE]);
        $this->client->followRedirect();
    }

    private function menu(
        string $prix = '24.00',
        int $minimum = 6,
        int $delai = 3,
        int $stock = 10,
        ?string $debut = null,
        ?string $fin = null,
    ): Menu {
        $m = new Menu();
        $m->setTitre('Menu de test')->setDescription('Description.')
            ->setTheme($this->em->getRepository(Theme::class)->find($this->themeId))
            ->setRegime($this->em->getRepository(Regime::class)->find($this->regimeId))
            ->setNbMinPersonnes($minimum)->setPrixMin($prix)
            ->setDelaiCommandeJours($delai)->setStock($stock)
            ->setDateDebut($debut ? new \DateTime($debut) : null)
            ->setDateFin($fin ? new \DateTime($fin) : null);
        $this->em->persist($m);
        $this->em->flush();

        return $m;
    }
}
