<?php

namespace App\Tests\Functional;

use App\Entity\Commande;
use App\Entity\Menu;
use App\Entity\Regime;
use App\Entity\Theme;
use App\Entity\Utilisateur;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Restitution du matériel prêté et indemnité de 600 €.
 *
 * « Plats et présentoirs sont à restituer sous dix jours ouvrés, sans quoi
 * une indemnité de 600 € s'applique. »
 */
class MaterielTest extends WebTestCase
{
    private const MOT_DE_PASSE = 'Motdepasse&33!';

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private CommandeRepository $depot;
    private int $menuId;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->depot = static::getContainer()->get(CommandeRepository::class);

        $st = new SchemaTool($this->em);
        $md = $this->em->getMetadataFactory()->getAllMetadata();
        $st->dropSchema($md);
        $st->createSchema($md);

        $theme = (new Theme())->setLibelle('Mariage');
        $regime = (new Regime())->setLibelle('Omnivore');
        $this->em->persist($theme);
        $this->em->persist($regime);

        $menu = (new Menu())
            ->setTitre('Buffet bordelais')
            ->setDescription('Une formule de saison.')
            ->setTheme($theme)->setRegime($regime)
            ->setPrixMin('42.00')->setNbMinPersonnes(10)
            ->setDelaiCommandeJours(3)->setStock(20);
        $this->em->persist($menu);
        $this->em->flush();

