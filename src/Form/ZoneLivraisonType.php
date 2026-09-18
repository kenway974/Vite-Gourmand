<?php

namespace App\Form;

use App\Entity\ZoneLivraison;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ZoneLivraisonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('commune', TextType::class, ['label' => 'Commune'])
            ->add('codePostal', TextType::class, [
                'label' => 'Code postal',
                'attr' => ['inputmode' => 'numeric', 'maxlength' => 5],
            ])
            ->add('frais', MoneyType::class, [
                'label' => 'Supplément de livraison',
                'currency' => 'EUR',
                // La colonne est un NUMERIC : le montant reste une chaîne
                // décimale de bout en bout, jamais un flottant.
                'input' => 'string',
                'scale' => 2,
                'help' => 'Zéro pour les communes où la livraison est comprise dans le prix du menu.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ZoneLivraison::class]);
    }
}
