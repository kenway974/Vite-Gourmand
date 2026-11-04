<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Nonce de la Content-Security-Policy, unique à chaque requête.
 *
 * Il est rangé dans les attributs de la requête principale, pas dans ce
 * service : en mode worker (FrankenPHP), les services survivent d'une requête
 * à l'autre, et un nonce resservi perdrait toute valeur.
 */
final class NonceCsp
{
    private const ATTRIBUT = '_csp_nonce';

    public function __construct(
        private readonly RequestStack $requetes,
    ) {
    }

    /**
     * Nonce de la requête en cours, créé au premier appel.
     */
    public function obtenir(): string
    {
        $requete = $this->requetes->getMainRequest();

        if (null === $requete) {
            // Rendu hors HTTP (courriel envoyé en console) : aucune CSP ne
            // s'applique, la valeur n'a pas d'importance.
            return '';
        }

        if (!$requete->attributes->has(self::ATTRIBUT)) {
            $requete->attributes->set(self::ATTRIBUT, base64_encode(random_bytes(18)));
        }

        return $requete->attributes->get(self::ATTRIBUT);
    }

    /**
     * Nonce déjà attribué à la requête, ou null si aucune page ne l'a demandé.
     */
    public function existant(): ?string
    {
        return $this->requetes->getMainRequest()?->attributes->get(self::ATTRIBUT);
    }
}
