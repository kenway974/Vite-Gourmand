<?php

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garanties du socle graphique, portées par base.html.twig.
 *
 * Ce ne sont pas des tests d'apparence — une couleur ou une marge ne se
 * teste pas utilement ici. Ce sont les promesses que le socle fait et qu'une
 * modification distraite pourrait casser sans que rien ne le signale.
 */
class SocleTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $st = new SchemaTool($em);
        $md = $em->getMetadataFactory()->getAllMetadata();
        $st->dropSchema($md);
        $st->createSchema($md);
    }

    public function testLaPageDeReferenceNExistePasHorsDeveloppement(): void
    {
        // Sa route est déclarée dans config/routes/dev/, que Symfony ne charge
        // qu'en développement. Si quelqu'un la déplaçait vers un attribut
        // #[Route], elle apparaîtrait en production sans que personne ne le
        // remarque : ce test est le filet.
        $this->client->request('GET', '/_socle');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAucunePoliceNEstChargeeDepuisUnTiers(): void
    {
        $this->client->request('GET', '/');
        $page = $this->client->getResponse()->getContent();

        // Les polices sont hébergées au dépôt. Les servir depuis Google
        // enverrait l'adresse IP de chaque visiteur à un tiers, ce que la
        // politique de confidentialité du site ne prévoit pas.
        self::assertStringNotContainsString('fonts.googleapis.com', $page);
        self::assertStringNotContainsString('fonts.gstatic.com', $page);
    }

    public function testChaquePagePorteLesReperesDAccessibilite(): void
    {
        foreach (['/', '/menus', '/avis', '/notre-histoire', '/contact'] as $chemin) {
            $crawler = $this->client->request('GET', $chemin);

            self::assertResponseIsSuccessful($chemin);

            // Lien d'évitement : première chose atteignable au clavier.
            self::assertCount(1, $crawler->filter('a.skip-link[href="#contenu"]'), $chemin);
            self::assertCount(1, $crawler->filter('main#contenu'), $chemin);

            // Une seule <h1> par page : c'est le repère principal d'un
            // lecteur d'écran, en avoir deux ou zéro le désoriente.
            self::assertCount(1, $crawler->filter('h1'), $chemin.' : une seule h1 attendue');

            // La navigation doit être nommée : un visiteur qui liste les
            // repères doit savoir laquelle il atteint.
            self::assertCount(1, $crawler->filter('nav[aria-label="Navigation principale"]'), $chemin);
        }
    }

    public function testLaPageCouranteEstSignaleeDansLaNavigation(): void
    {
        $crawler = $this->client->request('GET', '/avis');

        // aria-current plutôt qu'une simple classe de couleur : la couleur
        // seule ne dit rien à un lecteur d'écran, ni à qui ne la distingue pas.
        $courant = $crawler->filter('nav a[aria-current="page"]');

        self::assertCount(1, $courant);
        self::assertSame('Avis', trim($courant->text()));
    }

    public function testLaBasculeDuMenuEstAnnonceeEtRepliableAuClavier(): void
    {
        $crawler = $this->client->request('GET', '/');
        $bouton = $crawler->filter('button.bascule-menu');

        self::assertCount(1, $bouton);
        // Un <button>, pas un lien ni un div : l'espace et l'entrée doivent
        // l'actionner sans code supplémentaire.
        self::assertSame('button', $bouton->attr('type'));
        self::assertSame('false', $bouton->attr('aria-expanded'));
        self::assertSame('nav-principale', $bouton->attr('aria-controls'));
        // L'icône seule ne dit rien : un intitulé lui est adjoint.
        self::assertStringContainsString('Ouvrir le menu', $bouton->text());
    }

    public function testLePiedDePageMeneAuxPagesLegales(): void
    {
        $crawler = $this->client->request('GET', '/');

        foreach ([
            '/mentions-legales',
            '/conditions-generales-de-vente',
            '/politique-de-confidentialite',
        ] as $chemin) {
            self::assertGreaterThan(
                0,
                $crawler->filter(sprintf('footer a[href="%s"]', $chemin))->count(),
                $chemin.' doit rester atteignable depuis le pied de page',
            );
        }
    }
}