        $this->menuId = $menu->getId();
    }

    // --- Calcul du délai --------------------------------------------------

    public function testLeDelaiSeCompteEnJoursOuvres(): void
    {
        // Le vendredi 18 septembre 2026, plus dix jours ouvrés : les quatre
        // jours de week-end intercalés ne comptent pas, on tombe deux
        // semaines plus tard, le vendredi 2 octobre.
        $commande = $this->commande(prestation: '2026-09-18', materiel: true);

        self::assertSame('2026-10-02', $commande->dateLimiteRestitution()->format('Y-m-d'));
    }

    public function testUnSamediDePrestationNeDecaleRienDePlus(): void
    {
        // Samedi 19 septembre 2026 : le décompte démarre au lundi suivant.
        $commande = $this->commande(prestation: '2026-09-19', materiel: true);

        self::assertSame('2026-10-02', $commande->dateLimiteRestitution()->format('Y-m-d'));
    }

    public function testSansPretDeMaterielIlNYARienARendre(): void
    {
        $commande = $this->commande(prestation: '2026-09-18', materiel: false);

        self::assertNull($commande->dateLimiteRestitution());
        self::assertFalse($commande->materielEstEnRetard(new \DateTime('2030-01-01')));
    }

    // --- Retard -----------------------------------------------------------

    public function testLeRetardCommenceAuLendemainDeLaDateLimite(): void
    {
        $commande = $this->commande(prestation: '2026-09-18', materiel: true);

        self::assertFalse($commande->materielEstEnRetard(new \DateTime('2026-10-02')));
        self::assertTrue($commande->materielEstEnRetard(new \DateTime('2026-10-03')));
    }

    public function testUnMaterielRenduDansLesTempsNEstPasEnRetard(): void
    {
        $commande = $this->commande(prestation: '2026-09-18', materiel: true);
        $commande->restituerMateriel(new \DateTime('2026-09-30'));

        // Même consultée bien plus tard, la commande n'est pas en retard :
        // c'est la date de retour qui fait foi, pas celle du jour.
        self::assertFalse($commande->materielEstEnRetard(new \DateTime('2030-01-01')));
    }

    public function testUnMaterielRenduApresLeDelaiResteEnRetard(): void
    {
        $commande = $this->commande(prestation: '2026-09-18', materiel: true);
        $commande->restituerMateriel(new \DateTime('2026-10-10'));

        self::assertTrue($commande->materielEstEnRetard());
    }

    // --- Indemnité --------------------------------------------------------

    public function testLIndemniteEstRefuseeAvantLaDateLimite(): void
    {
        $commande = $this->commande(prestation: '2026-09-18', materiel: true);

        $this->expectException(\LogicException::class);
        $commande->appliquerIndemniteMateriel(date: new \DateTime('2026-09-25'));
    }

    public function testLIndemniteEstDeSixCentsEuros(): void
    {
        $commande = $this->commande(prestation: '2026-09-18', materiel: true);

        self::assertFalse($commande->indemniteEstFacturee());

        $commande->appliquerIndemniteMateriel(date: new \DateTime('2026-10-15'));

        self::assertTrue($commande->indemniteEstFacturee());
        self::assertEqualsWithDelta(600.0, (float) $commande->getIndemniteMateriel(), 0.001);
    }

    public function testAnnulerUnRetourEffaceLIndemnite(): void
    {
        $commande = $this->commande(prestation: '2026-09-18', materiel: true);
        $commande->restituerMateriel(new \DateTime('2026-10-10'));
        $commande->appliquerIndemniteMateriel();

        $commande->annulerRestitutionMateriel();

        self::assertNull($commande->getDateRestitutionMateriel());
        self::assertFalse($commande->indemniteEstFacturee());
    }

    // --- Requête ----------------------------------------------------------

    public function testLaFileNeRetientQueLesPretsLivresEtNonRendus(): void
    {
        $attendue = $this->commande(prestation: '-20 days', materiel: true);
        $rendue = $this->commande(prestation: '-20 days', materiel: true);
        $rendue->restituerMateriel(new \DateTime('-15 days'));
        $this->commande(prestation: '-20 days', materiel: false);
        // Une commande annulée n'a jamais donné lieu à un prêt effectif.
        $this->commande(prestation: '-20 days', materiel: true, statut: Commande::ANNULEE);
        $this->em->flush();

        $ids = array_map(fn (Commande $c) => $c->getId(), $this->depot->findMaterielPrete(false));

        self::assertSame([$attendue->getId()], $ids);
        self::assertSame(
            [$rendue->getId()],
            array_map(fn (Commande $c) => $c->getId(), $this->depot->findMaterielPrete(true)),
        );
    }

    // --- Espace employé ---------------------------------------------------

    public function testLaPageMaterielEstInterditeAuxClients(): void
    {
        $this->connecter($this->utilisateur('client@example.com', []));

        $this->client->request('GET', '/employe/materiel');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testUnEmployeEnregistreUnRetour(): void
    {
        $id = $this->commande(prestation: '-5 days', materiel: true)->getId();
        $this->connecter($this->utilisateur('employe@example.com', ['ROLE_EMPLOYE']));

        $crawler = $this->client->request('GET', '/employe/materiel');
        self::assertResponseIsSuccessful();

        $this->client->submit($crawler->filter('form[action*="/restitution"] button')->form());
        self::assertResponseRedirects('/employe/materiel');

        $this->em->clear();
        self::assertNotNull($this->depot->find($id)->getDateRestitutionMateriel());
    }

    public function testLIndemniteNEstProposeeQuAuDelaDuDelai(): void
    {
        $this->commande(prestation: '-5 days', materiel: true);
        $this->connecter($this->utilisateur('employe@example.com', ['ROLE_EMPLOYE']));

        $crawler = $this->client->request('GET', '/employe/materiel');
        self::assertCount(0, $crawler->filter('form[action*="/indemnite"]'));

        // Trente jours calendaires dépassent dix jours ouvrés dans tous les cas.
        $this->commande(prestation: '-30 days', materiel: true);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/employe/materiel');
        self::assertCount(1, $crawler->filter('form[action*="/indemnite"]'));
    }

    public function testUnEmployeFactureLIndemnite(): void
    {
        $id = $this->commande(prestation: '-30 days', materiel: true)->getId();
        $this->connecter($this->utilisateur('employe@example.com', ['ROLE_EMPLOYE']));

        $crawler = $this->client->request('GET', '/employe/materiel');
        $this->client->submit($crawler->filter('form[action*="/indemnite"] button')->form());

        $this->em->clear();
        self::assertEqualsWithDelta(600.0, (float) $this->depot->find($id)->getIndemniteMateriel(), 0.001);
    }

    public function testLesActionsExigentUnJetonCsrf(): void
    {
        $id = $this->commande(prestation: '-30 days', materiel: true)->getId();
        $this->connecter($this->utilisateur('employe@example.com', ['ROLE_EMPLOYE']));

        foreach (['restitution', 'indemnite'] as $action) {
            $this->client->request('POST', sprintf('/employe/materiel/%d/%s', $id, $action));
            self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN, $action);
        }

        $this->em->clear();
        $commande = $this->depot->find($id);

        self::assertNull($commande->getDateRestitutionMateriel());
        self::assertFalse($commande->indemniteEstFacturee());
    }

    // --- Fixtures ---------------------------------------------------------

    private function commande(string $prestation, bool $materiel, string $statut = Commande::LIVREE): Commande
    {
        static $rang = 0;
        ++$rang;

        // Le menu est relu à chaque appel : le client HTTP redémarre le noyau
        // entre deux requêtes, et l'objet chargé dans setUp() serait détaché.
        $menu = $this->em->getRepository(Menu::class)->find($this->menuId);

        $client = new Utilisateur();
        $client->setEmail(sprintf('convive%d@example.com', $rang))
            ->setNom('Dupont')->setPrenom('Camille')->setRoles([])->setActif(true)
            ->setPassword('peu importe');
        $this->em->persist($client);

        $commande = (new Commande())
            ->setUtilisateur($client)
            ->setMenu($menu)
            ->setDateCommande(new \DateTime($prestation.' -10 days'))
            ->setDatePrestation(new \DateTime($prestation))
            ->setHeureLivraison(new \DateTime('12:00'))
            ->setLieuLivraison('12 cours de l\'Intendance, Bordeaux')
            ->setNbPersonnes(10)
            ->setPrixTotal('420.00')
            ->setStatut($statut)
            ->setPretMateriel($materiel);

        $this->em->persist($commande);
        $this->em->flush();

        return $commande;
    }

    private function utilisateur(string $email, array $roles): Utilisateur
    {
        $u = new Utilisateur();
        $u->setEmail($email)->setNom('T')->setPrenom('U')->setRoles($roles)->setActif(true);
        $u->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)
            ->hashPassword($u, self::MOT_DE_PASSE));
        $this->em->persist($u);
        $this->em->flush();

        return $u;
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
}
