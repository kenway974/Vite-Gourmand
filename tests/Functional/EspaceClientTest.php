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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Espace client : consultation, annulation, dépôt d'avis.
 */
class EspaceClientTest extends WebTestCase
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

        $menu = new Menu();
        $menu->setTitre('Bistrot Bordelais')->setDescription('Description.')
            ->setTheme($theme)->setRegime($regime)
            ->setNbMinPersonnes(6)->setPrixMin('24.00')
            ->setDelaiCommandeJours(3)->setStock(5);
        $this->em->persist($menu);
        $this->em->flush();
        $this->menuId = $menu->getId();
    }

    public function testUnVisiteurAnonymeEstRenvoyeVersLaConnexion(): void
    {
        // La liste des commandes s'affiche désormais sur /mon-compte : il n'y
        // a plus de page /mes-commandes à part entière.
        $this->client->request('GET', '/mon-compte');

        self::assertResponseRedirects();
        self::assertStringContainsString('/connexion', $this->client->getResponse()->headers->get('Location') ?? '');
    }

    public function testLaListeNeMontreQueSesProprosCommandes(): void
    {
        $moi = $this->utilisateur('moi@example.com');
        $autre = $this->utilisateur('autre@example.com');
        $this->commande($moi, Commande::CONFIRMEE);
        $this->commande($autre, Commande::CONFIRMEE);

        $this->connecter('moi@example.com');
        $crawler = $this->client->request('GET', '/mon-compte');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('tbody tr'));
    }

    public function testLaCommandeDUnAutreClientEstIntrouvable(): void
    {
        $autre = $this->utilisateur('autre@example.com');
        $this->utilisateur('moi@example.com');
        $id = $this->commande($autre, Commande::CONFIRMEE)->getId();

        $this->connecter('moi@example.com');
        $this->client->request('GET', '/mes-commandes/'.$id);

        // Introuvable, et non « trouvée puis refusée » : le propriétaire fait
        // partie de la requête.
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testUneCommandeEnAttenteEstAnnulableEtLibereLaPrestation(): void
    {
        $moi = $this->utilisateur('moi@example.com');
        $commande = $this->commande($moi, Commande::EN_ATTENTE);
        $id = $commande->getId();

        $this->connecter('moi@example.com');
        $crawler = $this->client->request('GET', '/mes-commandes/'.$id);
        $this->client->submit($crawler->filter('form[action$="/annuler"] button')->form());

        self::assertResponseRedirects('/mes-commandes/'.$id);

        $this->em->clear();
        self::assertSame(Commande::ANNULEE, $this->em->getRepository(Commande::class)->find($id)->getStatut());
        // Le stock repart à 6 : la prestation est rendue au catalogue.
        self::assertSame(6, $this->em->getRepository(Menu::class)->find($this->menuId)->getStock());
    }

    public function testUneCommandeEnPreparationNEstPlusAnnulableEnLibreService(): void
    {
        $moi = $this->utilisateur('moi@example.com');
        $id = $this->commande($moi, Commande::EN_PREPARATION)->getId();

        $this->connecter('moi@example.com');
        $crawler = $this->client->request('GET', '/mes-commandes/'.$id);

        // Le bouton n'est même pas proposé.
        self::assertCount(0, $crawler->filter('form[action$="/annuler"]'));
    }

    public function testUnAvisNEstPossibleQuApresLivraison(): void
    {
        $moi = $this->utilisateur('moi@example.com');
        $id = $this->commande($moi, Commande::CONFIRMEE)->getId();

        $this->connecter('moi@example.com');
        $this->client->request('GET', '/mes-commandes/'.$id.'/avis');

        self::assertResponseRedirects('/mes-commandes/'.$id);
        self::assertSame(0, $this->em->getRepository(Avis::class)->count([]));
    }

    public function testUnAvisDeposeAttendLaModeration(): void
    {
        $moi = $this->utilisateur('moi@example.com');
        $id = $this->commande($moi, Commande::LIVREE)->getId();

        $this->connecter('moi@example.com');
        $this->client->request('GET', '/mes-commandes/'.$id.'/avis');
        $this->client->submitForm('Envoyer mon avis', [
            'avis[note]' => '5',
            'avis[commentaire]' => 'Entrecôte parfaite, livraison à l\'heure.',
        ]);

        self::assertResponseRedirects('/mes-commandes/'.$id);

        $this->em->clear();
        $avis = $this->em->getRepository(Avis::class)->findOneBy([]);

        self::assertNotNull($avis);
        self::assertSame(5, $avis->getNote());
        self::assertSame(Avis::EN_ATTENTE, $avis->getStatutValidation());
        self::assertFalse($avis->estPublie());
    }

    public function testUnSecondAvisSurLaMemeCommandeEstRefuse(): void
    {
        $moi = $this->utilisateur('moi@example.com');
        $commande = $this->commande($moi, Commande::LIVREE);
        $id = $commande->getId();

        $avis = (new Avis())->setCommande($commande)->setUtilisateur($moi)
            ->setNote(4)->setCommentaire('Déjà donné.')
            ->setStatutValidation(Avis::VALIDE)->setDateCreation(new \DateTime());
        $this->em->persist($avis);
        $this->em->flush();

        $this->connecter('moi@example.com');
        $this->client->request('GET', '/mes-commandes/'.$id.'/avis');

        // La base porte une contrainte d'unicité : on refuse avant d'y arriver.
        self::assertResponseRedirects('/mes-commandes/'.$id);
        self::assertSame(1, $this->em->getRepository(Avis::class)->count([]));
    }

    // --- Fixtures ---------------------------------------------------------

    private function utilisateur(string $email): Utilisateur
    {
        $u = new Utilisateur();
        $u->setEmail($email)->setNom('Test')->setPrenom('Client')->setRoles([])->setActif(true);
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

    private function commande(Utilisateur $client, string $statut): Commande
    {
        $c = new Commande();
        $c->setUtilisateur($client)
            ->setMenu($this->em->getRepository(Menu::class)->find($this->menuId))
            ->setDateCommande(new \DateTime())
            ->setDatePrestation(new \DateTime('+10 days'))
            ->setHeureLivraison(new \DateTime('12:00'))
            ->setLieuLivraison('24 rue Notre-Dame, 33000 Bordeaux')
            ->setNbPersonnes(8)
            ->setPrixTotal('192.00')
            ->setStatut($statut)
            ->setPretMateriel(false);

        $this->em->persist($c);
        $this->em->flush();

        return $c;
    }
}
