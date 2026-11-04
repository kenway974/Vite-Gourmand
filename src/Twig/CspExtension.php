<?php

namespace App\Twig;

use App\Security\NonceCsp;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose csp_nonce() aux gabarits : tout <script> doit le porter, sans quoi la
 * Content-Security-Policy le bloque.
 */
class CspExtension extends AbstractExtension
{
    public function __construct(
        private readonly NonceCsp $nonce,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('csp_nonce', $this->nonce->obtenir(...)),
        ];
    }
}
