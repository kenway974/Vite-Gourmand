<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\ChangementMotDePasseType;
use App\Form\ProfilType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion de son propre compte.
 *
 * Toutes les actions portent sur l'utilisateur connecté, jamais sur un
 * identifiant passé dans l'URL : il n'y a donc rien à contrôler côté
 * propriété, aucun paramètre ne désigne un autre compte.
 */
#[Route('/mon-compte')]
#[IsGranted('ROLE_USER')]
class CompteController extends AbstractController
{
    #[Route('', name: 'app_compte', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('compte/index.html.twig');
    }

    #[Route('/modifier', name: 'app_compte_modifier', methods: ['GET', 'POST'])]
    public function modifier(Request $request, EntityManagerInterface $em): Response
    {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();

        $form = $this->createForm(ProfilType::class, $utilisateur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Vos coordonnées ont été mises à jour.');

            return $this->redirectToRoute('app_compte');
        }

        return $this->render('compte/modifier.html.twig', ['form' => $form]);
    }

    #[Route('/mot-de-passe', name: 'app_compte_mot_de_passe', methods: ['GET', 'POST'])]
    public function motDePasse(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
    ): Response {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();

        $form = $this->createForm(ChangementMotDePasseType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $utilisateur->setPassword(
                $hasher->hashPassword($utilisateur, $form->get('nouveau')->getData()),
            );
            $em->flush();

            $this->addFlash('success', 'Votre mot de passe a été changé.');

            return $this->redirectToRoute('app_compte');
        }

        return $this->render('compte/mot_de_passe.html.twig', ['form' => $form]);
    }
}
