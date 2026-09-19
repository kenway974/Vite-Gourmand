<?php

namespace App\Tests\Functional;

use App\Entity\Allergene;
use App\Entity\Avis;
use App\Entity\Commande;
use App\Entity\Ingredient;
use App\Entity\Menu;
use App\Entity\Plat;
use App\Entity\Regime;
use App\Entity\Theme;
use App\Entity\Utilisateur;
use App\Repository\MenuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Catalogue public : recherche, filtres, tri, pagination et fiche menu.
 *
 * C'est le seul chemin qui mène un visiteur jusqu'à la commande.
 */
class CatalogueTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private MenuRepository $menus;

    private Theme $mariage;
    private Theme $noel;
    private Regime $omnivore;
    private Regime $vegetarien;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->menus = static::getContainer()->get(MenuRepository::class);

        $st = new SchemaTool($this->em);
        $md = $this->em->getMetadataFactory()->getAllMetadata();
        $st->dropSchema($md);
        $st->createSchema($md);

        $this->mariage = $this->theme('Mariage');
        $this->noel = $this->theme('Noël');
        $this->omnivore = $this->regime('Omnivore');
        $this->vegetarien = $this->regime('Végétarien');
        $this->em->flush();
    }

    // --- Accès ------------------------------------------------------------

    public function testLeCatalogueEstOuvertAuxVisiteursAnonymes(): void
    {
        $this->menu('Buffet bordelais');

        $this->client->request('GET', '/menus');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Nos menus');
        self::assertSelectorTextContains('.catalogue', 'Buffet bordelais');
    }

    public function testChercherSansChoisirDeFiltreNeCassePas(): void
    {
        $this->menu('Buffet bordelais');

        // On soumet le vrai formulaire plutôt qu'une URL écrite à la main :
        // c'est le chemin du visiteur qui clique « Rechercher » sans rien
        // choisir, et il envoie « theme= », « regime= » et « convives= » vides.
        $crawler = $this->client->request('GET', '/menus');
        $this->client->submit($crawler->filter('form.filtres')->form());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.catalogue', 'Buffet bordelais');
    }

    /**
     * Les mêmes paramètres vides passés directement dans l'URL : la pagination
     * les reconduit, et un lien de page ne doit pas mener à une erreur.
     */
    public function testLesParametresVidesSontTraitesCommeAbsents(): void
    {
        $this->menu('Buffet bordelais');

        $this->client->request('GET', '/menus?q=&theme=&regime=&convives=&tri=&page=');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.catalogue', 'Buffet bordelais');
    }

    public function testChaqueMenuMeneVersSaFiche(): void
    {
        $menu = $this->menu('Buffet bordelais');

        $this->client->request('GET', '/menus');
        $this->client->clickLink('Buffet bordelais');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Buffet bordelais');
        self::assertStringContainsString('/menus/'.$menu->getId(), $this->client->getRequest()->getUri());
    }

    public function testUneFicheInexistanteRenvoieUne404(): void
    {
        $this->client->request('GET', '/menus/9999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // --- Filtrage sans rechargement (AJAX) ---------------------------------

    /**
     * Une requête marquée X-Requested-With ne reçoit que le fragment de
     * résultats : c'est ce que le contrôleur Stimulus injecte à la place de
     * la page, il ne doit donc pas recevoir la page entière autour.
     */
    public function testUneRequeteAjaxNeRenvoieQueLeFragmentDeResultats(): void
    {
        $this->menu('Buffet bordelais');

        $this->client->request('GET', '/menus', server: ['HTTP_X-Requested-With' => 'XMLHttpRequest']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.catalogue', 'Buffet bordelais');
        self::assertSelectorNotExists('nav.nav-principale');
        self::assertSelectorNotExists('form.filtres');
    }

    /**
     * Même filtre, avec et sans l'en-tête AJAX : le fragment doit refléter
     * les mêmes résultats que la page complète, pas une vue différente.
     */
    public function testLaRequeteAjaxRespecteLesMemesFiltresQueLaPageComplete(): void
    {
        $this->menu('Entrecôte à la bordelaise');
        $this->menu('Plateau de fruits de mer');

        $this->client->request(
            'GET',
            '/menus?q=entrecôte',
            server: ['HTTP_X-Requested-With' => 'XMLHttpRequest'],
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.catalogue', 'Entrecôte à la bordelaise');
        self::assertSelectorTextNotContains('.catalogue', 'Plateau de fruits de mer');
    }

    /** Une requête normale, sans l'en-tête, continue de recevoir la page entière. */
    public function testUneRequeteSansEnTeteRecoitLaPageComplete(): void
    {
        $this->menu('Buffet bordelais');

        $this->client->request('GET', '/menus');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('nav.nav-principale');
        self::assertSelectorExists('form.filtres');
    }

    // --- Recherche --------------------------------------------------------

    public function testLaRechercheTrouveUnMenuParSonTitre(): void
    {
        $this->menu('Entrecôte à la bordelaise');
        $this->menu('Plateau de fruits de mer');

        $titres = $this->titres(['recherche' => 'entrecôte']);

        self::assertSame(['Entrecôte à la bordelaise'], $titres);
    }

    public function testLaRechercheIgnoreLaCasse(): void
    {
        $this->menu('Entrecôte à la bordelaise');

        self::assertSame(['Entrecôte à la bordelaise'], $this->titres(['recherche' => 'BORDELAISE']));
        self::assertSame(['Entrecôte à la bordelaise'], $this->titres(['recherche' => 'EnTrEcÔtE']));
    }

    public function testLaRechercheIgnoreLesAccents(): void
    {
        $this->menu('Pâté de foie', description: 'Terrine de campagne.');
        $this->menu('Plateau de fruits de mer', description: 'Huîtres et crevettes.');

        // L'exigence : taper sans accent doit trouver le menu accentué.
        self::assertSame(['Pâté de foie'], $this->titres(['recherche' => 'pate de foi']));
        self::assertSame(['Pâté de foie'], $this->titres(['recherche' => 'PATE']));

        // Et l'inverse : taper avec accent doit trouver aussi.
        self::assertSame(['Pâté de foie'], $this->titres(['recherche' => 'pâté']));

        // Y compris dans la description.
        self::assertSame(['Plateau de fruits de mer'], $this->titres(['recherche' => 'huitres']));
    }

    public function testLaRechercheSansAccentsNeDependPasDuSgbd(): void
    {
        // Ce test tourne sur SQLite, qui ne plie pas les accents. S'il passe
        // ici, c'est que la normalisation est bien faite en PHP et non déléguée
        // à la collation de la base.
        $menu = $this->menu('Crème brûlée');

        self::assertSame('creme brulee une formule de saison.', $menu->getRecherche());
        self::assertSame(['Crème brûlée'], $this->titres(['recherche' => 'creme brulee']));
    }

    public function testLaRechercheTrouveUnMenuParSaDescription(): void
    {
        $this->menu('Formule découverte', description: 'Autour du canelé et du vin de Pessac.');
        $this->menu('Formule express', description: 'Sandwichs et salades.');

        self::assertSame(['Formule découverte'], $this->titres(['recherche' => 'canelé']));
    }

    public function testLesJokersSqlSaisisParLeVisiteurSontNeutralises(): void
    {
        $this->menu('Buffet bordelais');
        $this->menu('Plateau de fruits de mer');

        // Sans échappement, « % » seul ramènerait tout le catalogue.
        self::assertSame([], $this->titres(['recherche' => '%']));
        self::assertSame([], $this->titres(['recherche' => '_uffet']));
    }

    // --- Filtres ----------------------------------------------------------

    public function testLeFiltreParThemeEtParRegime(): void
    {
        $this->menu('Repas de Noël', theme: $this->noel);
        $this->menu('Banquet de mariage', theme: $this->mariage);
        $this->menu('Buffet vert', theme: $this->mariage, regime: $this->vegetarien);

        self::assertSame(['Repas de Noël'], $this->titres(['theme' => $this->noel]));
        self::assertSame(['Banquet de mariage', 'Buffet vert'], $this->titres(['theme' => $this->mariage]));
        self::assertSame(['Buffet vert'], $this->titres(['regime' => $this->vegetarien]));
    }

    public function testLeFiltreParEffectifEcarteLesMenusHorsDePortee(): void
    {
        $this->menu('Petit comité', nbMinPersonnes: 4);
        $this->menu('Grand banquet', nbMinPersonnes: 50);

        self::assertSame(['Petit comité'], $this->titres(['nbPersonnes' => 10]));
    }

    public function testLeFiltreConvivesEstProposeParPaliers(): void
    {
        $this->menu('Buffet bordelais');

        $crawler = $this->client->request('GET', '/menus');
        $proposes = $crawler->filter('#convives option')->each(fn ($n) => $n->attr('value'));

        // Les maquettes proposent trois paliers, pas un champ libre.
        self::assertSame(['', '4', '6', '20'], $proposes);
    }

    public function testUnEffectifHorsPalierEstIgnore(): void
    {
        $this->menu('Petit comité', nbMinPersonnes: 4);
        $this->menu('Grand banquet', nbMinPersonnes: 50);

        // 6 est un palier : il filtre.
        $crawler = $this->client->request('GET', '/menus?convives=6');
        self::assertSelectorTextContains('.catalogue', 'Petit comité');
        self::assertStringNotContainsString('Grand banquet', $crawler->filter('.catalogue')->text());

        // 7 n'en est pas un : la valeur vient de l'URL, elle est ignorée
        // plutôt que passée telle quelle à la requête.
        $crawler = $this->client->request('GET', '/menus?convives=7');
        self::assertStringContainsString('Grand banquet', $crawler->filter('.catalogue')->text());
    }

    public function testUnMenuEpuiseResteAuCatalogueMaisPasDansLesCommandables(): void
    {
        $this->menu('Buffet bordelais', stock: 0);

        // Il reste affiché : c'est ce qui fait savoir au visiteur qu'on le propose.
        self::assertSame(['Buffet bordelais'], $this->titres([]));
        self::assertSame([], $this->titres(['seulementCommandables' => true]));
    }

    public function testUnMenuDontLaPeriodeEstRevolueDisparaitDuCatalogue(): void
    {
        $this->menu('Menu de Pâques', dateFin: new \DateTime('-1 day'));
        $this->menu('Buffet bordelais');

        self::assertSame(['Buffet bordelais'], $this->titres([]));
    }

    // --- Tri --------------------------------------------------------------

    public function testLeTriParPrix(): void
    {
        $this->menu('Formule express', prixMin: '18.00');
        $this->menu('Banquet de mariage', prixMin: '75.00');
        $this->menu('Buffet bordelais', prixMin: '42.00');

        self::assertSame(
            ['Formule express', 'Buffet bordelais', 'Banquet de mariage'],
            $this->titres(['tri' => 'prix-croissant']),
        );
        self::assertSame(
            ['Banquet de mariage', 'Buffet bordelais', 'Formule express'],
            $this->titres(['tri' => 'prix-decroissant']),
        );
    }

    public function testLeTriParNoteClasseLesMieuxNotesEnTete(): void
    {
        $bien = $this->menu('Banquet de mariage');
        $moyen = $this->menu('Formule express');
        $sansAvis = $this->menu('Buffet bordelais');

        $this->avis($bien, 5);
        $this->avis($bien, 4);
        $this->avis($moyen, 2);

        $titres = $this->titres(['tri' => 'note']);

        self::assertSame('Banquet de mariage', $titres[0]);
        self::assertSame('Formule express', $titres[1]);
        // Un menu sans avis passe après ceux qui sont notés, pas devant.
        self::assertSame('Buffet bordelais', $titres[2]);
        self::assertNotNull($sansAvis->getId());
    }

    public function testUnTriInconnuRetombeSurLOrdreAlphabetique(): void
    {
        $this->menu('Buffet bordelais');
        $this->menu('Assiette landaise');

        // La valeur vient de l'URL : elle ne doit jamais atteindre le DQL.
        self::assertSame(
            ['Assiette landaise', 'Buffet bordelais'],
            $this->titres(['tri' => "m.id ASC; DROP TABLE menu"]),
        );
    }

    // --- Pagination -------------------------------------------------------

    public function testLaPaginationDecoupeLeCatalogue(): void
    {
        for ($i = 1; $i <= 11; ++$i) {
            $this->menu(sprintf('Menu %02d', $i));
        }

        $premiere = $this->menus->findCatalogue([], 1, 9);
        $seconde = $this->menus->findCatalogue([], 2, 9);

        self::assertCount(11, $premiere);
        self::assertCount(9, iterator_to_array($premiere, false));
        self::assertCount(2, iterator_to_array($seconde, false));

        $this->client->request('GET', '/menus?page=2');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('nav[aria-label="Pagination du catalogue"]', 'Page 2 sur 2');
    }

    // --- Fiche menu -------------------------------------------------------

    public function testLaFicheDeduitLesAllergenesDesIngredients(): void
    {
        $menu = $this->menu('Buffet bordelais');

        $gluten = $this->allergene('Gluten');
        $lait = $this->allergene('Lait');

        $farine = $this->ingredient('Farine', [$gluten]);
        $beurre = $this->ingredient('Beurre', [$lait]);
        $sel = $this->ingredient('Sel', []);

        $menu->addPlat($this->plat('Canelé', [$farine, $beurre, $sel]));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/menus/'.$menu->getId());

        self::assertResponseIsSuccessful();
        $texte = $crawler->filter('body')->text();

        self::assertStringContainsString('Gluten', $texte);
        self::assertStringContainsString('Lait', $texte);
        self::assertStringContainsString('Canelé', $texte);
    }

    public function testLaFicheNAfficheQueLesAvisValides(): void
    {
        $menu = $this->menu('Buffet bordelais');

        $this->avis($menu, 5, 'Parfait du début à la fin.');
        $this->avis($menu, 1, 'Commentaire encore en modération.', Avis::EN_ATTENTE);
        $this->avis($menu, 1, 'Commentaire refusé.', Avis::REFUSE);

        $crawler = $this->client->request('GET', '/menus/'.$menu->getId());
        $texte = $crawler->filter('body')->text();

        self::assertStringContainsString('Parfait du début à la fin.', $texte);
        self::assertStringNotContainsString('encore en modération', $texte);
        self::assertStringNotContainsString('Commentaire refusé', $texte);
        // Seul l'avis validé compte dans la moyenne : en comptant les deux
        // autres, notés 1, elle tomberait à 2,3 et la chaîne ne collerait plus.
        self::assertStringContainsString('5 sur 5 — 1 avis vérifié', $texte);
    }

    public function testChaqueAvisAfficheLeContexteDeSaCommande(): void
    {
        $menu = $this->menu('Buffet bordelais');
        $this->avis($menu, 5, 'Parfait.', prestation: '2024-12-14', convives: 8);

        $crawler = $this->client->request('GET', '/menus/'.$menu->getId());

        // « Décembre 2024 · 8 convives », comme sur les maquettes : c'est la
        // date du repas qui compte, pas celle de rédaction de l'avis.
        self::assertStringContainsString('Décembre 2024 · 8 convives', $crawler->filter('body')->text());
    }

    public function testLeContexteDesAvisNeCoutePasUneRequeteParAvis(): void
    {
        $menu = $this->menu('Buffet bordelais');

        for ($i = 0; $i < 6; ++$i) {
            $this->avis($menu, 4, 'Très bien.');
        }

        $this->client->request('GET', '/');
        $this->client->enableProfiler();
        $this->client->request('GET', '/menus/'.$menu->getId());

        $requetes = $this->client->getProfile()->getCollector('db')->getQueryCount();

        self::assertLessThanOrEqual(
            8,
            $requetes,
            sprintf('%d requêtes pour 6 avis : la commande n\'est plus ramenée avec.', $requetes),
        );
    }

    public function testUnMenuCommandableExposeLeLienDeCommande(): void
    {
        $menu = $this->menu('Buffet bordelais');
        $epuise = $this->menu('Plateau de fruits de mer', stock: 0);

        $crawler = $this->client->request('GET', '/menus/'.$menu->getId());
        self::assertCount(1, $crawler->filter('a[href="/commander/'.$menu->getId().'"]'));

        $crawler = $this->client->request('GET', '/menus/'.$epuise->getId());
        self::assertCount(0, $crawler->filter('a[href="/commander/'.$epuise->getId().'"]'));
        self::assertSelectorTextContains('body', 'Momentanément épuisé');
    }

    // --- Notation globale -------------------------------------------------

    public function testLAccueilAfficheLaMoyenneTousMenusConfondus(): void
    {
        $premier = $this->menu('Buffet bordelais');
        $second = $this->menu('Banquet de mariage');

        $this->avis($premier, 5);
        $this->avis($second, 4);
        $this->avis($second, 1, 'En attente.', Avis::EN_ATTENTE);

        $crawler = $this->client->request('GET', '/');
        $texte = $crawler->filter('body')->text();

        // (5 + 4) / 2 = 4,5. L'avis en modération ne compte pas : « vérifiés ».
        self::assertStringContainsString('4,5 sur 5', $texte);
        self::assertStringContainsString('2 avis vérifiés', $texte);
    }

    public function testLAccueilNAnnonceRienSansAvisPublie(): void
    {
        $menu = $this->menu('Buffet bordelais');
        $this->avis($menu, 5, 'En attente.', Avis::EN_ATTENTE);

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('sur 5', $crawler->filter('body')->text());
    }

    public function testLaMoyenneGlobaleDiffereDeCelleDuMenu(): void
    {
        $premier = $this->menu('Buffet bordelais');
        $second = $this->menu('Banquet de mariage');

        $this->avis($premier, 5);
        $this->avis($second, 1);

        // Deux agrégats distincts : 3,0 en global, 5 sur la fiche du premier menu.
        // La moyenne globale garde toujours une décimale, comme « 4,8 sur 5 ».
        self::assertStringContainsString('3,0 sur 5', $this->client->request('GET', '/')->filter('body')->text());
        self::assertStringContainsString(
            '5 sur 5 — 1 avis vérifié',
            $this->client->request('GET', '/menus/'.$premier->getId())->filter('body')->text(),
        );
    }

    // --- Coût en requêtes -------------------------------------------------

    public function testLeCatalogueNeDeclenchePasUneRequeteParMenu(): void
    {
        // Un thème et un régime distincts par menu : s'ils étaient partagés,
        // Doctrine ne les chargerait qu'une fois et une jointure manquante
        // passerait inaperçue.
        for ($i = 1; $i <= 9; ++$i) {
            $menu = $this->menu(
                sprintf('Menu %02d', $i),
                theme: $this->theme('Thème '.$i),
                regime: $this->regime('Régime '.$i),
            );
            $this->avis($menu, 4);
        }

        // Le compteur de requêtes n'est remis à zéro qu'entre deux requêtes
        // HTTP : sans cette page intercalaire, les INSERT des fixtures
        // ci-dessus seraient comptés avec ceux du catalogue.
        $this->client->request('GET', '/');

        $this->client->enableProfiler();
        $this->client->request('GET', '/menus');
        self::assertResponseIsSuccessful();

        $requetes = $this->client->getProfile()->getCollector('db')->getQueryCount();

        // Catalogue + comptage + notes + thèmes + régimes, et rien qui grandisse
        // avec le nombre de menus affichés.
        self::assertLessThanOrEqual(
            8,
            $requetes,
            sprintf('%d requêtes pour 9 menus notés : le N+1 est de retour.', $requetes),
        );
    }

    // --- Fixtures ---------------------------------------------------------

    /**
     * @param array<string, mixed> $filtres
     *
     * @return string[]
     */
    private function titres(array $filtres): array
    {
        return array_map(
            fn (Menu $m) => $m->getTitre(),
            iterator_to_array($this->menus->findCatalogue($filtres, 1, 50), false),
        );
    }

    private function menu(
        string $titre,
        ?string $description = 'Une formule de saison.',
        ?Theme $theme = null,
        ?Regime $regime = null,
        string $prixMin = '42.00',
        int $nbMinPersonnes = 10,
        int $stock = 20,
        ?\DateTime $dateDebut = null,
        ?\DateTime $dateFin = null,
    ): Menu {
        $menu = (new Menu())
            ->setTitre($titre)
            ->setDescription($description)
            ->setTheme($theme ?? $this->mariage)
            ->setRegime($regime ?? $this->omnivore)
            ->setPrixMin($prixMin)
            ->setNbMinPersonnes($nbMinPersonnes)
            ->setDelaiCommandeJours(3)
            ->setStock($stock)
            ->setDateDebut($dateDebut)
            ->setDateFin($dateFin);

        $this->em->persist($menu);
        $this->em->flush();

        return $menu;
    }

    private function avis(
        Menu $menu,
        int $note,
        string $commentaire = 'Très bien.',
        string $statut = Avis::VALIDE,
        string $prestation = '-1 week',
        int $convives = 10,
    ): Avis {
        static $rang = 0;
        ++$rang;

        $client = new Utilisateur();
        $client->setEmail(sprintf('client%d@example.com', $rang))
            ->setNom('Dupont')->setPrenom('Camille')->setRoles([])->setActif(true)
            ->setPassword('peu importe');
        $this->em->persist($client);

        $commande = (new Commande())
            ->setUtilisateur($client)
            ->setMenu($menu)
            ->setDateCommande(new \DateTime('-1 month'))
            ->setDatePrestation(new \DateTime($prestation))
            ->setHeureLivraison(new \DateTime('12:00'))
            ->setLieuLivraison('12 cours de l\'Intendance, Bordeaux')
            ->setNbPersonnes($convives)
            ->setPrixTotal('420.00')
            ->setStatut(Commande::LIVREE)
            ->setPretMateriel(false);
        $this->em->persist($commande);

        $avis = (new Avis())
            ->setCommande($commande)
            ->setUtilisateur($client)
            ->setNote($note)
            ->setCommentaire($commentaire)
            ->setStatutValidation($statut)
            ->setDateCreation(new \DateTime('-3 days'));
        $this->em->persist($avis);
        $this->em->flush();

        return $avis;
    }

    private function theme(string $libelle): Theme
    {
        $t = (new Theme())->setLibelle($libelle);
        $this->em->persist($t);

        return $t;
    }

    private function regime(string $libelle): Regime
    {
        $r = (new Regime())->setLibelle($libelle);
        $this->em->persist($r);

        return $r;
    }

    private function allergene(string $libelle): Allergene
    {
        $a = (new Allergene())->setLibelle($libelle);
        $this->em->persist($a);

        return $a;
    }

    /** @param Allergene[] $allergenes */
    private function ingredient(string $nom, array $allergenes): Ingredient
    {
        $i = (new Ingredient())->setNom($nom);

        foreach ($allergenes as $allergene) {
            $i->addAllergene($allergene);
        }

        $this->em->persist($i);

        return $i;
    }

    /** @param Ingredient[] $ingredients */
    private function plat(string $nom, array $ingredients): Plat
    {
        $p = (new Plat())->setNom($nom)->setType('Dessert')->setDescription('Spécialité bordelaise.');

        foreach ($ingredients as $ingredient) {
            $p->addIngredient($ingredient);
        }

        $this->em->persist($p);

        return $p;
    }
}
