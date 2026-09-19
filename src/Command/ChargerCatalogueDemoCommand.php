<?php

namespace App\Command;

use App\DataFixtures\CatalogueDemoLoader;
use App\Entity\Menu;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Charge le catalogue de démonstration (menus, plats, ingrédients, thèmes,
 * régimes, horaires, zones de livraison) dans une base vide.
 *
 * Contrairement à `doctrine:fixtures:load`, ne crée ni compte utilisateur ni
 * commande : c'est ce qui la rend sûre à lancer en production, là où les
 * fixtures complètes exposeraient un mot de passe connu (celui écrit en
 * clair dans AppFixtures, pour l'usage local).
 */
#[AsCommand(
    name: 'app:charger-catalogue-demo',
    description: 'Charge le catalogue de démonstration (menus, plats, thèmes...) sans compte utilisateur ni commande.',
)]
class ChargerCatalogueDemoCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CatalogueDemoLoader $catalogue,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Le catalogue est censé être chargé une seule fois, sur une base
        // vide : le relancer sur une base déjà peuplée doublerait chaque
        // menu et chaque plat plutôt que de les remplacer.
        if ($this->em->getRepository(Menu::class)->count([]) > 0) {
            $io->error('Des menus existent déjà : le catalogue de démonstration n\'a pas été rechargé.');

            return Command::FAILURE;
        }

        $this->catalogue->charger($this->em);
        $this->em->flush();

        $io->success('Catalogue de démonstration chargé.');

        return Command::SUCCESS;
    }
}
