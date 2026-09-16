<?php

namespace App\Tests\Functional;

use App\Entity\Allergene;
use App\Entity\Ingredient;
use App\Entity\Menu;
use App\Entity\Plat;
use App\Entity\Regime;
use App\Entity\Theme;
use App\Entity\Utilisateur;
use App\Repository\AllergeneRepository;
use App\Repository\MenuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Requêtes du catalogue, des allergènes, et garde-fou contre le N+1.
 */
class RequetesTest extends WebTestCase
{
    private const MOT_DE_PASSE = 'Admin&Gourmand974!';

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    private function menus(): MenuRepository
    {
        return static::getContainer()->get(MenuRepository::class);
    }

    private function allergenes(): AllergeneRepository
    {
        return static::getContainer()->get(AllergeneRepository::class);
    }

    // --- 7 : catalogue public ---------------------------------------------

    public function testLeCatalogueGardeLesMenusEnRupture(): void
    {
        $t = $this->theme('Bistrot');
        $r = $this->regime('Standard');
        $this->menu('En vente', $t, $r, stock: 5);
        $this->menu('En rupture', $t, $r, stock: 0);

        // Un menu épuisé reste au catalogue : le masquer reviendrait à cacher
        // au visiteur qu'on le propose. Son état est signalé à l'affichage.
        $resultats = iterator_to_array($this->menus()->findCatalogue());

        self::assertCount(2, $resultats);
    }

    public function testLeCatalogueMasqueLesMenusDontLaPeriodeEstPassee(): void
    {
        $t = $this->theme('Noël');
        $r = $this->regime('Standard');
        $this->menu('Toute l\'année', $t, $r);
        $this->menu('Saison terminée', $t, $r, debut: '-60 days', fin: '-30 days');

        $resultats = iterator_to_array($this->menus()->findCatalogue());

        self::assertCount(1, $resultats);
        self::assertSame('Toute l\'année', $resultats[0]->getTitre());
    }

    public function testLeCatalogueGardeLesMenusDontLaSaisonNaPasCommence(): void
    {
        $t = $this->theme('Noël');
        $r = $this->regime('Standard');
        $this->menu('Noël Tradition', $t, $r, debut: '+60 days', fin: '+90 days');

        // Visible, mais pas encore commandable : c'est tout l'intérêt.
        $resultats = iterator_to_array($this->menus()->findCatalogue());

        self::assertCount(1, $resultats);
        self::assertFalse($resultats[0]->estCommandable());
        self::assertSame(Menu::BIENTOT, $resultats[0]->disponibilite());
    }

    public function testLeFiltreSeulementCommandablesEcarteRuptureEtHorsSaison(): void
    {
        $t = $this->theme('Bistrot');
        $r = $this->regime('Standard');
        $this->menu('Commandable', $t, $r, stock: 5);
        $this->menu('Épuisé', $t, $r, stock: 0);
        $this->menu('Pas encore ouvert', $t, $r, debut: '+30 days', fin: '+60 days');

        $tous = iterator_to_array($this->menus()->findCatalogue());
        $commandables = iterator_to_array($this->menus()->findCatalogue(['seulementCommandables' => true]));

        self::assertCount(3, $tous);
        self::assertCount(1, $commandables);
        self::assertSame('Commandable', $commandables[0]->getTitre());
    }

    public function testLesQuatreEtatsDeDisponibilite(): void
    {
        $t = $this->theme('Bistrot');
        $r = $this->regime('Standard');

        $disponible = $this->menu('Disponible', $t, $r, stock: 5);
        $epuise = $this->menu('Épuisé', $t, $r, stock: 0);
        $bientot = $this->menu('Bientôt', $t, $r, stock: 5, debut: '+10 days', fin: '+20 days');
        $termine = $this->menu('Terminé', $t, $r, stock: 5, debut: '-20 days', fin: '-10 days');

        self::assertSame(Menu::DISPONIBLE, $disponible->disponibilite());
        self::assertSame(Menu::EPUISE, $epuise->disponibilite());
        self::assertSame(Menu::BIENTOT, $bientot->disponibilite());
        self::assertSame(Menu::TERMINE, $termine->disponibilite());

        // La période prime sur le stock : un menu de Noël consulté en juillet
        // doit annoncer « bientôt », pas « épuisé ».
        $horsSaisonEtEpuise = $this->menu('Hors saison et épuisé', $t, $r, stock: 0, debut: '+10 days', fin: '+20 days');
        self::assertSame(Menu::BIENTOT, $horsSaisonEtEpuise->disponibilite());
    }

    public function testUnMenuSansDateEstProposeTouteLAnnee(): void
    {
        $menu = $this->menu('Sans date', $this->theme('Bistrot'), $this->regime('Standard'));

        self::assertTrue($menu->estDansSaPeriode());
        self::assertTrue($menu->estDansSaPeriode(new \DateTime('+10 years')));
        self::assertTrue($menu->estCommandable());
    }

