<?php

namespace App\Controller;

use App\Entity\Avis;
use App\Entity\Commande;
use App\Entity\SuiviCommande;
use App\Entity\Utilisateur;
use App\Form\AvisType;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Espace client : suivi d'une commande et dépôt d'avis.
 *
 * La liste des commandes s'affiche sur /mon-compte (CompteController) : ces
 * routes ne portent plus que le détail d'une commande précise, désignée par
 * son identifiant.
 */
#[Route('/mes-commandes')]
#[IsGranted('ROLE_USER')]
class EspaceClientController extends AbstractController
{
    #[Route('/{id}', name: 'app_client_commande', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(int $id, CommandeRepository $commandes): Response
    {
        return $this->render('espace_client/detail.html.twig', [
            'commande' => $this->commandeDuClient($id, $commandes),
        ]);
    }

    #[Route('/{id}/annuler', name: 'app_client_commande_annuler', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function annuler(int $id, Request $request, CommandeRepository $commandes, EntityManagerInterface $em): Response
    {
        $commande = $this->commandeDuClient($id, $commandes);

        if (!$this->isCsrfTokenValid('annuler-commande-'.$id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        // Une commande en préparation ne s'annule plus en libre-service : les
        // achats sont engagés. Le client doit passer par le restaurant.
        if (!$commande->estAnnulableParLeClient()) {
            $this->addFlash('danger', 'Cette commande est déjà en préparation : contactez-nous pour l\'annuler.');

            return $this->redirectToRoute('app_client_commande', ['id' => $id]);
        }

        $commande->setStatut(Commande::ANNULEE);

        $em->persist(
            (new SuiviCommande())
                ->setCommande($commande)
                ->setStatut(Commande::ANNULEE)
                ->setDateModification(new \DateTime())
                ->setModeContact('site web')
                ->setMotif('Annulation à la demande du client.')
        );

        // La prestation est libérée : le menu redevient disponible.
        $menu = $commande->getMenu();
        $menu->setStock(($menu->getStock() ?? 0) + 1);

        $em->flush();

        $this->addFlash('success', 'Votre commande a été annulée.');

        return $this->redirectToRoute('app_client_commande', ['id' => $id]);
    }

    #[Route('/{id}/avis', name: 'app_client_avis', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function deposerAvis(int $id, Request $request, CommandeRepository $commandes, EntityManagerInterface $em): Response
    {
        $commande = $this->commandeDuClient($id, $commandes);

        // On ne juge que ce qu'on a reçu.
        if (Commande::LIVREE !== $commande->getStatut()) {
            $this->addFlash('danger', 'Vous pourrez déposer un avis une fois la prestation livrée.');

            return $this->redirectToRoute('app_client_commande', ['id' => $id]);
        }

        // Un avis par commande : la base porte une contrainte d'unicité.
        if (null !== $commande->getAvis()) {
            $this->addFlash('danger', 'Vous avez déjà déposé un avis sur cette commande.');

            return $this->redirectToRoute('app_client_commande', ['id' => $id]);
        }

        $avis = new Avis();
        $form = $this->createForm(AvisType::class, $avis);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $avis->setCommande($commande)
                ->setUtilisateur($this->client())
                ->setDateCreation(new \DateTime())
                ->setStatutValidation(Avis::EN_ATTENTE);

            $em->persist($avis);
            $em->flush();

            $this->addFlash('success', 'Merci. Votre avis sera publié après relecture.');

            return $this->redirectToRoute('app_client_commande', ['id' => $id]);
        }

        return $this->render('espace_client/avis.html.twig', [
            'commande' => $commande,
            'form' => $form,
        ]);
    }

    private function client(): Utilisateur
    {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();

        return $utilisateur;
    }

    /**
     * Le propriétaire est dans la requête : une commande d'autrui est
     * introuvable, pas « trouvée puis refusée ».
     */
    private function commandeDuClient(int $id, CommandeRepository $commandes): Commande
    {
        $commande = $commandes->findUneDuClient($id, $this->client());

        if (null === $commande) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        return $commande;
    }
}
