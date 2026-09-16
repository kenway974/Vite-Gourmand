<?php

namespace App\Tests\Functional;

use App\Entity\Commande;
use App\Entity\Menu;
use App\Entity\Regime;
use App\Entity\Theme;
use App\Entity\Utilisateur;
use App\Entity\ZoneLivraison;
use App\Repository\CommandeRepository;
use App\Service\CalculateurPrix;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Zones de livraison : supplément, refus hors zone, administration.
 *
 * « Le prix inclut la livraison, le dressage et la reprise du matériel dans
 * un rayon de Bordeaux intra-muros. »
 */
class ZoneLivraisonTest extends WebTestCase
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

        $menu = (new Menu())
            ->setTitre('Buffet bordelais')
            ->setDescription('Une formule de saison.')
            ->setTheme($theme)->setRegime($regime)
            ->setPrixMin('40.00')->setNbMinPersonnes(10)
            ->setDelaiCommandeJours(3)->setStock(20);
        $this->em->persist($menu);
        $this->em->flush();

        $this->menuId = $menu->getId();

        $this->zone('33000', 'Bordeaux', '0.00');
        $this->zone('33700', 'Mérignac', '35.00');
    }

    // --- Calcul -----------------------------------------------------------

    public function testLaLivraisonEstComprisePourBordeaux(): void
    {
        $detail = $this->calculer(10, '33000');

        self::assertEqualsWithDelta(0.0, (float) $detail->fraisLivraison, 0.001);
        self::assertTrue($detail->livraisonComprise());
        self::assertEqualsWithDelta(400.0, (float) $detail->montantTotal, 0.001);
    }

    public function testLeSupplementSAjouteApresLaRemise(): void
    {
        // 15 convives : le seuil de remise (10 + 5) est atteint.
        // 15 × 40 = 600, moins 10 % = 540, plus 35 de livraison = 575.
        $detail = $this->calculer(15, '33700');

        self::assertTrue($detail->beneficieDeLaRemise());
        self::assertEqualsWithDelta(600.0, (float) $detail->montantBrut, 0.001);
        self::assertEqualsWithDelta(60.0, (float) $detail->montantRemise, 0.001);
        self::assertEqualsWithDelta(35.0, (float) $detail->fraisLivraison, 0.001);

        // La remise porte sur les prestations, pas sur le transport : si le
        // supplément entrait dans l'assiette, le total tomberait à 571,50.
        self::assertEqualsWithDelta(575.0, (float) $detail->montantTotal, 0.001);
    }

    public function testSansZoneLaLivraisonEstTraiteeCommeComprise(): void
    {
        // Le refus d'une adresse non desservie relève de la validation de la
        // commande, pas du calcul du prix.
        $detail = $this->calculer(10, null);

        self::assertEqualsWithDelta(400.0, (float) $detail->montantTotal, 0.001);
    }

    // --- Commande ---------------------------------------------------------

    public function testUneCommandeHorsZoneEstRefusee(): void
    {
        $this->connecter();

        $this->soumettre('75001');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('body', 'Nous ne livrons pas encore le 75001');
        self::assertSame(0, $this->em->getRepository(Commande::class)->count([]));
    }

    public function testUnCodePostalMalFormeEstRefuse(): void
    {
        $this->connecter();

        $this->soumettre('33');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('body', 'cinq chiffres');
        // Un seul message : la contrainte de zone ne s'empile pas sur celle
        // du format.
        self::assertSelectorTextNotContains('body', 'Nous ne livrons pas encore');
    }

    public function testLeSupplementEstEnregistreSurLaCommande(): void
    {
        $this->connecter();

        $this->soumettre('33700');

        self::assertResponseRedirects();

        $commande = $this->em->getRepository(Commande::class)->findOneBy([]);

        self::assertSame('33700', $commande->getCodePostalLivraison());
        self::assertEqualsWithDelta(35.0, (float) $commande->getFraisLivraison(), 0.001);
        // 10 convives : pas de remise. 400 + 35.
        self::assertEqualsWithDelta(435.0, (float) $commande->getPrixTotal(), 0.001);
    }

    public function testUnSupplementGlisseDansLeFormulaireEstRejete(): void
    {
        $this->connecter();

        // Le supplément n'est pas un champ du formulaire : il est relu en base
        // au moment du calcul. Une soumission qui en ajoute un est refusée en
        // bloc — Symfony n'accepte pas les champs hors définition.
        $crawler = $this->client->request('GET', '/commander/'.$this->menuId);
        $formulaire = $crawler->selectButton('Envoyer ma demande')->form([
            'commande[datePrestation]' => (new \DateTime('+10 days'))->format('Y-m-d'),
            'commande[heureLivraison]' => '12:00',
            'commande[lieuLivraison]' => '5 avenue de la Libération, Mérignac',
            'commande[codePostalLivraison]' => '33700',
            'commande[nbPersonnes]' => '10',
        ]);

        // On rejoue le formulaire légitime — jeton CSRF compris — en y
        // glissant un champ qu'il n'expose pas.
        $valeurs = $formulaire->getPhpValues();
        $valeurs['commande']['fraisLivraison'] = '0.00';

        $this->client->request('POST', $formulaire->getUri(), $valeurs);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(0, $this->em->getRepository(Commande::class)->count([]));
    }

    public function testLeSupplementEstReluEnBaseEtNonRepriseDuFormulaire(): void
    {
        $this->connecter();
        $this->soumettre('33700');

        $commande = $this->em->getRepository(Commande::class)->findOneBy([]);

        // La zone a été retrouvée par son code postal côté serveur : le
        // montant vient de la table, pas de la requête.
        self::assertEqualsWithDelta(35.0, (float) $commande->getFraisLivraison(), 0.001);
        self::assertEqualsWithDelta(
            (float) $this->em->getRepository(ZoneLivraison::class)
                ->findOneBy(['codePostal' => '33700'])->getFrais(),
            (float) $commande->getFraisLivraison(),
            0.001,
        );
    }

    // --- Administration ---------------------------------------------------

    public function testLesZonesSontReserveesAAdministration(): void
    {
        $this->connecter();

        $this->client->request('GET', '/admin/zones');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testUnAdministrateurAjouteUneCommune(): void
    {
        $this->connecterAdmin();

        $this->client->request('GET', '/admin/zones/nouveau');
        $this->client->submitForm('Enregistrer', [
            'zone_livraison[commune]' => 'Pessac',
            'zone_livraison[codePostal]' => '33600',
            'zone_livraison[frais]' => '30',
        ]);

        self::assertResponseRedirects('/admin/zones');

        $zone = $this->em->getRepository(ZoneLivraison::class)->findOneBy(['codePostal' => '33600']);

        self::assertSame('Pessac', $zone->getCommune());
        self::assertEqualsWithDelta(30.0, (float) $zone->getFrais(), 0.001);
    }

    public function testUnCodePostalDejaRattacheEstRefuse(): void
    {
        $this->connecterAdmin();

        $this->client->request('GET', '/admin/zones/nouveau');
        $this->client->submitForm('Enregistrer', [
            'zone_livraison[commune]' => 'Bordeaux bis',
            'zone_livraison[codePostal]' => '33000',
            'zone_livraison[frais]' => '10',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(2, $this->em->getRepository(ZoneLivraison::class)->count([]));
    }

    public function testUneZoneAvecDesCommandesEnCoursNEstPasRetiree(): void
    {
        $this->connecter();
        $this->soumettre('33700');
        $this->client->getCookieJar()->clear();

        $this->connecterAdmin();
        $crawler = $this->client->request('GET', '/admin/zones');
        $id = $this->em->getRepository(ZoneLivraison::class)->findOneBy(['codePostal' => '33700'])->getId();

        $this->client->submit($crawler->filter('form[action="/admin/zones/'.$id.'/supprimer"] button')->form());
        $this->client->followRedirect();

        self::assertSelectorTextContains('body', 'ne peut pas être retirée');
        self::assertSame(2, $this->em->getRepository(ZoneLivraison::class)->count([]));
    }

    public function testUneCommandeLivreeNEmpechePlusDeRetirerLaZone(): void
    {
        $this->connecter();
        $this->soumettre('33700');

        $commande = $this->em->getRepository(Commande::class)->findOneBy([]);
        $commande->setStatut(Commande::LIVREE);
        $this->em->flush();

        $depot = static::getContainer()->get(CommandeRepository::class);

        self::assertSame(0, $depot->compterEnCoursPour('33700'));
    }

    // --- Fixtures ---------------------------------------------------------

    private function calculer(int $convives, ?string $codePostal)
    {
        $menu = $this->em->getRepository(Menu::class)->find($this->menuId);
        $zone = null === $codePostal
            ? null
            : $this->em->getRepository(ZoneLivraison::class)->findOneBy(['codePostal' => $codePostal]);

        return static::getContainer()->get(CalculateurPrix::class)->calculer($menu, $convives, $zone);
    }

    private function soumettre(string $codePostal, int $convives = 10): void
    {
        $this->client->request('GET', '/commander/'.$this->menuId);
        $this->client->submitForm('Envoyer ma demande', [
            'commande[datePrestation]' => (new \DateTime('+10 days'))->format('Y-m-d'),
            'commande[heureLivraison]' => '12:00',
            'commande[lieuLivraison]' => 'Une adresse quelque part',
            'commande[codePostalLivraison]' => $codePostal,
            'commande[nbPersonnes]' => (string) $convives,
        ]);
    }

    private function zone(string $codePostal, string $commune, string $frais): ZoneLivraison
    {
        $z = (new ZoneLivraison())
            ->setCodePostal($codePostal)
            ->setCommune($commune)
            ->setFrais($frais);

        $this->em->persist($z);
        $this->em->flush();

        return $z;
    }

    private function connecter(): void
    {
        $this->seConnecter($this->utilisateur('client@example.com', []));
    }

    private function connecterAdmin(): void
    {
        $this->seConnecter($this->utilisateur('admin@example.com', ['ROLE_ADMIN']));
    }

    private function utilisateur(string $email, array $roles): Utilisateur
    {
        $existant = $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);

        if (null !== $existant) {
            return $existant;
        }

        $u = new Utilisateur();
        $u->setEmail($email)->setNom('T')->setPrenom('U')->setRoles($roles)->setActif(true);
        $u->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)
            ->hashPassword($u, self::MOT_DE_PASSE));
        $this->em->persist($u);
        $this->em->flush();

        return $u;
    }

    private function seConnecter(Utilisateur $utilisateur): void
    {
        $this->client->request('GET', '/connexion');
        $this->client->submitForm('Se connecter', [
            'email' => $utilisateur->getEmail(),
            'password' => self::MOT_DE_PASSE,
        ]);
        $this->client->followRedirect();
    }
}
