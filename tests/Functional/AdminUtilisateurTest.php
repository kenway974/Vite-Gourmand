<?php

namespace App\Tests\Functional;

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
 * Gestion des comptes par un administrateur.
 */
class AdminUtilisateurTest extends WebTestCase
{
    private const MOT_DE_PASSE = 'Motdepasse&33!';

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

    public function testLaSectionEstReserveeAuxAdministrateurs(): void
    {
        foreach ([[], ['ROLE_EMPLOYE']] as $roles) {
            $this->client->restart();
            $email = 'compte'.\count($roles).'@example.com';
            $this->utilisateur($email, $roles);
            $this->connecter($email);

            $this->client->request('GET', '/admin/utilisateurs');
            self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        }
    }

    public function testLaListeAfficheLeNombreDeCommandes(): void
    {
        $client = $this->utilisateur('client@example.com', []);
        $this->commande($client);
        $this->commande($client);
        $this->utilisateur('admin@example.com', ['ROLE_ADMIN']);

        $this->connecter('admin@example.com');
        $crawler = $this->client->request('GET', '/admin/utilisateurs');

        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('tbody tr'));

        $ligneClient = $crawler->filter('tbody tr')->reduce(
            fn ($n) => str_contains($n->text(), 'client@example.com')
        );
        self::assertStringContainsString('2', $ligneClient->text());
    }

    public function testLaRechercheRestreintLaListe(): void
    {
        $this->utilisateur('sophie.brunet@example.fr', []);
        $this->utilisateur('david.marchand@example.fr', []);
        $this->utilisateur('admin@example.com', ['ROLE_ADMIN']);

        $this->connecter('admin@example.com');
        $crawler = $this->client->request('GET', '/admin/utilisateurs?recherche=sophie');

        self::assertCount(1, $crawler->filter('tbody tr'));
    }

    public function testUnClientPeutEtrePromuEmploye(): void
    {
        $client = $this->utilisateur('client@example.com', []);
        $id = $client->getId();
        $this->utilisateur('admin@example.com', ['ROLE_ADMIN']);

        $this->connecter('admin@example.com');
        $this->soumettre('/admin/utilisateurs/'.$id.'/role', 'role-utilisateur-'.$id, ['role' => 'ROLE_EMPLOYE']);

        $this->em->clear();
        self::assertContains('ROLE_EMPLOYE', $this->em->getRepository(Utilisateur::class)->find($id)->getRoles());
    }

    public function testUnAdministrateurNeSeRetirePasSesPropresDroits(): void
    {
        // Sinon il se verrouille dehors, et plus personne n'administre le site.
        $moi = $this->utilisateur('admin@example.com', ['ROLE_ADMIN']);
        $this->utilisateur('autre.admin@example.com', ['ROLE_ADMIN']);
        $id = $moi->getId();

        $this->connecter('admin@example.com');
        $this->soumettre('/admin/utilisateurs/'.$id.'/role', 'role-utilisateur-'.$id, ['role' => '']);

        $this->em->clear();
        self::assertContains('ROLE_ADMIN', $this->em->getRepository(Utilisateur::class)->find($id)->getRoles());
    }

    public function testUnAdministrateurNeDesactivePasSonPropreCompte(): void
    {
        $moi = $this->utilisateur('admin@example.com', ['ROLE_ADMIN']);
        $id = $moi->getId();

        $this->connecter('admin@example.com');
        $this->soumettre('/admin/utilisateurs/'.$id.'/activation', 'activation-utilisateur-'.$id, []);

        $this->em->clear();
        self::assertTrue($this->em->getRepository(Utilisateur::class)->find($id)->isActif());
    }

    public function testLeDernierAdministrateurActifNePerdPasSonRole(): void
    {
        $seul = $this->utilisateur('admin@example.com', ['ROLE_ADMIN']);
        $autre = $this->utilisateur('autre@example.com', ['ROLE_ADMIN']);
        $idSeul = $seul->getId();

        // On désactive le second : il ne reste qu'un administrateur actif.
        $autre->setActif(false);
        $this->em->flush();

        $this->connecter('admin@example.com');
        $this->soumettre('/admin/utilisateurs/'.$idSeul.'/role', 'role-utilisateur-'.$idSeul, ['role' => 'ROLE_EMPLOYE']);

        $this->em->clear();
        self::assertContains('ROLE_ADMIN', $this->em->getRepository(Utilisateur::class)->find($idSeul)->getRoles());
    }

    public function testUnCompteDesactiveNePeutPlusSeConnecter(): void
    {
        // Vérification de bout en bout : la désactivation depuis l'admin
        // rejoint bien le blocage posé par UtilisateurChecker.
        $client = $this->utilisateur('client@example.com', []);
        $id = $client->getId();
        $this->utilisateur('admin@example.com', ['ROLE_ADMIN']);

        $this->connecter('admin@example.com');
        $this->soumettre('/admin/utilisateurs/'.$id.'/activation', 'activation-utilisateur-'.$id, []);

        $this->em->clear();
        self::assertFalse($this->em->getRepository(Utilisateur::class)->find($id)->isActif());

        $this->client->restart();
        $this->client->request('GET', '/connexion');
        $this->client->submitForm('Se connecter', [
            'email' => 'client@example.com',
            'password' => self::MOT_DE_PASSE,
        ]);

        self::assertResponseRedirects('/connexion');
        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('désactivé', $crawler->filter('.encart-alerte')->text());
    }

    public function testUneActionSansJetonCsrfEstRefusee(): void
    {
        $client = $this->utilisateur('client@example.com', []);
        $id = $client->getId();
        $this->utilisateur('admin@example.com', ['ROLE_ADMIN']);

        $this->connecter('admin@example.com');
        $this->client->request('POST', '/admin/utilisateurs/'.$id.'/role', ['role' => 'ROLE_ADMIN']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->em->clear();
        self::assertNotContains('ROLE_ADMIN', $this->em->getRepository(Utilisateur::class)->find($id)->getRoles());
    }

    // --- Fixtures ---------------------------------------------------------

    private function soumettre(string $url, string $jeton, array $donnees): void
    {
        $crawler = $this->client->request('GET', '/admin/utilisateurs');
        $formulaire = $crawler->filter(sprintf('form[action="%s"]', $url))->form();

        foreach ($donnees as $champ => $valeur) {
            $formulaire[$champ] = $valeur;
        }

        $this->client->submit($formulaire);
    }

    private function utilisateur(string $email, array $roles): Utilisateur
    {
        $u = new Utilisateur();
        $u->setEmail($email)->setNom('Nom')->setPrenom('Prénom')->setRoles($roles)->setActif(true);
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

    private function commande(Utilisateur $client): void
    {
        $theme = $this->em->getRepository(Theme::class)->findOneBy([]) ?? (new Theme())->setLibelle('T');
        $regime = $this->em->getRepository(Regime::class)->findOneBy([]) ?? (new Regime())->setLibelle('R');
        $this->em->persist($theme);
        $this->em->persist($regime);

        $menu = (new Menu())->setTitre('Menu '.uniqid())->setDescription('D.')
            ->setTheme($theme)->setRegime($regime)
            ->setNbMinPersonnes(6)->setPrixMin('24.00')->setDelaiCommandeJours(3)->setStock(5);
        $this->em->persist($menu);

        $c = (new Commande())->setUtilisateur($client)->setMenu($menu)
            ->setDateCommande(new \DateTime())->setDatePrestation(new \DateTime('+10 days'))
            ->setHeureLivraison(new \DateTime('12:00'))->setLieuLivraison('Bordeaux')
            ->setNbPersonnes(8)->setPrixTotal('192.00')
            ->setStatut(Commande::CONFIRMEE)->setPretMateriel(false);
        $this->em->persist($c);
        $this->em->flush();
    }
}
