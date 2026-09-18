<?php

namespace App\Tests\Functional;

use App\Entity\Utilisateur;
use App\Security\ReinitialisationMotDePasse;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Mot de passe oublié : demande, courriel, jeton à usage unique.
 */
class MotDePasseOublieTest extends WebTestCase
{
    private const MOT_DE_PASSE = 'Motdepasse&33!';
    private const NOUVEAU = 'NouveauMotdepasse&33!';

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

    // --- Demande ----------------------------------------------------------

    public function testLeFormulaireEstAccessibleSansConnexion(): void
    {
        $this->client->request('GET', '/mot-de-passe-oublie');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mot de passe oublié');
    }

    public function testUneDemandeEnvoieUnCourrielEtPoseUnJeton(): void
    {
        $id = $this->utilisateur('client@example.com')->getId();

        $this->demander('client@example.com');

        // Le courriel part par Messenger (messenger.yaml route SendEmailMessage
        // vers async) : il est mis en file, pas expédié pendant la requête.
        // C'est voulu — la réponse HTTP n'attend pas le serveur SMTP.
        self::assertQueuedEmailCount(1);

        $this->em->clear();
        $client = $this->em->getRepository(Utilisateur::class)->find($id);

        self::assertNotNull($client->getJetonReinitialisation());
        self::assertTrue($client->reinitialisationEnCours());

        // Ce qui est stocké est une empreinte, pas le jeton : 64 caractères
        // hexadécimaux, et le lien du courriel ne s'y retrouve pas tel quel.
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $client->getJetonReinitialisation());
        self::assertStringNotContainsString(
            $client->getJetonReinitialisation(),
            $this->lienRecu(),
        );
    }

    public function testUneAdresseInconnueNeRevelePasQuElleEstInconnue(): void
    {
        $this->utilisateur('client@example.com');

        $this->demander('client@example.com');
        $connue = $this->client->getResponse()->getContent();

        $this->demander('personne@example.com');
        $inconnue = $this->client->getResponse()->getContent();

        // Même page, mot pour mot : sans quoi le formulaire deviendrait un
        // annuaire des comptes existants.
        self::assertSame($connue, $inconnue);
        self::assertQueuedEmailCount(0);
    }

    public function testUnCompteDesactiveNeRecoitPasDeLien(): void
    {
        $utilisateur = $this->utilisateur('banni@example.com');
        $utilisateur->setActif(false);
        $this->em->flush();

        $this->demander('banni@example.com');

        // Rouvrir un chemin par la réinitialisation contournerait la
        // désactivation du compte.
        self::assertQueuedEmailCount(0);
    }

    // --- Réinitialisation -------------------------------------------------

    public function testLeLienRecuPermetDeChoisirUnNouveauMotDePasse(): void
    {
        $this->utilisateur('client@example.com');
        $this->demander('client@example.com');

        $this->client->request('GET', $this->lienRecu());
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Enregistrer le nouveau mot de passe', [
            'nouveau_mot_de_passe[nouveau][first]' => self::NOUVEAU,
            'nouveau_mot_de_passe[nouveau][second]' => self::NOUVEAU,
        ]);

        self::assertResponseRedirects('/connexion');
        self::assertTrue($this->motDePasseValide('client@example.com', self::NOUVEAU));
        self::assertFalse($this->motDePasseValide('client@example.com', self::MOT_DE_PASSE));
    }

    public function testLeJetonNeSertQuUneFois(): void
    {
        $this->utilisateur('client@example.com');
        $this->demander('client@example.com');
        $lien = $this->lienRecu();

        $this->client->request('GET', $lien);
        $this->client->submitForm('Enregistrer le nouveau mot de passe', [
            'nouveau_mot_de_passe[nouveau][first]' => self::NOUVEAU,
            'nouveau_mot_de_passe[nouveau][second]' => self::NOUVEAU,
        ]);

        // Le jeton est consommé : rejouer le lien ne doit plus rien ouvrir.
        $this->client->request('GET', $lien);
        self::assertResponseRedirects('/mot-de-passe-oublie');
    }

    public function testUnJetonExpireEstRefuse(): void
    {
        $id = $this->utilisateur('client@example.com')->getId();
        $this->demander('client@example.com');
        $lien = $this->lienRecu();

        $this->em->clear();
        $client = $this->em->getRepository(Utilisateur::class)->find($id);
        $client->demanderReinitialisation(
            $client->getJetonReinitialisation(),
            new \DateTimeImmutable('-1 minute'),
        );
        $this->em->flush();

        $this->client->request('GET', $lien);

        self::assertResponseRedirects('/mot-de-passe-oublie');
        self::assertTrue($this->motDePasseValide('client@example.com', self::MOT_DE_PASSE));
    }

    public function testUnJetonInventeEstRefuse(): void
    {
        $this->utilisateur('client@example.com');

        $this->client->request('GET', '/mot-de-passe-oublie/'.str_repeat('a', 64));
        self::assertResponseRedirects('/mot-de-passe-oublie');

        // Hors format, la route ne correspond même pas.
        $this->client->request('GET', '/mot-de-passe-oublie/pas-un-jeton');
        self::assertResponseStatusCodeSame(404);
    }

    public function testUnMotDePasseFaibleEstRefuse(): void
    {
        $this->utilisateur('client@example.com');
        $this->demander('client@example.com');

        $this->client->request('GET', $this->lienRecu());
        $this->client->submitForm('Enregistrer le nouveau mot de passe', [
            'nouveau_mot_de_passe[nouveau][first]' => 'motdepasse',
            'nouveau_mot_de_passe[nouveau][second]' => 'motdepasse',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertTrue($this->motDePasseValide('client@example.com', self::MOT_DE_PASSE));
    }

    public function testLaDureeDeValiditeEstCelleAnnoncee(): void
    {
        $id = $this->utilisateur('client@example.com')->getId();
        $this->demander('client@example.com');

        $this->em->clear();
        $expiration = $this->em->getRepository(Utilisateur::class)->find($id)->getJetonExpiration();
        $attendue = new \DateTime(sprintf('+%d minutes', ReinitialisationMotDePasse::VALIDITE_MINUTES));

        self::assertEqualsWithDelta($attendue->getTimestamp(), $expiration->getTimestamp(), 60);
    }

    // --- Fixtures ---------------------------------------------------------

    private function demander(string $email): void
    {
        $this->client->request('GET', '/mot-de-passe-oublie');
        $this->client->submitForm('Envoyer le lien', ['demande_reinitialisation[email]' => $email]);
    }

    /**
     * Extrait le lien de réinitialisation du dernier courriel intercepté.
     */
    private function lienRecu(): string
    {
        $corps = $this->getMailerMessage()->getHtmlBody();

        self::assertMatchesRegularExpression('#/mot-de-passe-oublie/[a-f0-9]{64}#', $corps);
        preg_match('#(/mot-de-passe-oublie/[a-f0-9]{64})#', $corps, $trouve);

        return $trouve[1];
    }

    private function motDePasseValide(string $email, string $motDePasse): bool
    {
        $this->em->clear();
        $utilisateur = $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);

        return static::getContainer()->get(UserPasswordHasherInterface::class)
            ->isPasswordValid($utilisateur, $motDePasse);
    }

    private function utilisateur(string $email): Utilisateur
    {
        $u = new Utilisateur();
        $u->setEmail($email)->setNom('Lartigue')->setPrenom('Camille')->setRoles([])->setActif(true);
        $u->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)
            ->hashPassword($u, self::MOT_DE_PASSE));
        $this->em->persist($u);
        $this->em->flush();

        return $u;
    }
}
