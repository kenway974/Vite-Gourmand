<?php

namespace App\Controller\Employe;

use App\Entity\Contact;
use App\Repository\ContactRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Traitement des messages de contact.
 */
#[Route('/employe/messages')]
#[IsGranted('ROLE_EMPLOYE')]
class MessageController extends AbstractController
{
    #[Route('', name: 'app_employe_messages', methods: ['GET'])]
    public function index(Request $request, ContactRepository $contacts): Response
    {
        $traites = $request->query->getBoolean('traites');

        return $this->render('employe/messages.html.twig', [
            'messages' => $contacts->findBy(['traite' => $traites], ['dateCreation' => 'DESC']),
            'traites' => $traites,
            'enAttente' => $contacts->count(['traite' => false]),
        ]);
    }

    #[Route('/{id}/traitement', name: 'app_employe_message_traitement', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function basculerTraitement(Request $request, Contact $contact, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('traitement-message-'.$contact->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $contact->marquerTraite(!$contact->isTraite());
        $em->flush();

        $this->addFlash('success', $contact->isTraite() ? 'Message marqué comme traité.' : 'Message rouvert.');

        return $this->redirectToRoute('app_employe_messages', ['traites' => $request->query->getBoolean('traites')]);
    }
}
