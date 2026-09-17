<?php

namespace App\Tests\Functional;

use App\Entity\Horaire;
use App\Entity\Utilisateur;
use App\Repository\HoraireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Horaires d'ouverture : ordre de la semaine, jours de fermeture,
 * administration et publication en pied de page.
 */
class AdminHoraireTest extends WebTestCase
{
    private const MOT_DE_PASSE = 'Motdepasse&33!';

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private HoraireRepository $depot;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->depot = static::getContainer()->get(HoraireRepository::class);

        $st = new SchemaTool($this->em);
        $md = $this->em->getMetadataFactory()->getAllMetadata();
        $st->dropSchema($md);
        $st->createSchema($md);
    }

    // --- Ordre de la semaine ----------------------------------------------

    public function testLaSemaineSortDansLOrdreChronologique(): void
    {
        // Insérés dans le désordre : un tri alphabétique sur `jour` donnerait
        // Dimanche, Jeudi, Lundi, Mardi…
        foreach (['Jeudi', 'Dimanche', 'Lundi', 'Mardi', 'Samedi', 'Mercredi', 'Vendredi'] as $jour) {
            $this->horaire($jour);
        }

        $ordre = array_map(fn (Horaire $h) => $h->getJour(), $this->depot->semaine());

        self::assertSame(Horaire::JOURS, $ordre);
    }

    public function testLHoraireEstRetrouveDepuisUneDate(): void
    {
        $this->horaire('Mercredi', '10:00', '17:00');

        // 2026-09-16 est un mercredi.
        $mercredi = new \DateTime('2026-09-16 12:00');

        self::assertSame('Mercredi', $this->depot->pourLeJour($mercredi)?->getJour());
        self::assertTrue($this->depot->estOuvert($mercredi));
        self::assertFalse($this->depot->estOuvert(new \DateTime('2026-09-16 18:30')));
        // L'heure de fermeture est exclue : à 17:00 pile, c'est fermé.
        self::assertFalse($this->depot->estOuvert(new \DateTime('2026-09-16 17:00')));
        // Jeudi n'est pas renseigné du tout.
        self::assertNull($this->depot->pourLeJour(new \DateTime('2026-09-17 12:00')));
        self::assertFalse($this->depot->estOuvert(new \DateTime('2026-09-17 12:00')));
    }

    public function testUnJourFermeNAPasDHeures(): void
    {
        $dimanche = $this->horaire('Dimanche', '09:00', '18:00');
        $dimanche->setFerme(true);
        $this->em->flush();

        self::assertNull($dimanche->getHeureOuverture());
        self::assertNull($dimanche->getHeureFermeture());
        self::assertSame('Fermé', $dimanche->plage());
        self::assertFalse($dimanche->couvre(new \DateTime('2026-09-20 12:00')));
    }

    // --- Contrôle d'accès -------------------------------------------------

    public function testLesHorairesSontFermesAuxVisiteursAnonymes(): void
    {
        $this->client->request('GET', '/admin/horaires');

        self::assertResponseRedirects();
        self::assertStringContainsString('/connexion', $this->client->getResponse()->headers->get('Location') ?? '');
    }

    public function testLesHorairesSontOuvertsAuPersonnel(): void
    {
        // Le cahier des charges confie les horaires à l'employé, au même titre
        // que les menus. Ce test affirmait l'inverse tant que /admin était
        // fermé au personnel.
        $this->connecter($this->utilisateur('employe@example.com', ['ROLE_EMPLOYE']));

        $this->client->request('GET', '/admin/horaires');
        self::assertResponseIsSuccessful();
    }

    public function testLesHorairesRestentFermesAuxClients(): void
    {
        $this->connecter($this->utilisateur('client@example.com', []));

        $this->client->request('GET', '/admin/horaires');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // --- Administration ---------------------------------------------------

    public function testUnAdministrateurRenseigneUnJour(): void
    {
        $this->connecterAdmin();

        $this->client->request('GET', '/admin/horaires/nouveau');
        $this->client->submitForm('Enregistrer', [
            'horaire[jour]' => 'Lundi',
            'horaire[heureOuverture]' => '09:00',
            'horaire[heureFermeture]' => '18:00',
        ]);

        self::assertResponseRedirects('/admin/horaires');

        $lundi = $this->depot->findOneBy(['jour' => 'Lundi']);

        self::assertNotNull($lundi);
        self::assertSame('09:00 – 18:00', $lundi->plage());
        self::assertSame(0, $lundi->getOrdre());
    }

    public function testUnJourDejaRenseigneNEstPlusProposeALaCreation(): void
    {
        $this->horaire('Lundi');
        $this->connecterAdmin();

        $crawler = $this->client->request('GET', '/admin/horaires/nouveau');
        $proposes = $crawler->filter('#horaire_jour option')->each(fn ($n) => $n->attr('value'));

        self::assertNotContains('Lundi', $proposes);
        self::assertContains('Mardi', $proposes);
    }

    public function testUneFermetureAvantLOuvertureEstRefusee(): void
    {
        $this->connecterAdmin();

        $this->client->request('GET', '/admin/horaires/nouveau');
        $this->client->submitForm('Enregistrer', [
            'horaire[jour]' => 'Mardi',
            'horaire[heureOuverture]' => '18:00',
            'horaire[heureFermeture]' => '09:00',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(0, $this->depot->count([]));
    }

    public function testUnJourSansHeureNiFermetureEstRefuse(): void
    {
        $this->connecterAdmin();

        $this->client->request('GET', '/admin/horaires/nouveau');
        $this->client->submitForm('Enregistrer', ['horaire[jour]' => 'Mardi']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(0, $this->depot->count([]));
    }

    public function testUnAdministrateurFermeUnJour(): void
    {
        $id = $this->horaire('Dimanche', '09:00', '13:00')->getId();
        $this->connecterAdmin();

        $this->client->request('GET', '/admin/horaires/'.$id.'/modifier');
        $this->client->submitForm('Enregistrer les modifications', [
            'horaire[jour]' => 'Dimanche',
            'horaire[ferme]' => '1',
        ]);

        self::assertResponseRedirects('/admin/horaires');

        $this->em->clear();
        $dimanche = $this->depot->find($id);

        self::assertTrue($dimanche->isFerme());
        self::assertSame('Fermé', $dimanche->plage());
    }

    public function testLeJourEnCoursDeModificationResteProposable(): void
    {
        $id = $this->horaire('Lundi')->getId();
        $this->connecterAdmin();

        $crawler = $this->client->request('GET', '/admin/horaires/'.$id.'/modifier');
        $proposes = $crawler->filter('#horaire_jour option')->each(fn ($n) => $n->attr('value'));

        self::assertContains('Lundi', $proposes);
    }

    public function testLaSuppressionExigeUnJetonCsrf(): void
    {
        $id = $this->horaire('Lundi')->getId();
        $this->connecterAdmin();

        $this->client->request('POST', '/admin/horaires/'.$id.'/supprimer');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame(1, $this->depot->count([]));
    }

    // --- Publication ------------------------------------------------------

    public function testLesHorairesSAffichentEnPiedDePage(): void
    {
        $this->horaire('Lundi', '09:00', '18:00');
        $this->horaire('Dimanche')->setFerme(true);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/contact');

        self::assertResponseIsSuccessful();
        $pied = $crawler->filter('footer')->text();

        self::assertStringContainsString('09:00 – 18:00', $pied);
        self::assertStringContainsString('Fermé', $pied);
    }

    // --- Fixtures ---------------------------------------------------------

    private function horaire(string $jour, ?string $ouverture = '09:00', ?string $fermeture = '18:00'): Horaire
    {
        $h = (new Horaire())->setJour($jour);

        if (null !== $ouverture) {
            $h->setHeureOuverture(new \DateTime($ouverture))->setHeureFermeture(new \DateTime($fermeture));
        }

        $this->em->persist($h);
        $this->em->flush();

        return $h;
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

    private function connecterAdmin(): void
    {
        $this->connecter($this->utilisateur('admin@example.com', ['ROLE_ADMIN']));
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
