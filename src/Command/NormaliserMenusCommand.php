<?php

namespace App\Command;

use App\Entity\Menu;
use App\Repository\MenuRepository;
use App\Service\Normalisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Recalcule la colonne de recherche des menus.
 *
 * Les setters de Menu la tiennent à jour à chaque écriture ; cette commande
 * sert aux lignes qui existaient avant la colonne, et après toute modification
 * du normalisateur — ajouter un caractère à sa table de remplacement ne
 * réécrit pas les menus déjà enregistrés.
 *
 * Elle est idempotente : la relancer ne change rien si tout est déjà à jour.
 */
#[AsCommand(
    name: 'app:normaliser-menus',
    description: 'Recalcule la colonne de recherche normalisée des menus.',
)]
class NormaliserMenusCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MenuRepository $menus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $menus = $this->menus->findAll();

        if ([] === $menus) {
            $io->warning('Aucun menu en base.');

            return Command::SUCCESS;
        }

        $modifies = 0;

        foreach ($menus as $menu) {
            $avant = $menu->getRecherche();

            // Réaffecter le titre déclenche le recalcul par le setter : une
            // seule source de vérité pour la normalisation.
            $menu->setTitre($menu->getTitre());

            if ($menu->getRecherche() !== $avant) {
                ++$modifies;
            }
        }

        $this->em->flush();

        $io->success(sprintf(
            '%d menu(s) parcouru(s), %d mis à jour.',
            \count($menus),
            $modifies,
        ));

        return Command::SUCCESS;
    }
}
