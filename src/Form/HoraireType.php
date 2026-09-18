<?php

namespace App\Form;

use App\Entity\Horaire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HoraireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var string[] $jours */
        $jours = $options['jours'];

        $builder
            ->add('jour', ChoiceType::class, [
                'label' => 'Jour',
                'choices' => array_combine($jours, $jours),
                'placeholder' => 'Choisir un jour',
            ])
            ->add('ferme', CheckboxType::class, [
                'label' => 'Fermé ce jour-là',
                'required' => false,
                'help' => 'Les heures saisies ci-dessous seront ignorées.',
            ])
            ->add('heureOuverture', TimeType::class, [
                'label' => 'Ouverture',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('heureFermeture', TimeType::class, [
                'label' => 'Fermeture',
                'widget' => 'single_text',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => Horaire::class,
                // Un jour déjà renseigné ne doit pas être reproposé : le
                // contrôleur restreint la liste à ce qui reste libre.
                'jours' => Horaire::JOURS,
            ])
            ->setAllowedTypes('jours', 'array')
        ;
    }
}
