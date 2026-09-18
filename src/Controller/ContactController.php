<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Entity\Utilisateur;
use App\Form\ContactType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Formulaire de contact public.
 */
class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function contact(Request $request, EntityManagerInterface $em): Response
    {
        $contact = new Contact();

        // Un visiteur connecté n'a pas à ressaisir son adresse.
        $utilisateur = $this->getUser();
        if ($utilisateur instanceof Utilisateur) {
            $contact->setEmail($utilisateur->getEmail());
        }

        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contact->setDateCreation(new \DateTime());

            $em->persist($contact);
            $em->flush();

            $this->addFlash('success', 'Votre message est bien arrivé. Nous vous répondons sous 48 heures.');

            return $this->redirectToRoute('app_contact');
        }

        return $this->render('contact/index.html.twig', ['form' => $form]);
    }
}
