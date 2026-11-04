<?php

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Finder\Finder;

/**
 * En-têtes de sécurité et Content-Security-Policy (EnTetesSecuriteSubscriber).
 */
class EnTetesSecuriteTest extends WebTestCase
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

    public function testLesEnTetesDeSecuriteSontPoses(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
        self::assertResponseHeaderSame('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = $this->client->getResponse()->headers->get('Content-Security-Policy');
        self::assertNotNull($csp);
        self::assertStringContainsString("default-src 'self'", $csp);
        self::assertStringContainsString("object-src 'none'", $csp);
        self::assertStringContainsString("frame-ancestors 'none'", $csp);
        self::assertStringNotContainsString('unsafe-eval', $csp);
        self::assertDoesNotMatchRegularExpression("/script-src[^;]*'unsafe-inline'/", $csp);
    }

    public function testLesPagesDErreurPortentAussiLesEnTetes(): void
    {
        $this->client->request('GET', '/page-qui-n-existe-pas');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        // Pas d'assertion sur la CSP : en mode debug, Symfony la retire des
        // pages d'erreur (ErrorListener::removeCspHeader) pour que sa page
        // d'exception reste utilisable. En production, elle est conservée.
    }

    public function testChaqueScriptPorteLeNonceAutoriseParLaPolitique(): void
    {
        $crawler = $this->client->request('GET', '/');
        $csp = $this->client->getResponse()->headers->get('Content-Security-Policy');

        self::assertMatchesRegularExpression("/script-src 'self' 'nonce-([A-Za-z0-9+\\/=]+)'/", $csp);
        preg_match("/'nonce-([A-Za-z0-9+\\/=]+)'/", $csp, $m);
        $nonce = $m[1];

        $scripts = $crawler->filter('script');
        self::assertGreaterThan(0, $scripts->count());
        foreach ($scripts as $script) {
            self::assertSame($nonce, $script->getAttribute('nonce'), 'Script sans le nonce de la page : la CSP le bloquerait.');
        }

        // Turbo lit ce nonce pour les éléments qu'il insère lui-même.
        self::assertSame($nonce, $crawler->filter('meta[name="csp-nonce"]')->attr('content'));

        // Un import de CSS dans le JavaScript devient un module « data: » dans
        // l'importmap, que script-src bloque : tout le JavaScript tomberait.
        self::assertStringNotContainsString('data:application/javascript', $this->client->getResponse()->getContent());
    }

    public function testLeNonceChangeAChaqueRequete(): void
    {
        $this->client->request('GET', '/');
        $premier = $this->client->getResponse()->headers->get('Content-Security-Policy');

        $this->client->request('GET', '/');
        $second = $this->client->getResponse()->headers->get('Content-Security-Policy');

        self::assertNotSame($premier, $second);
    }

    public function testHstsNEstEnvoyeQuEnHttps(): void
    {
        $this->client->request('GET', '/');
        self::assertResponseNotHasHeader('Strict-Transport-Security');

        $this->client->request('GET', 'https://localhost/');
        self::assertResponseHeaderSame('Strict-Transport-Security', 'max-age=31536000');
    }

    public function testAucunGabaritNeContientDeJavaScriptEnLigne(): void
    {
        // La CSP bloquerait silencieusement un onclick="…" ou un <script> sans
        // nonce : le bouton ne ferait plus rien, sans erreur côté serveur.
        $gabarits = (new Finder())->files()->in(\dirname(__DIR__, 2).'/templates')->name('*.twig');

        foreach ($gabarits as $gabarit) {
            $contenu = $gabarit->getContents();

            self::assertDoesNotMatchRegularExpression(
                '/<[a-z][^>]*\son[a-z]+\s*=/i',
                $contenu,
                $gabarit->getRelativePathname().' : gestionnaire d\'événement en ligne, à remplacer par un contrôleur Stimulus.',
            );
            self::assertStringNotContainsString('javascript:', $contenu, $gabarit->getRelativePathname());

            preg_match_all('/<script\b[^>]*>/i', $contenu, $balises);
            foreach ($balises[0] as $balise) {
                self::assertStringContainsString('nonce=', $balise, $gabarit->getRelativePathname().' : <script> sans nonce.');
            }
        }
    }
}
