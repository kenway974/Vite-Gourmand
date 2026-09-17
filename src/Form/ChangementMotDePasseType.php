<?php

namespace App\Form;

use App\Security\PolitiqueMotDePasse;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;

/**
 * Changement de mot de passe depuis un compte déjà connecté.
 *
 * Aucun champ n'est mappé sur l'entité : un mot de passe en clair n'a rien à
 * faire sur Utilisateur, même le temps d'une requête.
 */
class ChangementMotDePasseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Le mot de passe actuel est redemandé : sans lui, une session
            // laissée ouverte suffirait à prendre le compte définitivement.
            ->add('actuel', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'mapped' => false,
                'attr' => ['autocomplete' => 'current-password'],
                'constraints' => [
                    new UserPassword(message: 'Ce mot de passe ne correspond pas à votre mot de passe actuel.'),
                ],
            ])
            ->add('nouveau', RepeatedType::class, [
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
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
