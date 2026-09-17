<?php

namespace App\Form;

use App\Entity\Ingredient;
use App\Entity\Plat;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlatType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Nom du plat'])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Entrée' => 'Entrée',
                    'Plat' => 'Plat',
                    'Accompagnement' => 'Accompagnement',
                    'Dessert' => 'Dessert',
                    'Boisson' => 'Boisson',
                ],
                'placeholder' => 'Choisir un type',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['rows' => 4],
            ])
            ->add('image', TextType::class, [
                'label' => 'Image (facultatif)',
                'required' => false,
                'help' => "Nom du fichier ou URL de l'illustration.",
            ])
            // Plat est le côté propriétaire de la relation avec Ingredient
            // (inversedBy), mais on passe quand même par addIngredient() pour
            // rester cohérent avec le reste de l'administration.
            ->add('ingredients', EntityType::class, [
                'label' => 'Ingrédients',
                'class' => Ingredient::class,
                'choice_label' => 'nom',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'by_reference' => false,
                'help' => 'Les allergènes du plat découlent de ses ingrédients.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Plat::class]);
    }
}
