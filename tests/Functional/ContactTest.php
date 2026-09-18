<?php

namespace App\Tests\Functional;

use App\Entity\Contact;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Formulaire de contact et traitement des messages.
 */
class ContactTest extends WebTestCase
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

    public function testLeFormulaireEstAccessibleSansConnexion(): void
    {
        $this->client->request('GET', '/contact');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Nous écrire');
    }

    public function testUnMessageEstEnregistreEnAttenteDeTraitement(): void
    {
        $this->client->request('GET', '/contact');
        $this->client->submitForm('Envoyer', [
            'contact[titre]' => 'Devis pour un mariage',
            'contact[email]' => 'futur.marie@example.fr',
            'contact[message]' => 'Nous cherchons un traiteur pour 80 convives en juin prochain.',
        ]);

        self::assertResponseRedirects('/contact');

        $message = $this->em->getRepository(Contact::class)->findOneBy([]);

        self::assertNotNull($message);
        self::assertFalse($message->isTraite());
        self::assertNull($message->getDateTraitement());
    }

    public function testUnMessageTropCourtEstRefuse(): void
    {
        $this->client->request('GET', '/contact');
        $this->client->submitForm('Envoyer', [
            'contact[titre]' => 'Bonjour',
            'contact[email]' => 'visiteur@example.fr',
            'contact[message]' => 'Salut',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(0, $this->em->getRepository(Contact::class)->count([]));
    }

    public function testUneAdresseInvalideEstRefusee(): void
    {
        $this->client->request('GET', '/contact');
        $this->client->submitForm('Envoyer', [
            'contact[titre]' => 'Question',
            'contact[email]' => 'pas-une-adresse',
            'contact[message]' => 'Livrez-vous jusqu\'au Cap Ferret ? Merci d\'avance.',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(0, $this->em->getRepository(Contact::class)->count([]));
    }

    public function testLAdresseDUnVisiteurConnecteEstPreremplie(): void
    {
        $this->utilisateur('client@example.com', []);
        $this->connecter('client@example.com');

        $crawler = $this->client->request('GET', '/contact');

        self::assertSame('client@example.com', $crawler->filter('#contact_email')->attr('value'));
    }

    // --- Traitement -------------------------------------------------------

    public function testLaFileDesMessagesEstReserveeAuPersonnel(): void
    {
        $this->utilisateur('client@example.com', []);
        $this->connecter('client@example.com');

        $this->client->request('GET', '/employe/messages');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testUnEmployeMarqueUnMessageCommeTraite(): void
    {
        $id = $this->message('-1 hour')->getId();
        $this->utilisateur('employe@example.com', ['ROLE_EMPLOYE']);
        $this->connecter('employe@example.com');

        $crawler = $this->client->request('GET', '/employe/messages');
        $this->client->submit($crawler->filter('form[action*="/traitement"] button')->form());

        $this->em->clear();
        $message = $this->em->getRepository(Contact::class)->find($id);

        self::assertTrue($message->isTraite());
        self::assertNotNull($message->getDateTraitement());
    }

    public function testRouvrirUnMessageEffaceSaDateDeTraitement(): void
    {
        $message = $this->message('-1 hour');
        $message->marquerTraite();
        $this->em->flush();
        $id = $message->getId();

        $this->utilisateur('employe@example.com', ['ROLE_EMPLOYE']);
        $this->connecter('employe@example.com');

        $crawler = $this->client->request('GET', '/employe/messages?traites=1');
        $this->client->submit($crawler->filter('form[action*="/traitement"] button')->form());

        $this->em->clear();
        $relu = $this->em->getRepository(Contact::class)->find($id);

        // Une date de traitement sur un message rouvert ne voudrait rien dire.
        self::assertFalse($relu->isTraite());
        self::assertNull($relu->getDateTraitement());
    }

    public function testLeDelaiDeQuaranteHuitHeuresEstSignale(): void
    {
        $recent = $this->message('-1 hour');
        $ancien = $this->message('-3 days');
        $traite = $this->message('-3 days');
        $traite->marquerTraite();

        self::assertFalse($recent->estEnRetard());
        self::assertTrue($ancien->estEnRetard());
        // Un message traité n'est jamais « en retard », quel que soit son âge.
        self::assertFalse($traite->estEnRetard());
    }

    // --- Fixtures ---------------------------------------------------------

    private function message(string $age): Contact
    {
        $c = (new Contact())
            ->setTitre('Question')
            ->setMessage('Bonjour, je souhaiterais des renseignements sur vos formules.')
            ->setEmail('visiteur@example.fr')
            ->setDateCreation(new \DateTime($age));

        $this->em->persist($c);
        $this->em->flush();

        return $c;
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

    private function connecter(string $email): void
    {
        $this->client->request('GET', '/connexion');
        $this->client->submitForm('Se connecter', ['email' => $email, 'password' => self::MOT_DE_PASSE]);
        $this->client->followRedirect();
    }
}
