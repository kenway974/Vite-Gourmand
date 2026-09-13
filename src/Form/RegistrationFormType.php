<?php

namespace App\Form;

use App\Entity\Utilisateur;
use App\Security\PolitiqueMotDePasse;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['autocomplete' => 'given-name'],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => ['autocomplete' => 'family-name'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'attr' => ['autocomplete' => 'email'],
            ])
            ->add('gsm', TelType::class, [
                'label' => 'Téléphone (facultatif)',
                'required' => false,
                'attr' => ['autocomplete' => 'tel'],
            ])
            ->add('adressePostale', TextType::class, [
                'label' => 'Adresse postale (facultatif)',
                'required' => false,
                'attr' => ['autocomplete' => 'street-address'],
            ])
            // Non mappé : on ne stocke jamais le mot de passe en clair sur l'entité.
            // Le contrôleur le hache puis l'injecte via setPassword().
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'Les deux mots de passe doivent être identiques.',
                'first_options' => [
                    'label' => 'Mot de passe',
                    'attr' => ['autocomplete' => 'new-password'],
                    'help' => PolitiqueMotDePasse::DESCRIPTION,
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'constraints' => PolitiqueMotDePasse::contraintes(),
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => "J'accepte les conditions générales de vente.",
                'mapped' => false,
                'constraints' => [
                    new IsTrue(message: 'Vous devez accepter les conditions générales de vente.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
        ]);
    }
}
