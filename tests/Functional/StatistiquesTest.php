<?php

namespace App\Tests\Functional;

use App\Entity\Commande;
use App\Entity\Menu;
use App\Entity\Regime;
use App\Entity\Theme;
use App\Entity\Utilisateur;
use App\Statistiques\CalculateurStatistiques;
use App\Statistiques\DepotEnMemoire;
use App\Statistiques\DepotStatistiques;
use App\Statistiques\FabriqueDepot;
use App\Statistiques\Instantane;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Statistiques d'activité.
 *
 * Le calcul est éprouvé de bout en bout ; seul l'adaptateur qui parle à
 * MongoDB ne l'est pas, faute d'extension PHP sur cette machine. C'est
 * précisément pour ça qu'il ne contient aucune logique métier.
 */
class StatistiquesTest extends WebTestCase
{
    private const MOT_DE_PASSE = 'Motdepasse&33!';

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private CalculateurStatistiques $calculateur;
    private Theme $theme;
    private Regime $regime;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->calculateur = static::getContainer()->get(CalculateurStatistiques::class);

        $st = new SchemaTool($this->em);
        $md = $this->em->getMetadataFactory()->getAllMetadata();
        $st->dropSchema($md);
        $st->createSchema($md);

        $this->theme = (new Theme())->setLibelle('Bistrot');
        $this->regime = (new Regime())->setLibelle('Standard');
        $this->em->persist($this->theme);
        $this->em->persist($this->regime);
        $this->em->flush();
    }

    // --- Calcul -----------------------------------------------------------

    public function testLeChiffreDAffairesNeCompteQueLeLivre(): void
    {
        $menu = $this->menu('Buffet bordelais');

        $this->commande($menu, '400.00', Commande::LIVREE);
        $this->commande($menu, '250.00', Commande::EN_ATTENTE);
        $this->commande($menu, '999.00', Commande::ANNULEE);

        $stats = $this->calculateur->calculer();

        // Une commande en attente n'est pas un encaissement, une annulée ne le
        // sera jamais. Seules les 400 € livrées comptent.
        self::assertEqualsWithDelta(400.0, (float) $stats->chiffreAffaires, 0.001);
        self::assertSame(1, $stats->nbCommandesLivrees);

        // Le nombre de commandes mesure l'activité : il retient l'attente,
        // mais pas l'annulation.
        self::assertSame(2, $stats->nbCommandes);
    }

    public function testLeChiffreDAffairesEstVentileParMenu(): void
    {
        $buffet = $this->menu('Buffet bordelais');
        $brunch = $this->menu('Brunch du marché');

        $this->commande($buffet, '400.00', Commande::LIVREE);
        $this->commande($buffet, '200.00', Commande::LIVREE);
        $this->commande($brunch, '150.00', Commande::LIVREE);

        $stats = $this->calculateur->calculer();

        self::assertEqualsWithDelta(600.0, (float) $stats->parMenu['Buffet bordelais']['chiffreAffaires'], 0.001);
        self::assertSame(2, $stats->parMenu['Buffet bordelais']['commandes']);
        self::assertEqualsWithDelta(150.0, (float) $stats->parMenu['Brunch du marché']['chiffreAffaires'], 0.001);

        // Le menu qui rapporte le plus arrive en tête : c'est ce qu'on vient voir.
        self::assertSame('Buffet bordelais', array_key_first($stats->parMenu));
    }

    public function testLePanierMoyenSeRapporteAuxCommandesLivrees(): void
    {
        $menu = $this->menu('Buffet bordelais');

        $this->commande($menu, '400.00', Commande::LIVREE);
        $this->commande($menu, '200.00', Commande::LIVREE);
        // Celle-ci ne doit pas tirer la moyenne vers le bas : elle n'a rien
        // rapporté, elle ne doit pas non plus diviser.
        $this->commande($menu, '1000.00', Commande::EN_ATTENTE);

        self::assertEqualsWithDelta(300.0, (float) $this->calculateur->calculer()->panierMoyen, 0.001);
    }

    public function testSansAucuneCommandeLesChiffresSontANeuf(): void
    {
        $this->menu('Buffet bordelais');

        $stats = $this->calculateur->calculer();

        // Surtout : pas de division par zéro sur le panier moyen.
        self::assertEqualsWithDelta(0.0, (float) $stats->panierMoyen, 0.001);
        self::assertSame(0, $stats->nbCommandes);
        self::assertSame([], $stats->parMenu);
    }

    public function testLaRepartitionParStatutCouvreTousLesStatuts(): void
    {
        $menu = $this->menu('Buffet bordelais');
        $this->commande($menu, '400.00', Commande::LIVREE);
        $this->commande($menu, '100.00', Commande::ANNULEE);

        $stats = $this->calculateur->calculer();

        // Tous les statuts sont présents, même à zéro : un tableau de bord qui
        // fait disparaître une colonne selon les données est illisible.
        self::assertSame(array_keys($stats->parStatut), Commande::STATUTS);
        self::assertSame(1, $stats->parStatut[Commande::LIVREE]);
        self::assertSame(1, $stats->parStatut[Commande::ANNULEE]);
        self::assertSame(0, $stats->parStatut[Commande::CONFIRMEE]);
    }

    // --- Dépôt ------------------------------------------------------------

    public function testUnReleveSurvitAUnAllerRetourEnDocument(): void
    {
        $menu = $this->menu('Buffet bordelais');
        $this->commande($menu, '400.00', Commande::LIVREE);

        $avant = $this->calculateur->calculer();
        $apres = Instantane::depuisDocument($avant->enDocument());

        // Le passage par la forme document ne doit rien perdre, sinon
        // l'historique afficherait autre chose que ce qui a été relevé.
        self::assertSame($avant->chiffreAffaires, $apres->chiffreAffaires);
        self::assertSame($avant->nbCommandes, $apres->nbCommandes);
        self::assertSame($avant->panierMoyen, $apres->panierMoyen);
        self::assertSame($avant->parMenu, $apres->parMenu);
        self::assertSame($avant->parStatut, $apres->parStatut);
        self::assertSame(
            $avant->releveLe->format(\DateTimeInterface::ATOM),
            $apres->releveLe->format(\DateTimeInterface::ATOM),
        );
    }

    public function testLHistoriqueSortDuPlusRecentAuPlusAncien(): void
    {
        $depot = new DepotEnMemoire();

        foreach (['-3 days', '-1 day', '-2 days'] as $age) {
            $depot->enregistrer($this->instantane($age));
        }

        $dates = array_map(
            fn (Instantane $i) => $i->releveLe->format('Y-m-d'),
            $depot->historique(),
        );

        // Enregistrés dans le désordre (-3, -1, -2), ils doivent ressortir du
        // plus récent au plus ancien : -1, -2, -3.
        self::assertSame([
            (new \DateTimeImmutable('-1 day'))->format('Y-m-d'),
            (new \DateTimeImmutable('-2 days'))->format('Y-m-d'),
            (new \DateTimeImmutable('-3 days'))->format('Y-m-d'),
        ], $dates);

        self::assertSame(
            (new \DateTimeImmutable('-1 day'))->format('Y-m-d'),
            $depot->dernier()->releveLe->format('Y-m-d'),
        );

        // La limite s'applique après le tri : on veut les plus récents.
        self::assertCount(2, $depot->historique(2));
    }

    public function testSansExtensionMongoLaFabriqueRetombeEnMemoire(): void
    {
        // C'est ce qui permet à l'application de démarrer — et à cette suite de
        // tests de tourner — sans serveur NoSQL.
        self::assertInstanceOf(DepotEnMemoire::class, FabriqueDepot::creer(''));
        self::assertInstanceOf(DepotEnMemoire::class, FabriqueDepot::creer('   '));

        if (!\extension_loaded('mongodb')) {
            self::assertInstanceOf(
                DepotEnMemoire::class,
                FabriqueDepot::creer('mongodb://localhost:27017'),
            );
        }
    }

    public function testLeDepotInjecteEstUtilisableSansServeur(): void
    {
        $depot = static::getContainer()->get(DepotStatistiques::class);

        self::assertTrue($depot->disponible());
        self::assertNull($depot->dernier());
    }

    // --- Accès ------------------------------------------------------------

    public function testLesStatistiquesSontReserveesALAdministrateur(): void
    {
        $this->connecter(['ROLE_EMPLOYE']);
        $this->client->request('GET', '/admin/statistiques');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testUnAdministrateurVoitLesChiffres(): void
    {
        $menu = $this->menu('Buffet bordelais');
        $this->commande($menu, '400.00', Commande::LIVREE);

        $this->connecter(['ROLE_ADMIN']);
        $crawler = $this->client->request('GET', '/admin/statistiques');

        self::assertResponseIsSuccessful();
        $texte = $crawler->filter('body')->text();

        self::assertStringContainsString('400.00 €', $texte);
        self::assertStringContainsString('Buffet bordelais', $texte);
    }

    public function testLaPageResteLisibleSansHistorique(): void
    {
        $this->connecter(['ROLE_ADMIN']);

        $crawler = $this->client->request('GET', '/admin/statistiques');

        // Aucun relevé enregistré : la page doit expliquer comment en produire,
        // pas planter ni afficher un tableau vide sans explication.
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('app:calculer-statistiques', $crawler->filter('body')->text());
    }

    // --- Fixtures ---------------------------------------------------------

    private function instantane(string $age): Instantane
    {
        return new Instantane(
            releveLe: new \DateTimeImmutable($age),
            nbCommandes: 1, nbCommandesLivrees: 1,
            chiffreAffaires: '100.00', panierMoyen: '100.00',
            parMenu: [], parStatut: [],
        );
    }

    private function menu(string $titre): Menu
    {
        $menu = (new Menu())
            ->setTitre($titre)->setDescription('Une formule.')
            ->setTheme($this->theme)->setRegime($this->regime)
            ->setPrixMin('40.00')->setNbMinPersonnes(10)
            ->setDelaiCommandeJours(3)->setStock(20);

        $this->em->persist($menu);
        $this->em->flush();

        return $menu;
    }

    private function commande(Menu $menu, string $total, string $statut): Commande
    {
        static $rang = 0;
        ++$rang;

        $client = new Utilisateur();
        $client->setEmail(sprintf('convive%d@example.com', $rang))
            ->setNom('D')->setPrenom('C')->setRoles([])->setActif(true)
            ->setPassword('peu importe');
        $this->em->persist($client);

        $commande = (new Commande())
            ->setUtilisateur($client)->setMenu($menu)
            ->setDateCommande(new \DateTime('-1 month'))
            ->setDatePrestation(new \DateTime('-1 week'))
            ->setHeureLivraison(new \DateTime('12:00'))
            ->setLieuLivraison('Bordeaux')
            ->setCodePostalLivraison('33000')
            ->setNbPersonnes(10)->setPrixTotal($total)
            ->setStatut($statut)->setPretMateriel(false);

        $this->em->persist($commande);
        $this->em->flush();

        return $commande;
    }

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
