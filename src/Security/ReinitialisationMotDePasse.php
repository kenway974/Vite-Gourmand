<?php

namespace App\Security;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Réinitialisation de mot de passe par jeton à usage unique.
 *
 * Le jeton en clair ne quitte jamais cette classe autrement que par courriel :
 * la base n'en conserve que l'empreinte SHA-256, de sorte qu'une fuite ne
 * permette pas de prendre les comptes.
 */
final class ReinitialisationMotDePasse
{
    /**
     * Une heure : assez pour relever ses courriels, trop court pour qu'un
     * lien oublié dans une boîte reste exploitable longtemps.
     */
    public const VALIDITE_MINUTES = 60;

    public function __construct(
        private readonly UtilisateurRepository $utilisateurs,
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urls,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly string $expediteur,
    ) {
    }

    /**
     * Traite une demande de réinitialisation.
     *
     * Ne renvoie rien et ne signale aucune erreur : le formulaire affiche le
     * même message qu'une adresse soit connue ou non, sans quoi il deviendrait
     * un annuaire des comptes existants.
     */
    public function demander(string $email): void
    {
        $utilisateur = $this->utilisateurs->findOneBy(['email' => $email]);

        // Un compte désactivé ne peut pas se connecter : lui rouvrir un
        // chemin par la réinitialisation contournerait la désactivation.
        if (null === $utilisateur || true !== $utilisateur->isActif()) {
            return;
        }

        $jeton = bin2hex(random_bytes(32));

        $utilisateur->demanderReinitialisation(
            hash('sha256', $jeton),
            new \DateTimeImmutable(sprintf('+%d minutes', self::VALIDITE_MINUTES)),
        );
        $this->em->flush();

        $this->mailer->send(
            (new TemplatedEmail())
                ->from($this->expediteur)
                ->to($utilisateur->getEmail())
                ->subject('Réinitialisation de votre mot de passe')
                ->htmlTemplate('securite/courriel_reinitialisation.html.twig')
                ->context([
                    'prenom' => $utilisateur->getPrenom(),
                    'lien' => $this->urls->generate(
                        'app_mot_de_passe_reinitialiser',
                        ['jeton' => $jeton],
                        UrlGeneratorInterface::ABSOLUTE_URL,
                    ),
                    'validite' => self::VALIDITE_MINUTES,
                ]),
        );
    }

    /**
     * Retrouve le compte visé par un jeton encore valide, ou null.
     */
    public function trouverParJeton(string $jeton): ?Utilisateur
    {
        // La recherche porte sur l'empreinte : le jeton reçu est rehaché, il
        // n'existe nulle part en clair du côté serveur.
        $utilisateur = $this->utilisateurs->findOneBy([
            'jetonReinitialisation' => hash('sha256', $jeton),
        ]);

        if (null === $utilisateur || !$utilisateur->reinitialisationEnCours()) {
            return null;
        }

        return $utilisateur;
    }

    /**
     * Applique le nouveau mot de passe et consomme le jeton.
     */
    public function reinitialiser(Utilisateur $utilisateur, string $motDePasse): void
    {
        $utilisateur
            ->setPassword($this->hasher->hashPassword($utilisateur, $motDePasse))
            ->oublierReinitialisation();

        $this->em->flush();
    }
}
