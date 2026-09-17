<?php

namespace App\Tests\Functional;

use App\Entity\Commande;
use App\Entity\Menu;
use App\Entity\Regime;
use App\Entity\Theme;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Pages légales, effacement RGPD et pages d'erreur.
 */
class ConformiteLegaleTest extends WebTestCase
{
    private const MOT_DE_PASSE = 'Motdepasse&33!';

    private const PAGES_LEGALES = [
        '/mentions-legales',
        '/conditions-generales-de-vente',
        '/politique-de-confidentialite',
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

    // --- Pages légales ----------------------------------------------------

    #[DataProvider('pagesLegales')]
    public function testLesPagesLegalesSontPubliques(string $url): void
    {
        $this->client->request('GET', $url);

        // Publiques au sens strict : la loi les impose accessibles à tous,
        // pas seulement aux clients inscrits.
        self::assertResponseIsSuccessful($url);
    }

    #[DataProvider('pagesLegales')]
    public function testChaquePageLegaleEstAtteignableDepuisLePiedDePage(string $url): void
    {
        $crawler = $this->client->request('GET', '/');
        $liens = $crawler->filter('footer a')->each(fn ($n) => $n->attr('href'));

        self::assertContains($url, $liens, $url.' devrait figurer au pied de page.');
    }

    public function testLesCgvDecriventLesReglesReellementAppliquees(): void
    {
        $texte = $this->client->request('GET', '/conditions-generales-de-vente')->filter('body')->text();

        // Un contrat qui annonce autre chose que ce que le code fait est faux.
        self::assertStringContainsString('10 %', $texte);
        self::assertStringContainsString('600 €', $texte);
        self::assertStringContainsString('dix jours ouvrés', $texte);
        self::assertStringContainsString('vingt-quatre heures', $texte);
        self::assertStringContainsString('48 heures', $texte);
        self::assertStringContainsString('Bordeaux intra-muros', $texte);
    }

    public function testLaCaseDInscriptionRenvoieVersLesCgv(): void
    {
        $crawler = $this->client->request('GET', '/inscription');

        self::assertGreaterThan(
            0,
            $crawler->filter('a[href="/conditions-generales-de-vente"]')->count(),
            'Faire accepter un contrat sans lien pour le lire n\'engage personne.',
        );
    }

    // --- Effacement RGPD --------------------------------------------------

    public function testLEffacementRetireLIdentiteEtGardeLesCommandes(): void
    {
        $client = $this->utilisateur('camille@example.fr');
        $this->commande($client);
        $id = $client->getId();

        $this->connecter('camille@example.fr');
        $crawler = $this->client->request('GET', '/mon-compte/suppression');
        self::assertResponseIsSuccessful();

        $this->client->submit($crawler->filter('form button')->form());
        self::assertResponseRedirects('/');

        $this->em->clear();
        $apres = $this->em->getRepository(Utilisateur::class)->find($id);

        // Le compte existe toujours — la comptabilité impose de garder les
        // commandes — mais il ne dit plus qui c'était.
        self::assertNotNull($apres);
        self::assertTrue($apres->estAnonymise());
        self::assertSame('Compte supprimé', $apres->getNom());
        self::assertNull($apres->getGsm());
        self::assertNull($apres->getAdressePostale());
        self::assertFalse($apres->isActif());
        self::assertSame([], array_diff($apres->getRoles(), ['ROLE_USER']));

        // La commande, elle, est intacte.
        self::assertSame(1, $this->em->getRepository(Commande::class)->count([]));
    }

    public function testUnCompteEfffaceNePeutPlusSeConnecter(): void
    {
        $this->utilisateur('camille@example.fr');
        $this->connecter('camille@example.fr');

        $crawler = $this->client->request('GET', '/mon-compte/suppression');
        $this->client->submit($crawler->filter('form button')->form());

        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/connexion');
        $this->client->submitForm('Se connecter', [
            'email' => 'camille@example.fr',
            'password' => self::MOT_DE_PASSE,
        ]);

        self::assertStringContainsString(
            '/connexion',
            $this->client->getResponse()->headers->get('Location') ?? '',
        );
    }

    public function testDeuxEffacementsNeSeMarchentPasDessus(): void
    {
        // L'index unique sur l'e-mail casserait si l'anonymisation écrivait
        // deux fois la même adresse.
        $un = $this->utilisateur('un@example.fr');
        $deux = $this->utilisateur('deux@example.fr');

        $un->anonymiser();
        $deux->anonymiser();
        $this->em->flush();

        self::assertNotSame($un->getEmail(), $deux->getEmail());
        self::assertSame(2, $this->em->getRepository(Utilisateur::class)->count([]));
    }

    public function testLEffacementExigeUnJetonCsrf(): void
    {
        $client = $this->utilisateur('camille@example.fr');
        $id = $client->getId();
        $this->connecter('camille@example.fr');

        $this->client->request('POST', '/mon-compte/suppression');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->em->clear();
        self::assertFalse($this->em->getRepository(Utilisateur::class)->find($id)->estAnonymise());
    }

    public function testLEffacementEstFermeAuxVisiteursAnonymes(): void
    {
        $this->client->request('GET', '/mon-compte/suppression');

        self::assertResponseRedirects();
        self::assertStringContainsString('/connexion', $this->client->getResponse()->headers->get('Location') ?? '');
    }

    // --- Pages d'erreur ---------------------------------------------------

    public function testUnePageIntrouvableEstPresentable(): void
    {
        $this->client->request('GET', '/cette-page-nexiste-pas');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // --- Fixtures ---------------------------------------------------------

    public static function pagesLegales(): iterable
    {
        foreach (self::PAGES_LEGALES as $url) {
            yield $url => [$url];
        }
    }

    private function commande(Utilisateur $client): void
    {
        $theme = (new Theme())->setLibelle('Bistrot');
        $regime = (new Regime())->setLibelle('Standard');
        $this->em->persist($theme);
        $this->em->persist($regime);

        $menu = (new Menu())
            ->setTitre('Buffet')->setDescription('Une formule.')
            ->setTheme($theme)->setRegime($regime)
            ->setPrixMin('40.00')->setNbMinPersonnes(10)
            ->setDelaiCommandeJours(3)->setStock(20);
        $this->em->persist($menu);

        $commande = (new Commande())
            ->setUtilisateur($client)->setMenu($menu)
            ->setDateCommande(new \DateTime('-1 month'))
            ->setDatePrestation(new \DateTime('-1 week'))
            ->setHeureLivraison(new \DateTime('12:00'))
            ->setLieuLivraison('Bordeaux')->setCodePostalLivraison('33000')
            ->setNbPersonnes(10)->setPrixTotal('400.00')
            ->setStatut(Commande::LIVREE)->setPretMateriel(false);
        $this->em->persist($commande);
        $this->em->flush();
    }

    private function utilisateur(string $email): Utilisateur
    {
        $u = new Utilisateur();
        $u->setEmail($email)->setNom('Lartigue')->setPrenom('Camille')
            ->setGsm('0556000000')->setAdressePostale('12 rue des Faussets, Bordeaux')
            ->setRoles([])->setActif(true);
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
}
