<?php

namespace App\Controller;

use App\Form\DemandeReinitialisationType;
use App\Form\NouveauMotDePasseType;
use App\Security\ReinitialisationMotDePasse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Mot de passe oublié.
 *
 * Ces deux pages sont publiques par nature : elles s'adressent à quelqu'un
 * qui, précisément, ne peut pas se connecter.
 */
#[Route('/mot-de-passe-oublie')]
class MotDePasseController extends AbstractController
{
    #[Route('', name: 'app_mot_de_passe_oublie', methods: ['GET', 'POST'])]
    public function demander(Request $request, ReinitialisationMotDePasse $service): Response
    {
        $form = $this->createForm(DemandeReinitialisationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $service->demander($form->get('email')->getData());

            // Le message est le même que l'adresse soit connue ou non : le
            // contraire ferait de ce formulaire un annuaire des comptes.
            return $this->render('securite/demande_envoyee.html.twig');
        }

        return $this->render('securite/mot_de_passe_oublie.html.twig', ['form' => $form]);
    }

    #[Route('/{jeton}', name: 'app_mot_de_passe_reinitialiser', requirements: ['jeton' => '[a-f0-9]{64}'], methods: ['GET', 'POST'])]
    public function reinitialiser(string $jeton, Request $request, ReinitialisationMotDePasse $service): Response
    {
        $utilisateur = $service->trouverParJeton($jeton);

        if (null === $utilisateur) {
            $this->addFlash('danger', 'Ce lien n\'est plus valable. Demandez-en un nouveau.');

            return $this->redirectToRoute('app_mot_de_passe_oublie');
        }

        $form = $this->createForm(NouveauMotDePasseType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $service->reinitialiser($utilisateur, $form->get('nouveau')->getData());

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('securite/nouveau_mot_de_passe.html.twig', ['form' => $form]);
    }
}
