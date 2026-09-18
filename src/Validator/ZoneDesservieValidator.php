<?php

namespace App\Validator;

use App\Entity\Commande;
use App\Repository\ZoneLivraisonRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class ZoneDesservieValidator extends ConstraintValidator
{
    public function __construct(private readonly ZoneLivraisonRepository $zones)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ZoneDesservie) {
            throw new UnexpectedValueException($constraint, ZoneDesservie::class);
        }

        if (!$value instanceof Commande) {
            return;
        }

        $code = $value->getCodePostalLivraison();

        // Un code absent ou mal formé est déjà signalé par les contraintes du
        // champ : inutile d'empiler un second message sur la même erreur.
        if (null === $code || 1 !== preg_match('/^\d{5}$/', $code)) {
            return;
        }

        if (null !== $this->zones->findParCodePostal($code)) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ code }}', $code)
            ->atPath('codePostalLivraison')
            ->addViolation();
    }
}
