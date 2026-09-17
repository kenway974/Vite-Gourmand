<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Le code postal de livraison doit correspondre à une zone desservie.
 *
 * Contrainte de classe et non de propriété : le message doit pouvoir être
 * rattaché au champ du code postal tout en ayant accès à la commande entière.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ZoneDesservie extends Constraint
{
    public string $message = 'Nous ne livrons pas encore le {{ code }}. Écrivez-nous pour un devis.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
