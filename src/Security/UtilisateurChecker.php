<?php

namespace App\Security;

use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Refuse la connexion aux comptes désactivés (champ `actif` de l'entité Utilisateur).
 *
 * Le contrôle a lieu avant la vérification du mot de passe : un compte désactivé
 * ne peut pas se connecter, même avec les bons identifiants.
 */
class UtilisateurChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Utilisateur) {
            return;
        }

        if (!$user->isActif()) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte est désactivé. Contactez le restaurant pour le réactiver.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // Aucun contrôle supplémentaire après authentification.
    }
}
