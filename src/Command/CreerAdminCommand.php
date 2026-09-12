<?php

namespace App\Command;

use App\Entity\Utilisateur;
use App\Security\PolitiqueMotDePasse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Crée un compte administrateur.
 *
 * Le formulaire d'inscription public attribue toujours ROLE_USER et ne permet
 * donc pas de créer un administrateur : c'est volontaire, personne ne doit
 * pouvoir s'auto-promouvoir depuis le site. Cette commande est le seul moyen
 * prévu d'obtenir un compte ROLE_ADMIN.
 */
#[AsCommand(
    name: 'app:creer-admin',
    description: 'Crée un compte administrateur.',
)]
class CreerAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'Adresse e-mail de connexion')
            ->addArgument('prenom', InputArgument::OPTIONAL, 'Prénom')
            ->addArgument('nom', InputArgument::OPTIONAL, 'Nom')
            ->addOption(
                'mot-de-passe',
                null,
                InputOption::VALUE_REQUIRED,
                'Mot de passe. À réserver aux scripts : saisi ici, il reste dans '
                .'l\'historique du terminal. En usage normal, laissez la commande le demander.'
            )
            ->setHelp(<<<'AIDE'
                Crée un compte administrateur (ROLE_ADMIN), actif immédiatement.

                Usage interactif, recommandé :
                    <info>php %command.full_name%</info>

                Usage non interactif, pour un script de déploiement :
                    <info>php %command.full_name% admin@exemple.fr Kenny Pignolet --mot-de-passe='...'</info>

                Le mot de passe doit respecter les mêmes règles que l'inscription :
                AIDE.' '.PolitiqueMotDePasse::DESCRIPTION)
        ;
    }

    /**
     * Demande les valeurs manquantes plutôt que d'échouer sur un argument absent.
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);

        if (!$input->getArgument('email')) {
            $input->setArgument('email', $io->ask('Adresse e-mail'));
        }

        if (!$input->getArgument('prenom')) {
            $input->setArgument('prenom', $io->ask('Prénom'));
        }

        if (!$input->getArgument('nom')) {
            $input->setArgument('nom', $io->ask('Nom'));
        }

        if (!$input->getOption('mot-de-passe')) {
            $io->text(PolitiqueMotDePasse::DESCRIPTION);

            // Saisie masquée, puis confirmation : une faute de frappe sur un
            // mot de passe invisible rendrait le compte inutilisable.
            $motDePasse = $io->askHidden('Mot de passe');
            $confirmation = $io->askHidden('Confirmer le mot de passe');

            if ($motDePasse !== $confirmation) {
                throw new \RuntimeException('Les deux mots de passe ne correspondent pas.');
            }

            $input->setOption('mot-de-passe', $motDePasse);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = (string) $input->getArgument('email');
        $prenom = (string) $input->getArgument('prenom');
        $nom = (string) $input->getArgument('nom');
        $motDePasse = (string) $input->getOption('mot-de-passe');

        $depot = $this->em->getRepository(Utilisateur::class);

        if (null !== $depot->findOneBy(['email' => $email])) {
            $io->error(sprintf('Un compte existe déjà avec l\'adresse « %s ».', $email));

            return Command::FAILURE;
        }

        // Robustesse du mot de passe vérifiée avant le hachage, qui est coûteux.
        $erreurs = $this->validator->validate($motDePasse, PolitiqueMotDePasse::contraintes());

        $utilisateur = new Utilisateur();
        $utilisateur->setEmail($email);
        $utilisateur->setPrenom($prenom);
        $utilisateur->setNom($nom);
        $utilisateur->setRoles(['ROLE_ADMIN']);
        $utilisateur->setActif(true);

        $messages = [];

        foreach ($erreurs as $erreur) {
            $messages[] = $erreur->getMessage();
        }

        foreach ($this->validator->validate($utilisateur) as $erreur) {
            $messages[] = sprintf('%s : %s', $erreur->getPropertyPath(), $erreur->getMessage());
        }

        if ($messages) {
            $io->error('Le compte n\'a pas été créé :');
            $io->listing($messages);

            return Command::FAILURE;
        }

        $utilisateur->setPassword($this->hasher->hashPassword($utilisateur, $motDePasse));

        $this->em->persist($utilisateur);
        $this->em->flush();

        $io->success(sprintf('Compte administrateur créé pour « %s ».', $email));
        $io->note('Connexion sur /connexion, puis accès aux pages sous /admin.');

        return Command::SUCCESS;
    }
}
