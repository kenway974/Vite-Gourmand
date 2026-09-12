<?php

namespace App\Security;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Source unique des règles de robustesse des mots de passe.
 *
 * Utilisée par le formulaire d'inscription et par la commande de création
 * d'administrateur, afin qu'un compte créé en console ne puisse pas être
 * moins bien protégé qu'un compte créé depuis le site.
 */
final class PolitiqueMotDePasse
{
    public const LONGUEUR_MINIMALE = 12;

    public const DESCRIPTION = '12 caractères minimum, dont une majuscule, une minuscule, un chiffre et un caractère spécial.';

    /**
     * @return list<Constraint>
     */
    public static function contraintes(): array
    {
        return [
            new NotBlank(message: 'Merci de saisir un mot de passe.'),
            new Length(
                min: self::LONGUEUR_MINIMALE,
                // Borne haute : évite un déni de service sur le calcul du hachage.
                max: 4096,
                minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
            ),
            new Regex(
                pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).+$/',
                message: 'Le mot de passe doit contenir une majuscule, une minuscule, un chiffre et un caractère spécial.',
            ),
        ];
    }
}
