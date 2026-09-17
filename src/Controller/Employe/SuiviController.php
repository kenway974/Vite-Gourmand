<?php

namespace App\Controller\Employe;

use App\Entity\Avis;
use App\Entity\Commande;
use App\Entity\SuiviCommande;
use App\Repository\AvisRepository;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Espace employé : suivi des commandes et modération des avis.
 *
 * ROLE_EMPLOYE suffit ; la hiérarchie des rôles donne l'accès aux
 * administrateurs par héritage, sans avoir à le déclarer ici.
 */
#[Route('/employe')]
#[IsGranted('ROLE_EMPLOYE')]
class SuiviController extends AbstractController
{
    #[Route('', name: 'app_employe', methods: ['GET'])]
    public function index(CommandeRepository $commandes, AvisRepository $avis): Response
    {
        return $this->render('employe/index.html.twig', [
            'aPreparerAujourdhui' => $commandes->findAPreparerPour(new \DateTime('today')),
            'aPreparerDemain' => $commandes->findAPreparerPour(new \DateTime('tomorrow')),
            'avisEnAttente' => \count($avis->findEnAttenteDeModeration()),
            'nouvellesCommandes' => \count($commandes->findPourSuivi(Commande::EN_ATTENTE)),
        ]);
    }

    #[Route('/commandes', name: 'app_employe_commandes', methods: ['GET'])]
    public function commandes(Request $request, CommandeRepository $commandes): Response
    {
        $statut = $request->query->getString('statut');

        // Un statut inconnu ne filtre rien plutôt que de renvoyer une page vide
        // sans explication.
        if ('' !== $statut && !\in_array($statut, Commande::STATUTS, true)) {
            $statut = '';
        }

        return $this->render('employe/commandes.html.twig', [
            'commandes' => $commandes->findPourSuivi($statut ?: null),
            'statutActif' => $statut,
            'statuts' => Commande::STATUTS,
        ]);
    }

    #[Route('/commandes/{id}/statut', name: 'app_employe_commande_statut', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function changerStatut(Request $request, Commande $commande, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('statut-commande-'.$commande->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $nouveau = $request->getPayload()->getString('statut');

        if (!\in_array($nouveau, Commande::STATUTS, true)) {
            $this->addFlash('danger', 'Statut inconnu.');

            return $this->redirectToRoute('app_employe_commandes');
        }

        // Une commande livrée ou annulée ne se remet pas en mouvement : son
        // historique doit rester le reflet de ce qui s'est réellement passé.
        if ($commande->estTerminee()) {
            $this->addFlash('danger', sprintf('Cette commande est %s : son statut ne change plus.', $commande->getStatut()));

            return $this->redirectToRoute('app_employe_commandes');
        }

        $commande->setStatut($nouveau);

        $em->persist(
            (new SuiviCommande())
                ->setCommande($commande)
                ->setStatut($nouveau)
                ->setDateModification(new \DateTime())
                ->setModeContact($request->getPayload()->getString('modeContact') ?: 'email')
                ->setMotif($request->getPayload()->getString('motif') ?: null)
        );

        $em->flush();

        $this->addFlash('success', sprintf('Commande n° %d passée à « %s ».', $commande->getId(), $nouveau));

        return $this->redirectToRoute('app_employe_commandes');
    }

    #[Route('/avis', name: 'app_employe_avis', methods: ['GET'])]
    public function avis(AvisRepository $avis): Response
    {
        return $this->render('employe/avis.html.twig', [
            'avis' => $avis->findEnAttenteDeModeration(),
            'statistiques' => $avis->statistiquesPubliees(),
        ]);
    }

    #[Route('/avis/{id}/moderer', name: 'app_employe_avis_moderer', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function modererAvis(Request $request, Avis $avis, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('moderer-avis-'.$avis->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $decision = $request->getPayload()->getString('decision');

        if (!\in_array($decision, [Avis::VALIDE, Avis::REFUSE], true)) {
            $this->addFlash('danger', 'Décision inconnue.');

            return $this->redirectToRoute('app_employe_avis');
        }

        $avis->setStatutValidation($decision);
        $em->flush();

        $this->addFlash('success', Avis::VALIDE === $decision ? 'Avis publié.' : 'Avis refusé.');

        return $this->redirectToRoute('app_employe_avis');
    }
}
