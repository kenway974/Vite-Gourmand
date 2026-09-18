<?php

namespace App\Tests\Functional;

use App\Entity\Avis;
use App\Entity\Commande;
use App\Entity\Menu;
use App\Entity\Regime;
use App\Entity\SuiviCommande;
use App\Entity\Theme;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Espace employé : feuille de route, suivi des commandes, modération.
 */
class EspaceEmployeTest extends WebTestCase
{
    private const MOT_DE_PASSE = 'Motdepasse&33!';

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private int $menuId;

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

        $menu = (new Menu())->setTitre('Bistrot Bordelais')->setDescription('D.')
            ->setTheme($theme)->setRegime($regime)
            ->setNbMinPersonnes(6)->setPrixMin('24.00')->setDelaiCommandeJours(3)->setStock(5);
        $this->em->persist($menu);
        $this->em->flush();
        $this->menuId = $menu->getId();
    }

    // --- Accès ------------------------------------------------------------

    public function testUnClientNAccedePasALEspaceEmploye(): void
    {
        $this->connecter($this->utilisateur('client@example.com', []));

        foreach (['/employe', '/employe/commandes', '/employe/avis'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN, $url);
        }
    }

    public function testUnAdministrateurAccedeParHeritageDesRoles(): void
    {
        // ROLE_ADMIN hérite de ROLE_EMPLOYE : rien à déclarer en plus.
        $this->connecter($this->utilisateur('admin@example.com', ['ROLE_ADMIN']));

        $this->client->request('GET', '/employe');
        self::assertResponseIsSuccessful();
    }

    // --- Feuille de route -------------------------------------------------

    public function testLaFeuilleDeRouteDuJourEstTrieeEtSansAnnulees(): void
    {
        $client = $this->utilisateur('client@example.com', []);
        $this->commande($client, Commande::CONFIRMEE, 'today', '19:00');
        $this->commande($client, Commande::EN_PREPARATION, 'today', '12:00');
        $this->commande($client, Commande::ANNULEE, 'today', '08:00');
        $this->commande($client, Commande::CONFIRMEE, '+5 days', '10:00');

        $this->connecter($this->utilisateur('employe@example.com', ['ROLE_EMPLOYE']));
        $crawler = $this->client->request('GET', '/employe');

        self::assertResponseIsSuccessful();

        $lignes = $crawler->filter('table')->first()->filter('tbody tr');

        // Deux prestations du jour, l'annulée écartée, la plus matinale d'abord.
        self::assertCount(2, $lignes);
        self::assertStringContainsString('12h00', $lignes->eq(0)->text());
        self::assertStringContainsString('19h00', $lignes->eq(1)->text());
    }

    // --- Suivi ------------------------------------------------------------

    public function testUnEmployeFaitAvancerUneCommandeEtLaisseUneTrace(): void
    {
        $client = $this->utilisateur('client@example.com', []);
        $id = $this->commande($client, Commande::EN_ATTENTE, '+10 days', '12:00')->getId();

        $this->connecter($this->utilisateur('employe@example.com', ['ROLE_EMPLOYE']));
        $crawler = $this->client->request('GET', '/employe/commandes');

        $formulaire = $crawler->filter('form[action$="/statut"]')->form();
        $formulaire['statut'] = Commande::CONFIRMEE;
        $formulaire['motif'] = 'Devis accepté par le client.';
        $this->client->submit($formulaire);

        self::assertResponseRedirects('/employe/commandes');

        $this->em->clear();
        self::assertSame(Commande::CONFIRMEE, $this->em->getRepository(Commande::class)->find($id)->getStatut());

        $suivis = $this->em->getRepository(SuiviCommande::class)->findAll();
        self::assertCount(1, $suivis);
        self::assertSame(Commande::CONFIRMEE, $suivis[0]->getStatut());
        self::assertSame('Devis accepté par le client.', $suivis[0]->getMotif());
    }

    public function testAucunFormulaireNEstProposeSurUneCommandeTerminee(): void
    {
        $client = $this->utilisateur('client@example.com', []);
        $this->commande($client, Commande::LIVREE, '-5 days', '12:00');

        $this->connecter($this->utilisateur('employe@example.com', ['ROLE_EMPLOYE']));
        $crawler = $this->client->request('GET', '/employe/commandes');

        self::assertCount(0, $crawler->filter('form[action$="/statut"]'));
    }

    public function testUneCommandeTermineeNeSeRemetPasEnMouvement(): void
    {
        $client = $this->utilisateur('client@example.com', []);
        $id = $this->commande($client, Commande::EN_PREPARATION, '+2 days', '12:00')->getId();

        $this->connecter($this->utilisateur('employe@example.com', ['ROLE_EMPLOYE']));

        // On récupère un formulaire légitime tant que la commande bouge encore.
        $crawler = $this->client->request('GET', '/employe/commandes');
        $formulaire = $crawler->filter('form[action$="/statut"]')->form();

        $formulaire['statut'] = Commande::LIVREE;
        $this->client->submit($formulaire);

        // Puis on rejoue ce même formulaire, jeton valide compris, pour
        // revenir en arrière. La commande est désormais terminée : refusé.
        $formulaire['statut'] = Commande::EN_PREPARATION;
        $this->client->submit($formulaire);

        $this->em->clear();
        self::assertSame(Commande::LIVREE, $this->em->getRepository(Commande::class)->find($id)->getStatut());

        // Et aucune ligne de suivi n'a été ajoutée par la tentative.
        self::assertCount(1, $this->em->getRepository(SuiviCommande::class)->findAll());
    }

    public function testLeFiltreParStatutRestreintLaListe(): void
    {
        $client = $this->utilisateur('client@example.com', []);
        $this->commande($client, Commande::EN_ATTENTE, '+10 days', '12:00');
        $this->commande($client, Commande::LIVREE, '-5 days', '12:00');

        $this->connecter($this->utilisateur('employe@example.com', ['ROLE_EMPLOYE']));
        $crawler = $this->client->request('GET', '/employe/commandes?statut='.urlencode(Commande::LIVREE));

        self::assertCount(1, $crawler->filter('tbody tr'));
    }

    // --- Modération -------------------------------------------------------

    public function testUnAvisValideDevientPublie(): void
    {
        $client = $this->utilisateur('client@example.com', []);
        $avis = $this->avis($client, Avis::EN_ATTENTE, 5);
        $id = $avis->getId();

        $this->connecter($this->utilisateur('employe@example.com', ['ROLE_EMPLOYE']));
        $crawler = $this->client->request('GET', '/employe/avis');
        $this->client->submit($crawler->filter('form[action$="/moderer"] button[value="validé"]')->form());

        $this->em->clear();
        $relu = $this->em->getRepository(Avis::class)->find($id);

        self::assertSame(Avis::VALIDE, $relu->getStatutValidation());
        self::assertTrue($relu->estPublie());
    }

    public function testLaModerationSansJetonCsrfEstRefusee(): void
    {
        $client = $this->utilisateur('client@example.com', []);
        $id = $this->avis($client, Avis::EN_ATTENTE, 5)->getId();

        $this->connecter($this->utilisateur('employe@example.com', ['ROLE_EMPLOYE']));
        $this->client->request('POST', '/employe/avis/'.$id.'/moderer', ['decision' => Avis::VALIDE]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->em->clear();
        self::assertSame(Avis::EN_ATTENTE, $this->em->getRepository(Avis::class)->find($id)->getStatutValidation());
    }

    public function testLaMoyenneNeCompteQueLesAvisPublies(): void
    {
        $client = $this->utilisateur('client@example.com', []);
        $this->avis($client, Avis::VALIDE, 5);
        $this->avis($client, Avis::VALIDE, 4);
        $this->avis($client, Avis::EN_ATTENTE, 1);
        $this->avis($client, Avis::REFUSE, 1);

        $this->connecter($this->utilisateur('employe@example.com', ['ROLE_EMPLOYE']));
        $crawler = $this->client->request('GET', '/employe/avis');

        // Moyenne de 5 et 4, sans les avis non publiés : 4,5 et non 2,75.
        self::assertStringContainsString('4.5 sur 5', $crawler->filter('main p')->first()->text());
        self::assertStringContainsString('2 avis', $crawler->filter('main p')->first()->text());
    }

    // --- Fixtures ---------------------------------------------------------

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

    private function connecter(Utilisateur $u): void
    {
        $this->client->request('GET', '/connexion');
        $this->client->submitForm('Se connecter', ['email' => $u->getEmail(), 'password' => self::MOT_DE_PASSE]);
        $this->client->followRedirect();
    }

    private function commande(Utilisateur $client, string $statut, string $jour, string $heure): Commande
    {
        $c = new Commande();
        $c->setUtilisateur($client)
            ->setMenu($this->em->getRepository(Menu::class)->find($this->menuId))
            ->setDateCommande(new \DateTime())
            ->setDatePrestation(new \DateTime($jour))
            ->setHeureLivraison(new \DateTime($heure))
            ->setLieuLivraison('24 rue Notre-Dame, 33000 Bordeaux')
            ->setNbPersonnes(8)->setPrixTotal('192.00')
            ->setStatut($statut)->setPretMateriel(false);
        $this->em->persist($c);
        $this->em->flush();

        return $c;
    }

    private function avis(Utilisateur $client, string $statut, int $note): Avis
    {
        $commande = $this->commande($client, Commande::LIVREE, '-10 days', '12:00');

        $a = (new Avis())->setCommande($commande)->setUtilisateur($client)
            ->setNote($note)->setCommentaire('Commentaire de test.')
            ->setStatutValidation($statut)->setDateCreation(new \DateTime());
        $this->em->persist($a);
        $this->em->flush();

        return $a;
    }
}
