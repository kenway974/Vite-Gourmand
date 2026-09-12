<?php

namespace App\Form;

use App\Entity\Utilisateur;
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
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

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
                    'help' => '12 caractères minimum, dont une majuscule, une minuscule, un chiffre et un caractère spécial.',
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'constraints' => [
                    new NotBlank(message: 'Merci de saisir un mot de passe.'),
                    new Length(
                        min: 12,
                        max: 4096, // borne haute : évite une attaque par déni de service sur le hachage
                        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                    ),
                    new Regex(
                        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).+$/',
                        message: 'Le mot de passe doit contenir une majuscule, une minuscule, un chiffre et un caractère spécial.',
                    ),
                ],
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
