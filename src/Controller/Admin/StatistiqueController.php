<?php

namespace App\Controller\Admin;

use App\Statistiques\CalculateurStatistiques;
use App\Statistiques\DepotStatistiques;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Statistiques d'activité.
 *
 * Réservées à l'administrateur : le cahier des charges les range avec la
 * gestion des comptes, pas avec le catalogue.
 *
 * Les chiffres du jour sont recalculés depuis MySQL à chaque affichage, et non
 * lus dans le dernier relevé : l'écran doit refléter l'activité de l'instant,
 * pas celle de la dernière exécution de la commande. Le dépôt NoSQL sert
 * l'évolution dans le temps, que MySQL ne saurait pas reconstituer après coup.
 */
#[Route('/admin/statistiques')]
#[IsGranted('ROLE_ADMIN')]
class StatistiqueController extends AbstractController
{
    #[Route('', name: 'app_admin_statistiques', methods: ['GET'])]
    public function index(
        CalculateurStatistiques $calculateur,
        DepotStatistiques $depot,
    ): Response {
        $disponible = $depot->disponible();

        return $this->render('admin/statistiques.html.twig', [
            'aujourdhui' => $calculateur->calculer(),
            // Une base de statistiques injoignable ne doit pas fermer l'écran :
            // les chiffres du jour restent lisibles, seul l'historique manque.
            'historique' => $disponible ? $depot->historique(15) : [],
            'depotDisponible' => $disponible,
        ]);
    }
}