    public function testLeCatalogueFiltreParTheme(): void
    {
        $creole = $this->theme('Créole');
        $italien = $this->theme('Italien');
        $r = $this->regime('Standard');
        $this->menu('Rougail', $creole, $r);
        $this->menu('Pasta', $italien, $r);

        $resultats = iterator_to_array($this->menus()->findCatalogue(['theme' => $creole]));

        self::assertCount(1, $resultats);
        self::assertSame('Rougail', $resultats[0]->getTitre());
    }

    public function testLeCatalogueEcarteLesMenusTropGrandsPourLEffectif(): void
    {
        $t = $this->theme('Créole');
        $r = $this->regime('Standard');
        $this->menu('Petit comité', $t, $r, nbMin: 4);
        $this->menu('Grande tablée', $t, $r, nbMin: 20);

        // Un client qui réserve pour 6 ne doit pas voir le menu réservé aux 20+.
        $resultats = iterator_to_array($this->menus()->findCatalogue(['nbPersonnes' => 6]));

        self::assertCount(1, $resultats);
        self::assertSame('Petit comité', $resultats[0]->getTitre());
    }

    public function testLeCatalogueFiltreParPrixMaximum(): void
    {
        $t = $this->theme('Créole');
        $r = $this->regime('Standard');
        $this->menu('Abordable', $t, $r, prix: '15.00');
        $this->menu('Prestige', $t, $r, prix: '80.00');

        $resultats = iterator_to_array($this->menus()->findCatalogue(['prixMax' => '20.00']));

        self::assertCount(1, $resultats);
        self::assertSame('Abordable', $resultats[0]->getTitre());
    }

    public function testLeCataloguePagine(): void
    {
        $t = $this->theme('Créole');
        $r = $this->regime('Standard');
        for ($i = 1; $i <= 12; ++$i) {
            $this->menu(sprintf('Menu %02d', $i), $t, $r);
        }

        $page1 = $this->menus()->findCatalogue([], page: 1, parPage: 9);
        $page2 = $this->menus()->findCatalogue([], page: 2, parPage: 9);

        // Le total porte sur l'ensemble, pas sur la page affichée.
        self::assertCount(12, $page1);
        self::assertCount(9, iterator_to_array($page1));
        self::assertCount(3, iterator_to_array($page2));
    }

    // --- 8 : détail d'un menu ---------------------------------------------

    public function testLeDetailRamenePlatsEtIngredientsEnUneRequete(): void
    {
        $t = $this->theme('Créole');
        $r = $this->regime('Standard');
        $menu = $this->menu('Menu créole', $t, $r);

        $ingredient = $this->ingredient('Saucisse');
        $plat = $this->plat('Rougail');
        $plat->addIngredient($ingredient);
        $menu->addPlat($plat);
        $this->em->flush();
        $id = $menu->getId();
        $this->em->clear();

        $detail = $this->menus()->findDetail($id);

        self::assertNotNull($detail);
        self::assertSame('Créole', $detail->getTheme()->getLibelle());
        self::assertCount(1, $detail->getPlats());
        self::assertCount(1, $detail->getPlats()->first()->getIngredients());
    }

    public function testLeDetailRenvoieNullSiLeMenuNExistePas(): void
    {
        self::assertNull($this->menus()->findDetail(999999));
    }

    // --- 12 : allergènes d'un menu ----------------------------------------

    public function testLesAllergenesRemontentDepuisLesIngredientsDesPlats(): void
    {
        $menu = $this->menu('Menu créole', $this->theme('Créole'), $this->regime('Standard'));

        $gluten = $this->allergene('Gluten');
        $lait = $this->allergene('Lait');

        $farine = $this->ingredient('Farine');
        $farine->addAllergene($gluten);
        $beurre = $this->ingredient('Beurre');
        $beurre->addAllergene($lait);

        $plat = $this->plat('Gâteau');
        $plat->addIngredient($farine);
        $plat->addIngredient($beurre);
        $menu->addPlat($plat);
        $this->em->flush();

        $resultats = $this->allergenes()->findPourMenu($menu);

        self::assertCount(2, $resultats);
        self::assertSame(['Gluten', 'Lait'], array_map(fn ($a) => $a->getLibelle(), $resultats));
    }

    public function testUnAllergenePresentDansPlusieursIngredientsNApparaitQuUneFois(): void
    {
        $menu = $this->menu('Menu créole', $this->theme('Créole'), $this->regime('Standard'));

        $gluten = $this->allergene('Gluten');

        // Trois ingrédients, répartis sur deux plats, tous porteurs du gluten.
        $plat1 = $this->plat('Gâteau');
        foreach (['Farine', 'Levure'] as $nom) {
            $ing = $this->ingredient($nom);
            $ing->addAllergene($gluten);
            $plat1->addIngredient($ing);
        }
        $plat2 = $this->plat('Pain');
        $ing = $this->ingredient('Farine complète');
        $ing->addAllergene($gluten);
        $plat2->addIngredient($ing);

        $menu->addPlat($plat1);
        $menu->addPlat($plat2);
        $this->em->flush();

        // Sans DISTINCT, le gluten sortirait trois fois.
        self::assertCount(1, $this->allergenes()->findPourMenu($menu));
    }

