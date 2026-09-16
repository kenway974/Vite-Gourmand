<?php

namespace App\Controller;

use App\Repository\AvisRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Pages minimales nécessaires au parcours d'authentification.
 *
 * NOTE : ce sont des gabarits provisoires. La vraie page d'accueil et les
 * espaces utilisateur / employé / admin sont à construire dans la phase front-end.
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

    #[Route('/mon-compte', name: 'app_compte', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function compte(): Response
    {
        return $this->render('home/compte.html.twig');
    }
}
