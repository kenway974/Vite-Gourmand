<?php

namespace App\Form;

use App\Entity\Menu;
use App\Entity\Plat;
use App\Entity\Regime;
use App\Entity\Theme;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MenuType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['rows' => 4],
            ])
            ->add('theme', EntityType::class, [
                'label' => 'Thème',
                'class' => Theme::class,
                'choice_label' => 'libelle',
                'placeholder' => 'Choisir un thème',
            ])
            ->add('regime', EntityType::class, [
                'label' => 'Régime',
                'class' => Regime::class,
                'choice_label' => 'libelle',
                'placeholder' => 'Choisir un régime',
            ])
            ->add('nbMinPersonnes', IntegerType::class, [
                'label' => 'Nombre minimum de personnes',
            ])
            ->add('prixMin', MoneyType::class, [
                'label' => 'Prix à partir de',
                'currency' => 'EUR',
            ])
            ->add('delaiCommandeJours', IntegerType::class, [
                'label' => 'Délai de commande (en jours)',
            ])
            ->add('stock', IntegerType::class, [
                'label' => 'Stock disponible',
            ])
            ->add('precautions', TextareaType::class, [
                'label' => 'Précautions (facultatif)',
                'required' => false,
                'attr' => ['rows' => 3],
                'help' => 'Allergènes, conseils de conservation, matériel à prévoir…',
            ])
            ->add('image', TextType::class, [
                'label' => 'Image (facultatif)',
                'required' => false,
                'help' => "Nom du fichier ou URL de l'illustration.",
            ])
            // `plats` est le côté inverse de la relation ManyToMany : sans
            // by_reference à false, le formulaire modifierait la collection
            // directement et Doctrine n'enregistrerait rien. À false, il passe
            // par addPlat()/removePlat(), qui synchronisent le côté propriétaire.
            ->add('plats', EntityType::class, [
                'label' => 'Plats du menu',
                'class' => Plat::class,
                'choice_label' => 'nom',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'by_reference' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Menu::class,
        ]);
    }
}