    public function testUnAllergeneDUnAutreMenuNEstPasRemonte(): void
    {
        $t = $this->theme('Créole');
        $r = $this->regime('Standard');
        $menuA = $this->menu('Menu A', $t, $r);
        $menuB = $this->menu('Menu B', $t, $r);

        $arachide = $this->allergene('Arachide');
        $ing = $this->ingredient('Cacahuète');
        $ing->addAllergene($arachide);
        $plat = $this->plat('Satay');
        $plat->addIngredient($ing);
        $menuB->addPlat($plat);
        $this->em->flush();

        self::assertCount(0, $this->allergenes()->findPourMenu($menuA));
        self::assertCount(1, $this->allergenes()->findPourMenu($menuB));
    }

    // --- Garde-fou : le N+1 ne doit pas revenir ---------------------------

    public function testLesListesAdminNeDependentPasDuNombreDeLignes(): void
    {
        $admin = new Utilisateur();
        $admin->setEmail('admin@example.com')->setNom('A')->setPrenom('A')
            ->setRoles(['ROLE_ADMIN'])->setActif(true);
        $admin->setPassword(
            static::getContainer()->get(UserPasswordHasherInterface::class)
                ->hashPassword($admin, self::MOT_DE_PASSE)
        );
        $this->em->persist($admin);

        // 20 lignes par section : avec du N+1, on dépasserait largement le plafond.
        $t = $this->theme('Créole');
        $r = $this->regime('Standard');
        for ($i = 1; $i <= 20; ++$i) {
            $a = $this->allergene("Allergene $i");
            $ing = $this->ingredient("Ingredient $i");
            $ing->addAllergene($a);
            $p = $this->plat("Plat $i");
            $p->addIngredient($ing);
            $m = $this->menu("Menu $i", $t, $r);
            $m->addPlat($p);
        }
        $this->em->flush();

        $this->client->request('GET', '/connexion');
        $this->client->submitForm('Se connecter', [
            'email' => 'admin@example.com',
            'password' => self::MOT_DE_PASSE,
        ]);
        $this->client->followRedirect();

        $plafond = 8;

        foreach (['/admin/menus', '/admin/plats', '/admin/ingredients',
                  '/admin/allergenes', '/admin/themes', '/admin/regimes'] as $page) {
            $this->client->enableProfiler();
            $this->client->request('GET', $page);
            self::assertResponseIsSuccessful();

            $profil = $this->client->getProfile();

            if (false === $profil) {
                self::markTestSkipped('Profiler indisponible : impossible de compter les requêtes.');
            }

            $nb = $profil->getCollector('db')->getQueryCount();

            self::assertLessThanOrEqual(
                $plafond,
                $nb,
                sprintf('%s a exécuté %d requêtes pour 20 lignes : le N+1 est revenu.', $page, $nb)
            );
        }
    }

    // --- Fixtures ---------------------------------------------------------

    private function theme(string $libelle): Theme
    {
        $t = (new Theme())->setLibelle($libelle);
        $this->em->persist($t);
        $this->em->flush();

        return $t;
    }

    private function regime(string $libelle): Regime
    {
        $r = (new Regime())->setLibelle($libelle);
        $this->em->persist($r);
        $this->em->flush();

        return $r;
    }

    private function allergene(string $libelle): Allergene
    {
        $a = (new Allergene())->setLibelle($libelle);
        $this->em->persist($a);

        return $a;
    }

    private function ingredient(string $nom): Ingredient
    {
        $i = (new Ingredient())->setNom($nom);
        $this->em->persist($i);

        return $i;
    }

    private function plat(string $nom): Plat
    {
        $p = (new Plat())->setNom($nom)->setType('Plat')->setDescription('Description.');
        $this->em->persist($p);

        return $p;
    }

    private function menu(
        string $titre,
        Theme $theme,
        Regime $regime,
        int $stock = 10,
        int $nbMin = 4,
        string $prix = '20.00',
        ?string $debut = null,
        ?string $fin = null,
    ): Menu {
        $m = new Menu();
        $m->setTitre($titre)->setDescription('Description.')->setTheme($theme)->setRegime($regime)
            ->setNbMinPersonnes($nbMin)->setPrixMin($prix)->setDelaiCommandeJours(2)->setStock($stock)
            ->setDateDebut($debut ? new \DateTime($debut) : null)
            ->setDateFin($fin ? new \DateTime($fin) : null);
        $this->em->persist($m);
        $this->em->flush();

        return $m;
    }
}
