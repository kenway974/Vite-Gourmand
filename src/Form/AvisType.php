<?php

namespace App\Form;

use App\Entity\Avis;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AvisType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('note', ChoiceType::class, [
                'label' => 'Votre note',
                'choices' => [
                    '5 — excellent' => 5,
                    '4 — très bien' => 4,
                    '3 — correct' => 3,
                    '2 — décevant' => 2,
                    '1 — mauvais' => 1,
                ],
                'placeholder' => 'Choisir une note',
            ])
            ->add('commentaire', TextareaType::class, [
                'label' => 'Votre commentaire',
                'attr' => ['rows' => 5],
                'help' => 'Votre avis sera publié après relecture par le restaurant.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Avis::class]);
    }
}
