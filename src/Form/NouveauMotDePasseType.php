<?php

namespace App\Form;

use App\Security\PolitiqueMotDePasse;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Saisie du nouveau mot de passe après clic sur le lien reçu par courriel.
 *
 * Contrairement au changement depuis le compte, l'ancien mot de passe n'est
 * pas redemandé : la personne ne s'en souvient précisément pas. C'est la
 * possession du jeton envoyé à l'adresse du compte qui fait preuve.
 */
class NouveauMotDePasseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('nouveau', RepeatedType::class, [
            'type' => PasswordType::class,
            'mapped' => false,
            'invalid_message' => 'Les deux mots de passe doivent être identiques.',
            'first_options' => [
                'label' => 'Nouveau mot de passe',
                'attr' => ['autocomplete' => 'new-password'],
                'help' => PolitiqueMotDePasse::DESCRIPTION,
            ],
            'second_options' => [
                'label' => 'Confirmer le nouveau mot de passe',
                'attr' => ['autocomplete' => 'new-password'],
            ],
            'constraints' => PolitiqueMotDePasse::contraintes(),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
