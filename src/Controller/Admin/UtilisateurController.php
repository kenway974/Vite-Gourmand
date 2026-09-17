<?php

namespace App\Controller\Admin;

use App\Entity\Utilisateur;
use App\Form\CompteInterneType;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion des comptes : rôles et activation.
 *
 * Les mots de passe ne sont pas gérés ici. Un administrateur n'a pas à
 * pouvoir changer celui d'un client : c'est au client de le faire.
 */
#[Route('/admin/utilisateurs')]
#[IsGranted('ROLE_ADMIN')]
class UtilisateurController extends AbstractController
{
    /** Rôles attribuables depuis cet écran. */
    private const ROLES = [
        'Client' => '',
        'Employé' => 'ROLE_EMPLOYE',
        'Administrateur' => 'ROLE_ADMIN',
    ];

    #[Route('', name: 'app_admin_utilisateur_index', methods: ['GET'])]
    public function index(Request $request, UtilisateurRepository $utilisateurs): Response
    {
        return $this->render('admin/utilisateur/index.html.twig', [
            'lignes' => $utilisateurs->findPourAdministration($request->query->getString('recherche')),
            'recherche' => $request->query->getString('recherche'),
            'roles' => self::ROLES,
        ]);
    }

    /**
     * Création d'un compte employé ou administrateur.
     *
     * Le cahier des charges réserve cette action à l'administrateur. Elle
     * n'existait qu'en console (app:creer-admin), ce qui suppose un accès au
     * serveur — inutilisable pour le gérant du traiteur.
     */
    #[Route('/nouveau', name: 'app_admin_utilisateur_new', methods: ['GET', 'POST'])]
    public function nouveau(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
    ): Response {
        $utilisateur = new Utilisateur();
        $form = $this->createForm(CompteInterneType::class, $utilisateur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $role = $form->get('role')->getData();

            // Le rôle vient d'un ChoiceType, donc déjà validé ; on revérifie
            // tout de même contre la liste de référence de cet écran.
            if (!\in_array($role, self::ROLES, true) || '' === $role) {
                $this->addFlash('danger', 'Rôle inconnu.');

                return $this->redirectToRoute('app_admin_utilisateur_new');
            }

            $utilisateur
                ->setRoles([$role])
                ->setActif(true)
                ->setPassword($hasher->hashPassword($utilisateur, $form->get('plainPassword')->getData()));

            $em->persist($utilisateur);
            $em->flush();

            $this->addFlash('success', sprintf(
                'Le compte de %s %s a été créé.',
                $utilisateur->getPrenom(),
                $utilisateur->getNom(),
            ));

            return $this->redirectToRoute('app_admin_utilisateur_index');
        }

        return $this->render('admin/utilisateur/nouveau.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/role', name: 'app_admin_utilisateur_role', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function changerRole(
        Request $request,
        Utilisateur $utilisateur,
        EntityManagerInterface $em,
        UtilisateurRepository $utilisateurs,
    ): Response {
        if (!$this->isCsrfTokenValid('role-utilisateur-'.$utilisateur->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $role = $request->getPayload()->getString('role');

        if (!\in_array($role, self::ROLES, true)) {
            $this->addFlash('danger', 'Rôle inconnu.');

            return $this->redirectToRoute('app_admin_utilisateur_index');
        }

        // Se retirer soi-même ses droits, c'est se verrouiller dehors.
        if ($utilisateur === $this->getUser() && 'ROLE_ADMIN' !== $role) {
            $this->addFlash('danger', 'Vous ne pouvez pas retirer vos propres droits d\'administration.');

            return $this->redirectToRoute('app_admin_utilisateur_index');
        }

        if ($this->seraitLeDernierAdministrateur($utilisateur, $utilisateurs) && 'ROLE_ADMIN' !== $role) {
            $this->addFlash('danger', 'Ce compte est le dernier administrateur actif : son rôle ne peut pas être retiré.');

            return $this->redirectToRoute('app_admin_utilisateur_index');
        }

        $utilisateur->setRoles('' === $role ? [] : [$role]);
        $em->flush();

        $this->addFlash('success', sprintf('Rôle de %s mis à jour.', $utilisateur->getEmail()));

        return $this->redirectToRoute('app_admin_utilisateur_index');
    }

    #[Route('/{id}/activation', name: 'app_admin_utilisateur_activation', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function basculerActivation(
        Request $request,
        Utilisateur $utilisateur,
        EntityManagerInterface $em,
        UtilisateurRepository $utilisateurs,
    ): Response {
        if (!$this->isCsrfTokenValid('activation-utilisateur-'.$utilisateur->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if ($utilisateur === $this->getUser()) {
            $this->addFlash('danger', 'Vous ne pouvez pas désactiver votre propre compte.');

            return $this->redirectToRoute('app_admin_utilisateur_index');
        }

        if ($utilisateur->isActif() && $this->seraitLeDernierAdministrateur($utilisateur, $utilisateurs)) {
            $this->addFlash('danger', 'Ce compte est le dernier administrateur actif : il ne peut pas être désactivé.');

            return $this->redirectToRoute('app_admin_utilisateur_index');
        }

        $utilisateur->setActif(!$utilisateur->isActif());
        $em->flush();

        $this->addFlash('success', sprintf(
            'Compte %s %s.',
            $utilisateur->getEmail(),
            $utilisateur->isActif() ? 'réactivé' : 'désactivé',
        ));

        return $this->redirectToRoute('app_admin_utilisateur_index');
    }

    private function seraitLeDernierAdministrateur(Utilisateur $utilisateur, UtilisateurRepository $utilisateurs): bool
    {
        return \in_array('ROLE_ADMIN', $utilisateur->getRoles(), true)
            && $utilisateur->isActif()
            && $utilisateurs->compterAdministrateursActifs() <= 1;
    }
}
