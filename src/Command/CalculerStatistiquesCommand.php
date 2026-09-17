<?php

namespace App\Command;

use App\Statistiques\CalculateurStatistiques;
use App\Statistiques\DepotStatistiques;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Enregistre un relevé d'activité dans la base NoSQL.
 *
 * À faire tourner périodiquement — une fois par nuit suffit. Chaque exécution
 * ajoute un document, elle n'écrase rien : c'est l'accumulation qui donne
 * l'évolution dans le temps, qu'un simple COUNT sur MySQL ne saurait pas
 * reconstituer après coup.
 */
#[AsCommand(
    name: 'app:calculer-statistiques',
    description: 'Enregistre un relevé d\'activité dans la base de statistiques.',
)]
class CalculerStatistiquesCommand extends Command
{
    public function __construct(
        private readonly CalculateurStatistiques $calculateur,
        private readonly DepotStatistiques $depot,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->depot->disponible()) {
            $io->error(
                'La base de statistiques est injoignable. Vérifiez MONGODB_URL '
                .'et la présence de l\'extension PHP mongodb.'
            );

            return Command::FAILURE;
        }

        $instantane = $this->calculateur->calculer();
        $this->depot->enregistrer($instantane);

        $io->success(sprintf(
            'Relevé enregistré : %d commande(s), %s € de chiffre d\'affaires.',
            $instantane->nbCommandes,
            $instantane->chiffreAffaires,
        ));

        $io->table(
            ['Menu', 'Commandes', 'Chiffre d\'affaires'],
            array_map(
                static fn (array $l) => [$l['menu'], $l['commandes'], $l['chiffreAffaires'].' €'],
                \array_slice($instantane->parMenu, 0, 5),
            ),
        );

        return Command::SUCCESS;
    }
}
