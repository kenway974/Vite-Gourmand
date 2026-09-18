<?php

namespace App\Form;

use App\Entity\Utilisateur;
use App\Security\PolitiqueMotDePasse;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Création d'un compte employé ou administrateur par un administrateur.
 *
 * Le formulaire public d'inscription attribue toujours ROLE_USER : personne ne
 * doit pouvoir se promouvoir depuis le site. Créer un compte interne est donc
 * une action d'administration, et c'est ce formulaire-ci qui la porte.
 */
class CompteInterneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, ['label' => 'Prénom'])
            ->add('nom', TextType::class, ['label' => 'Nom'])
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'help' => 'Elle servira d\'identifiant de connexion.',
            ])
            // Non mappé : setRoles attend un tableau, et le contrôleur doit
            // pouvoir refuser une valeur qui ne figure pas dans cette liste.
            ->add('role', ChoiceType::class, [
                'label' => 'Rôle',
                'mapped' => false,
                'choices' => [
                    'Employé' => 'ROLE_EMPLOYE',
                    'Administrateur' => 'ROLE_ADMIN',
                ],
                'expanded' => true,
                'data' => 'ROLE_EMPLOYE',
                'help' => 'Un employé gère le catalogue, les commandes et les avis. '
                    .'Un administrateur y ajoute les comptes, la livraison et les statistiques.',
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Mot de passe provisoire',
                'mapped' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'help' => PolitiqueMotDePasse::DESCRIPTION
                    .' À communiquer à la personne, qui pourra le changer depuis son compte.',
                'constraints' => PolitiqueMotDePasse::contraintes(),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Utilisateur::class]);
    }
}
