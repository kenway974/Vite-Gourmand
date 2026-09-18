<?php

namespace App\Controller\Employe;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Suivi du matériel prêté : plats et présentoirs sont à restituer sous dix
 * jours ouvrés, sans quoi une indemnité de 600 € s'applique.
 */
#[Route('/employe/materiel')]
#[IsGranted('ROLE_EMPLOYE')]
class MaterielController extends AbstractController
{
    #[Route('', name: 'app_employe_materiel', methods: ['GET'])]
    public function index(Request $request, CommandeRepository $commandes): Response
    {
        $restitue = $request->query->getBoolean('rendus');

        return $this->render('employe/materiel.html.twig', [
            'commandes' => $commandes->findMaterielPrete($restitue),
            'restitue' => $restitue,
            'enAttente' => \count($commandes->findMaterielPrete(false)),
        ]);
    }

    #[Route('/{id}/restitution', name: 'app_employe_materiel_restitution', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function restitution(Request $request, Commande $commande, EntityManagerInterface $em): Response
    {
        $this->verifierJeton($request, 'restitution-materiel-'.$commande->getId());

        if (null === $commande->getDateRestitutionMateriel()) {
            $commande->restituerMateriel();
            $message = sprintf('Retour du matériel de la commande n° %d enregistré.', $commande->getId());
        } else {
            $commande->annulerRestitutionMateriel();
            $message = sprintf('Retour du matériel de la commande n° %d annulé.', $commande->getId());
        }

        $em->flush();
        $this->addFlash('success', $message);

        return $this->redirectToRoute('app_employe_materiel', $this->filtreCourant($request));
    }

    #[Route('/{id}/indemnite', name: 'app_employe_materiel_indemnite', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function indemnite(Request $request, Commande $commande, EntityManagerInterface $em): Response
    {
        $this->verifierJeton($request, 'indemnite-materiel-'.$commande->getId());

        try {
            $commande->appliquerIndemniteMateriel();
        } catch (\LogicException $e) {
            // Le bouton n'apparaît qu'au-delà du délai ; une requête forgée,
            // ou une page restée ouverte, peut tout de même arriver ici.
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('app_employe_materiel', $this->filtreCourant($request));
        }

        $em->flush();

        $this->addFlash('success', sprintf(
            'Indemnité de %s € appliquée à la commande n° %d.',
            Commande::INDEMNITE_MATERIEL,
            $commande->getId(),
        ));

        return $this->redirectToRoute('app_employe_materiel', $this->filtreCourant($request));
    }

    private function verifierJeton(Request $request, string $intention): void
    {
        if (!$this->isCsrfTokenValid($intention, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }

    /**
     * Ramène l'employé sur la liste qu'il consultait plutôt que sur l'autre.
     *
     * @return array<string, string>
     */
    private function filtreCourant(Request $request): array
    {
        return $request->request->getBoolean('rendus') ? ['rendus' => '1'] : [];
    }
}
