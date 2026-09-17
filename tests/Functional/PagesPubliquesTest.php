<?php

namespace App\Tests\Functional;

use App\Entity\Avis;
use App\Entity\Commande;
use App\Entity\Menu;
use App\Entity\Regime;
use App\Entity\Theme;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Les deux pages publiques que la navigation des maquettes annonce sans
 * qu'aucune route ne les serve : « Avis » et « Notre histoire ».
 *
 * L'enjeu de la page d'avis n'est pas l'affichage, c'est le filtrage : un avis
 * en attente de modération ou refusé ne doit jamais atteindre un visiteur.
 */
class PagesPubliquesTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Theme $theme;
    private Regime $regime;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $st = new SchemaTool($this->em);
        $md = $this->em->getMetadataFactory()->getAllMetadata();
        $st->dropSchema($md);
        $st->createSchema($md);

        $this->theme = (new Theme())->setLibelle('Mariage');
        $this->regime = (new Regime())->setLibelle('Omnivore');
        $this->em->persist($this->theme);
        $this->em->persist($this->regime);
        $this->em->flush();
    }

    // --- Accès ------------------------------------------------------------

    public function testLesDeuxPagesSontOuvertesAuxVisiteursAnonymes(): void
    {
        foreach (['/avis', '/notre-histoire'] as $chemin) {
            $this->client->request('GET', $chemin);

            self::assertResponseIsSuccessful($chemin);
        }
    }

    public function testLesDeuxPagesSontAtteignablesDepuisLaNavigation(): void
    {
        $crawler = $this->client->request('GET', '/');

        // Une page qu'aucun lien n'atteint n'existe pas pour un visiteur.
        self::assertCount(1, $crawler->filter('nav a[href="/avis"]'));
        self::assertCount(1, $crawler->filter('nav a[href="/notre-histoire"]'));
    }

    // --- Filtrage des avis ------------------------------------------------

    public function testSeulsLesAvisValidesSontPublies(): void
    {
        $menu = $this->menu('Buffet bordelais');

        $this->avis($menu, 5, 'Service impeccable.', Avis::VALIDE);
        $this->avis($menu, 1, 'Commentaire en cours de relecture.', Avis::EN_ATTENTE);
        $this->avis($menu, 2, 'Commentaire écarté par le personnel.', Avis::REFUSE);

        $this->client->request('GET', '/avis');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Service impeccable.');

        // Les deux autres ne doivent apparaître nulle part : ni en attente de
        // modération, ni refusé après relecture.
        $page = $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('en cours de relecture', $page);
        self::assertStringNotContainsString('écarté par le personnel', $page);
    }

    public function testLaMoyenneNeCompteQueLesAvisPublies(): void
    {
        $menu = $this->menu('Buffet bordelais');

        // Moyenne des validés : (5 + 3) / 2 = 4,0. En comptant le refusé à 1,
        // elle tomberait à 3,0.
        $this->avis($menu, 5, 'Parfait.', Avis::VALIDE);
        $this->avis($menu, 3, 'Correct.', Avis::VALIDE);
        $this->avis($menu, 1, 'Écarté.', Avis::REFUSE);

        $this->client->request('GET', '/avis');

        $page = $this->client->getResponse()->getContent();
        self::assertStringContainsString('4 sur 5', $page);
        self::assertStringContainsString('2 avis vérifiés', $page);
    }

    public function testUnAvisAfficheSonContexteEtSonMenu(): void
    {
        $menu = $this->menu('Dîner de Noël');
        $this->avis($menu, 4, 'Très bonne soirée.', Avis::VALIDE, '2024-12-14', 8);

        $this->client->request('GET', '/avis');

        $page = $this->client->getResponse()->getContent();
        // « Décembre 2024 · 8 convives », tel que les maquettes le demandent.
        self::assertStringContainsString('Décembre 2024 · 8 convives', $page);
        self::assertStringContainsString('Dîner de Noël', $page);
    }

    public function testSansAucunAvisLaPageLeDitSansEchouer(): void
    {
        $this->client->request('GET', '/avis');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', "Aucun avis n'a encore été publié.");
    }

    // --- Pagination -------------------------------------------------------

    public function testLaPaginationDecoupeLesAvisSansEnPerdreNiEnRepeter(): void
    {
        $menu = $this->menu('Buffet bordelais');

        // 12 avis pour 10 par page : deux pages, la seconde en contient deux.
        for ($i = 1; $i <= 12; ++$i) {
            $this->avis($menu, 4, sprintf('Commentaire numero %02d.', $i), Avis::VALIDE);
        }

        $premiere = $this->client->request('GET', '/avis');
        self::assertCount(10, $premiere->filter('article'));
        self::assertSelectorTextContains('body', 'Page 1 sur 2');

        $seconde = $this->client->request('GET', '/avis?page=2');
        self::assertCount(2, $seconde->filter('article'));

        // Aucun avis ne doit se retrouver sur les deux pages : c'est ce que
        // garantit le second critère de tri dans findPublies().
        $lus = array_merge(
            $premiere->filter('article')->each(fn ($n) => $n->text()),
            $seconde->filter('article')->each(fn ($n) => $n->text()),
        );
        $numeros = [];
        foreach ($lus as $texte) {
            preg_match('/Commentaire numero (\d{2})/', $texte, $m);
            $numeros[] = $m[1];
        }

        self::assertCount(12, $numeros);
        self::assertSame($numeros, array_unique($numeros), 'un avis apparaît sur deux pages');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pagesAberrantes')]
    public function testUnePageAberranteNeCasseRien(string $requete): void
    {
        $menu = $this->menu('Buffet bordelais');
        $this->avis($menu, 5, 'Parfait.', Avis::VALIDE);

        $this->client->request('GET', '/avis'.$requete);

        // Ni 500 sur un décalage négatif, ni 404 : on retombe sur quelque
        // chose de lisible.
        self::assertResponseIsSuccessful($requete);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pagesAberrantes(): iterable
    {
        yield 'page zéro' => ['?page=0'];
        yield 'page négative' => ['?page=-5'];
        yield 'page non numérique' => ['?page=abc'];
        yield 'page au-delà du dernier avis' => ['?page=99'];
    }

    public function testUnePageVideRenvoieVersLePremierAvis(): void
    {
        $menu = $this->menu('Buffet bordelais');
        $this->avis($menu, 5, 'Parfait.', Avis::VALIDE);

        $this->client->request('GET', '/avis?page=99');

        // « Aucun avis publié » serait faux ici : il y en a un, mais pas sur
        // cette page. La nuance évite de faire croire le site vide.
        self::assertSelectorTextContains('body', 'Cette page ne contient aucun avis.');
        self::assertSelectorExists('a[href="/avis"]');
    }

    // --- Notre histoire ---------------------------------------------------

    public function testNotreHistoireRenvoieVersLeCatalogueEtLeContact(): void
    {
        $crawler = $this->client->request('GET', '/notre-histoire');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Notre histoire');
        self::assertGreaterThan(0, $crawler->filter('a[href="/contact"]')->count());
        self::assertGreaterThan(0, $crawler->filter('a[href="/menus"]')->count());
    }

    public function testNotreHistoireNInventeAucuneValeurQueSeulLeClientConnait(): void
    {
        $this->client->request('GET', '/notre-histoire');

        // Même convention que les mentions légales : ce qu'on ne sait pas
        // reste visiblement à compléter plutôt que rempli au hasard.
        self::assertStringContainsString(
            '[',
            $this->client->getResponse()->getContent(),
        );
    }

    // --- Coût des requêtes ------------------------------------------------

    public function testLaPageDAvisNeDependPasDuNombreDAvis(): void
    {
        $menu = $this->menu('Buffet bordelais');

        // Dix avis sur la page, chacun avec son auteur, sa commande et son
        // menu : sans les jointures de findPublies(), on paierait trois
        // requêtes de plus par avis.
        for ($i = 1; $i <= 10; ++$i) {
            $this->avis($menu, 4, sprintf('Commentaire %02d.', $i), Avis::VALIDE);
        }

        // Une requête intermédiaire : le compteur du profileur n'est pas
        // remis à zéro entre l'écriture des fixtures et la mesure.
        $this->client->request('GET', '/');

        $this->client->enableProfiler();
        $this->client->request('GET', '/avis');
        self::assertResponseIsSuccessful();

        $profil = $this->client->getProfile();

        if (false === $profil) {
            self::markTestSkipped('Profiler indisponible : impossible de compter les requêtes.');
        }

        $nb = $profil->getCollector('db')->getQueryCount();

        self::assertLessThanOrEqual(
            6,
            $nb,
            sprintf('La page d\'avis a exécuté %d requêtes pour 10 avis : le N+1 est revenu.', $nb)
        );
    }

    // --- Fabriques --------------------------------------------------------

    private function menu(string $titre): Menu
    {
        $menu = (new Menu())
            ->setTitre($titre)
            ->setDescription('Une formule de saison.')
            ->setTheme($this->theme)
            ->setRegime($this->regime)
            ->setPrixMin('42.00')
            ->setNbMinPersonnes(10)
            ->setDelaiCommandeJours(3)
            ->setStock(20);

        $this->em->persist($menu);
        $this->em->flush();

        return $menu;
    }

    private function avis(
        Menu $menu,
        int $note,
        string $commentaire,
        string $statut,
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
            ->setDateCreation(new \DateTime(sprintf('-%d days', $rang)));
        $this->em->persist($avis);
        $this->em->flush();

        return $avis;
    }
}
