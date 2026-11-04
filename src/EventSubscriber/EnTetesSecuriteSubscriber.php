<?php

namespace App\EventSubscriber;

use App\Security\NonceCsp;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * En-têtes de sécurité posés sur toutes les réponses.
 *
 * La Content-Security-Policy n'autorise que les scripts servis par le site et
 * ceux qui portent le nonce de la requête (importmap() en reçoit un dans
 * base.html.twig). Un script injecté dans une page ne s'exécute donc pas.
 *
 * Les styles en ligne restent permis : plusieurs gabarits utilisent l'attribut
 * style, et la barre de progression de Turbo insère une balise <style>. Ils
 * n'exécutent pas de code ; le risque qu'ils représentent est sans commune
 * mesure avec celui des scripts.
 *
 * En développement, la CSP n'est pas envoyée : le rechargement à chaud de
 * FrankenPHP charge ses scripts depuis jsDelivr. Les autres en-têtes, si.
 */
final class EnTetesSecuriteSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NonceCsp $nonce,
        #[Autowire('%kernel.environment%')]
        private readonly string $environnement,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Priorité positive : passe avant la barre de débogage (-128), qui
        // complète elle-même la CSP pour ses propres scripts.
        return [KernelEvents::RESPONSE => ['poserEnTetes', 10]];
    }

    public function poserEnTetes(ResponseEvent $evenement): void
    {
        if (!$evenement->isMainRequest()) {
            return;
        }

        $enTetes = $evenement->getResponse()->headers;

        $enTetes->set('X-Content-Type-Options', 'nosniff');
        $enTetes->set('X-Frame-Options', 'DENY');
        $enTetes->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if ($evenement->getRequest()->isSecure()) {
            $enTetes->set('Strict-Transport-Security', 'max-age=31536000');
        }

        if ('dev' !== $this->environnement && !$enTetes->has('Content-Security-Policy')) {
            $enTetes->set('Content-Security-Policy', $this->politique());
        }
    }

    private function politique(): string
    {
        $scripts = "'self'";
        if (null !== $nonce = $this->nonce->existant()) {
            $scripts .= " 'nonce-".$nonce."'";
        }

        return implode('; ', [
            "default-src 'self'",
            'script-src '.$scripts,
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
