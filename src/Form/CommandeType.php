<?php

namespace App\Form;

use App\Entity\Commande;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire de commande.
 *
 * Le menu n'y figure pas : il vient de l'URL. L'exposer en champ permettrait
 * à un visiteur de commander un autre menu que celui qu'il consulte, à un
 * prix calculé sur le premier.
 */
class CommandeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('datePrestation', DateType::class, [
                'label' => 'Date de la prestation',
                'widget' => 'single_text',
                'help' => $options['aide_delai'],
            ])
            ->add('heureLivraison', TimeType::class, [
                'label' => 'Heure de livraison',
                'widget' => 'single_text',
            ])
            ->add('lieuLivraison', TextType::class, [
                'label' => 'Adresse de livraison',
            ])
            ->add('codePostalLivraison', TextType::class, [
                'label' => 'Code postal de livraison',
                'help' => $options['aide_zones'],
                'attr' => ['inputmode' => 'numeric', 'maxlength' => 5, 'autocomplete' => 'postal-code'],
            ])
            ->add('nbPersonnes', IntegerType::class, [
                'label' => 'Nombre de convives',
                'help' => $options['aide_convives'],
            ])
            ->add('pretMateriel', CheckboxType::class, [
                'label' => 'Prêt de matériel (plats et présentoirs)',
                'required' => false,
                'help' => 'À restituer sous dix jours ouvrés, faute de quoi une indemnité de 600 € s\'applique.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Commande::class,
            'aide_delai' => '',
            'aide_convives' => '',
            'aide_zones' => '',
        ]);

        $resolver->setAllowedTypes('aide_delai', 'string');
        $resolver->setAllowedTypes('aide_convives', 'string');
        $resolver->setAllowedTypes('aide_zones', 'string');
    }
}
