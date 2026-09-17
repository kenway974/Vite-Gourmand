<?php

namespace App\Controller;

use App\Repository\AvisRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page d'accueil.
 *
 * NOTE : gabarit provisoire, la vraie page d'accueil est à construire dans
 * la phase front-end.
 */
class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(AvisRepository $avis): Response
    {
        return $this->render('home/index.html.twig', [
            // La moyenne annoncée en accueil porte sur TOUS les menus, là où
            // la fiche menu affiche celle de ce menu seul. Deux agrégats
            // distincts, deux requêtes distinctes.
            'notation' => $avis->statistiquesPubliees(),
        ]);
    }
}
