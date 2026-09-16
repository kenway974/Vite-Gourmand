<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\Menu;
use App\Entity\SuiviCommande;
use App\Entity\Utilisateur;
use App\Form\CommandeType;
use App\Service\CalculateurPrix;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Passage d'une commande par un client connecté.
 */
#[IsGranted('ROLE_USER')]
class CommandeController extends AbstractController
{
    #[Route('/commander/{id}', name: 'app_commande_nouvelle', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function nouvelle(
        Request $request,
        Menu $menu,
        EntityManagerInterface $em,
        CalculateurPrix $calculateur,
    ): Response {
        $minimum = $menu->getNbMinPersonnes() ?? 1;
        $delai = $menu->getDelaiCommandeJours() ?? 0;
        $premiereDate = (new \DateTime('today'))->modify(sprintf('+%d days', $delai));

        $commande = new Commande();
        $commande->setMenu($menu);
        $commande->setNbPersonnes($minimum);
        $commande->setDatePrestation($premiereDate);

        $form = $this->createForm(CommandeType::class, $commande, [
            'aide_delai' => sprintf('Au plus tôt le %s, soit %d jours de préparation.', $premiereDate->format('d/m/Y'), $delai),
            'aide_convives' => sprintf(
                'À partir de %d convives. Remise de 10 %% dès %d.',
                $minimum,
                $minimum + CalculateurPrix::CONVIVES_AU_DELA_DU_MINIMUM,
            ),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Utilisateur $utilisateur */
            $utilisateur = $this->getUser();

            $commande->setUtilisateur($utilisateur);
            $commande->setDateCommande(new \DateTime());
            $commande->setStatut(Commande::EN_ATTENTE);

            // Effectif, total et remise posés ensemble : le total ne peut pas
            // être enregistré sans la remise qui l'explique.
            $commande->appliquerPrix($calculateur->calculer($menu, $commande->getNbPersonnes()));

            // Le catalogue exprime un nombre de prestations disponibles.
            $menu->setStock(max(0, ($menu->getStock() ?? 0) - 1));

            $em->persist($commande);
            $em->persist($this->premierSuivi($commande));
            $em->flush();

            $this->addFlash('success', 'Votre demande a bien été enregistrée. Nous revenons vers vous sous 48 heures.');

            return $this->redirectToRoute('app_commande_confirmation', ['id' => $commande->getId()]);
        }

        return $this->render('commande/nouvelle.html.twig', [
            'menu' => $menu,
            'form' => $form,
            'exemplePrix' => $calculateur->calculer($menu, $minimum),
        ]);
    }

    #[Route('/commande/{id}/confirmation', name: 'app_commande_confirmation', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function confirmation(Commande $commande): Response
    {
        // Une commande n'appartient qu'à son auteur : sans ce contrôle, changer
        // l'identifiant dans l'URL donnerait accès à la commande d'autrui.
        if ($commande->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Cette commande ne vous appartient pas.');
        }

        return $this->render('commande/confirmation.html.twig', ['commande' => $commande]);
    }

    private function premierSuivi(Commande $commande): SuiviCommande
    {
        return (new SuiviCommande())
            ->setCommande($commande)
            ->setStatut(Commande::EN_ATTENTE)
            ->setDateModification(new \DateTime())
            ->setModeContact('site web');
    }
}
