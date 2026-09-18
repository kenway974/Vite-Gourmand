<?php

namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Coordonnées modifiables par le titulaire du compte.
 *
 * Ni le rôle ni l'activation n'y figurent : ce sont des décisions
 * d'administration, un client ne se promeut pas lui-même.
 */
class ProfilType extends AbstractType
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
                'help' => 'Cette adresse sert aussi à vous connecter.',
                'attr' => ['autocomplete' => 'email'],
            ])
            ->add('gsm', TelType::class, [
                'label' => 'Téléphone (facultatif)',
                'required' => false,
                'help' => 'Utilisé pour vous joindre le jour de la livraison.',
                'attr' => ['autocomplete' => 'tel'],
            ])
            ->add('adressePostale', TextType::class, [
                'label' => 'Adresse postale (facultatif)',
                'required' => false,
                'attr' => ['autocomplete' => 'street-address'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Utilisateur::class]);
    }
}
