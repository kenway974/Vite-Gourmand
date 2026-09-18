<?php

namespace App\Form;

use App\Entity\Allergene;
use App\Entity\Ingredient;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IngredientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => "Nom de l'ingrédient"])
            ->add('image', TextType::class, [
                'label' => 'Image (facultatif)',
                'required' => false,
            ])
            ->add('allergenes', EntityType::class, [
                'label' => 'Allergènes contenus',
                'class' => Allergene::class,
                'choice_label' => 'libelle',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'by_reference' => false,
                'help' => "Information réglementaire : à renseigner avec soin.",
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Ingredient::class]);
    }
}
