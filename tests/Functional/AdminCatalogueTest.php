<?php

namespace App\Tests\Functional;

use App\Entity\Allergene;
use App\Entity\Ingredient;
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
 * Gestion du catalogue : plats, ingrédients, allergènes, thèmes et régimes.
 */
class AdminCatalogueTest extends WebTestCase
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

    // --- Contrôle d'accès -------------------------------------------------

    public function testLesSectionsDuCatalogueSontFermeesAuxVisiteursAnonymes(): void
    {
        foreach (['plats', 'ingredients', 'allergenes', 'themes', 'regimes'] as $section) {
            $this->client->request('GET', '/admin/'.$section);

            self::assertResponseRedirects();
            self::assertStringContainsString(
                '/connexion',
                $this->client->getResponse()->headers->get('Location') ?? '',
                "La section $section devrait renvoyer vers la connexion."
            );
        }
    }

    public function testLesSectionsDuCatalogueSontInterditesAuxClients(): void
    {
        $this->connecter($this->creerUtilisateur('client@example.com', ['ROLE_USER']));

        foreach (['plats', 'ingredients', 'allergenes', 'themes', 'regimes'] as $section) {
            $this->client->request('GET', '/admin/'.$section);
            self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN, "Section $section");
        }
    }

    // --- Création ---------------------------------------------------------

    public function testUnAdministrateurCreeUnThemeEtUnRegime(): void
    {
        $this->connecterAdmin();

        $this->client->request('GET', '/admin/themes/nouveau');
        $this->client->submitForm('Enregistrer', [
            'theme[libelle]' => 'Créole',
            'theme[description]' => 'Cuisine réunionnaise',
        ]);
        self::assertResponseRedirects('/admin/themes');

        $this->client->request('GET', '/admin/regimes/nouveau');
        $this->client->submitForm('Enregistrer', [
            'regime[libelle]' => 'Sans gluten',
        ]);
        self::assertResponseRedirects('/admin/regimes');

        $this->em->clear();
        self::assertNotNull($this->em->getRepository(Theme::class)->findOneBy(['libelle' => 'Créole']));
        self::assertNotNull($this->em->getRepository(Regime::class)->findOneBy(['libelle' => 'Sans gluten']));
    }

    public function testUnIngredientEstCreeAvecSesAllergenes(): void
    {
        $this->connecterAdmin();
        $allergeneId = $this->creerAllergene('Gluten')->getId();

        $this->client->request('GET', '/admin/ingredients/nouveau');
        $this->client->submitForm('Enregistrer', [
            'ingredient[nom]' => 'Farine de blé',
            'ingredient[allergenes]' => [(string) $allergeneId],
        ]);

        self::assertResponseRedirects('/admin/ingredients');

        $this->em->clear();
        $ingredient = $this->em->getRepository(Ingredient::class)->findOneBy(['nom' => 'Farine de blé']);

        self::assertNotNull($ingredient);
        self::assertCount(1, $ingredient->getAllergenes(), "L'allergène n'a pas été rattaché.");
        self::assertSame('Gluten', $ingredient->getAllergenes()->first()->getLibelle());
    }

    public function testUnPlatEstCreeAvecSesIngredients(): void
    {
        $this->connecterAdmin();
        $ingredientId = $this->creerIngredient('Saucisse')->getId();

        $this->client->request('GET', '/admin/plats/nouveau');
        $this->client->submitForm('Enregistrer', [
            'plat[nom]' => 'Rougail saucisse',
            'plat[type]' => 'Plat',
            'plat[description]' => 'Spécialité réunionnaise.',
            'plat[ingredients]' => [(string) $ingredientId],
        ]);

        self::assertResponseRedirects('/admin/plats');

        $this->em->clear();
        $plat = $this->em->getRepository(Plat::class)->findOneBy(['nom' => 'Rougail saucisse']);

        self::assertNotNull($plat);
        self::assertCount(1, $plat->getIngredients(), "L'ingrédient n'a pas été rattaché.");
    }

    public function testUnLibelleVideEstRefuse(): void
    {
        $this->connecterAdmin();

        $this->client->request('GET', '/admin/allergenes/nouveau');
        $this->client->submitForm('Enregistrer', ['allergene[libelle]' => '']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(0, $this->em->getRepository(Allergene::class)->count([]));
    }

    // --- Modification -----------------------------------------------------

    public function testUnAdministrateurModifieUnAllergene(): void
    {
        $this->connecterAdmin();
        $id = $this->creerAllergene('Glutn')->getId();

        $this->client->request('GET', '/admin/allergenes/'.$id.'/modifier');
        $this->client->submitForm('Enregistrer les modifications', ['allergene[libelle]' => 'Gluten']);

        self::assertResponseRedirects('/admin/allergenes');

        $this->em->clear();
        self::assertSame('Gluten', $this->em->getRepository(Allergene::class)->find($id)->getLibelle());
    }

    // --- Gardes de suppression --------------------------------------------

    public function testUnAllergeneRattacheAUnIngredientNePeutPasEtreSupprime(): void
    {
        $this->connecterAdmin();
        $allergene = $this->creerAllergene('Gluten');
        $ingredient = $this->creerIngredient('Farine');
        $ingredient->addAllergene($allergene);
        $this->em->flush();
        $id = $allergene->getId();

        $this->soumettreSuppression('/admin/allergenes');

        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('ne peut pas être supprimé', $crawler->filter('.flash')->text());

        $this->em->clear();
        self::assertNotNull($this->em->getRepository(Allergene::class)->find($id));
    }

    public function testUnIngredientUtiliseDansUnPlatNePeutPasEtreSupprime(): void
    {
        $this->connecterAdmin();
        $ingredient = $this->creerIngredient('Saucisse');
        $plat = $this->creerPlat('Rougail');
        $plat->addIngredient($ingredient);
        $this->em->flush();
        $id = $ingredient->getId();

        $this->soumettreSuppression('/admin/ingredients');
        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('ne peut pas être supprimé', $crawler->filter('.flash')->text());

        $this->em->clear();
        self::assertNotNull($this->em->getRepository(Ingredient::class)->find($id));
    }

    public function testUnThemeUtiliseParUnMenuNePeutPasEtreSupprime(): void
    {
        $this->connecterAdmin();
        $theme = $this->creerTheme('Créole');
        $this->creerMenu('Menu créole', $theme, $this->creerRegime('Sans gluten'));
        $id = $theme->getId();

        $this->soumettreSuppression('/admin/themes');
        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('ne peut pas être supprimé', $crawler->filter('.flash')->text());

        $this->em->clear();
        self::assertNotNull($this->em->getRepository(Theme::class)->find($id));
    }

    public function testUnAllergeneLibreEstBienSupprime(): void
    {
        $this->connecterAdmin();
        $id = $this->creerAllergene('Inutilisé')->getId();

        $this->soumettreSuppression('/admin/allergenes');
        self::assertResponseRedirects('/admin/allergenes');

        $this->em->clear();
        self::assertNull($this->em->getRepository(Allergene::class)->find($id));
    }

    public function testLaSuppressionSansJetonCsrfEstRefusee(): void
    {
        $this->connecterAdmin();
        $id = $this->creerAllergene('Gluten')->getId();

        $this->client->request('POST', '/admin/allergenes/'.$id.'/supprimer');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->em->clear();
        self::assertNotNull($this->em->getRepository(Allergene::class)->find($id));
    }

    // --- Le blocage initial est-il levé ? ---------------------------------

    public function testUnMenuDevientCreableUneFoisThemeEtRegimeSaisis(): void
    {
        $this->connecterAdmin();

        // Sans thème ni régime, les listes du formulaire de menu sont vides.
        $crawler = $this->client->request('GET', '/admin/menus/nouveau');
        self::assertCount(0, $crawler->filter('#menu_theme option[value!=""]'));

        // On les crée depuis l'administration…
        $this->client->request('GET', '/admin/themes/nouveau');
        $this->client->submitForm('Enregistrer', ['theme[libelle]' => 'Créole']);
        $this->client->request('GET', '/admin/regimes/nouveau');
        $this->client->submitForm('Enregistrer', ['regime[libelle]' => 'Sans gluten']);

        $this->em->clear();
        $themeId = $this->em->getRepository(Theme::class)->findOneBy(['libelle' => 'Créole'])->getId();
        $regimeId = $this->em->getRepository(Regime::class)->findOneBy(['libelle' => 'Sans gluten'])->getId();

        // … et la création de menu devient possible.
        $this->client->request('GET', '/admin/menus/nouveau');
        $this->client->submitForm('Créer le menu', [
            'menu[titre]' => 'Menu créole',
            'menu[description]' => 'Saveurs de La Réunion.',
            'menu[theme]' => (string) $themeId,
            'menu[regime]' => (string) $regimeId,
            'menu[nbMinPersonnes]' => '6',
            'menu[prixMin]' => '18.50',
            'menu[delaiCommandeJours]' => '3',
            'menu[stock]' => '10',
        ]);

        self::assertResponseRedirects('/admin/menus');

        $this->em->clear();
        self::assertNotNull($this->em->getRepository(Menu::class)->findOneBy(['titre' => 'Menu créole']));
    }

    // --- Fixtures ---------------------------------------------------------

    private function soumettreSuppression(string $liste): void
    {
        $crawler = $this->client->request('GET', $liste);
        $this->client->submit($crawler->filter('form[action$="/supprimer"] button')->form());
    }

    private function creerUtilisateur(string $email, array $roles): Utilisateur
    {
        $utilisateur = new Utilisateur();
        $utilisateur->setEmail($email)->setNom('Test')->setPrenom('Utilisateur')
            ->setRoles($roles)->setActif(true);
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

    private function connecterAdmin(): void
    {
        $this->connecter($this->creerUtilisateur('admin@example.com', ['ROLE_ADMIN']));
    }

    private function creerAllergene(string $libelle): Allergene
    {
        $a = (new Allergene())->setLibelle($libelle);
        $this->em->persist($a);
        $this->em->flush();

        return $a;
    }

    private function creerIngredient(string $nom): Ingredient
    {
        $i = (new Ingredient())->setNom($nom);
        $this->em->persist($i);
        $this->em->flush();

        return $i;
    }

    private function creerPlat(string $nom): Plat
    {
        $p = (new Plat())->setNom($nom)->setType('Plat')->setDescription('Description.');
        $this->em->persist($p);
        $this->em->flush();

        return $p;
    }

    private function creerTheme(string $libelle): Theme
    {
        $t = (new Theme())->setLibelle($libelle);
        $this->em->persist($t);
        $this->em->flush();

        return $t;
    }

    private function creerRegime(string $libelle): Regime
    {
        $r = (new Regime())->setLibelle($libelle);
        $this->em->persist($r);
        $this->em->flush();

        return $r;
    }

    private function creerMenu(string $titre, Theme $theme, Regime $regime): Menu
    {
        $m = new Menu();
        $m->setTitre($titre)->setDescription('Description.')->setTheme($theme)->setRegime($regime)
            ->setNbMinPersonnes(4)->setPrixMin('20.00')->setDelaiCommandeJours(2)->setStock(5);
        $this->em->persist($m);
        $this->em->flush();

        return $m;
    }
}
