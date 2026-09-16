<?php

namespace App\Controller\Admin;

use App\Entity\Allergene;
use App\Entity\Commande;
use App\Entity\Horaire;
use App\Entity\Ingredient;
use App\Entity\Menu;
use App\Entity\Plat;
use App\Entity\Regime;
use App\Entity\Theme;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Tableau de bord de l'espace d'administration.
 *
 * La règle access_control sur ^/admin protège déjà ces routes ; l'attribut
 * IsGranted les protège une seconde fois, au cas où la configuration du
 * pare-feu évoluerait.
 */
#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    #[Route('', name: 'app_admin', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'nbMenus' => $em->getRepository(Menu::class)->count([]),
            'nbPlats' => $em->getRepository(Plat::class)->count([]),
            'nbIngredients' => $em->getRepository(Ingredient::class)->count([]),
            'nbAllergenes' => $em->getRepository(Allergene::class)->count([]),
            'nbThemes' => $em->getRepository(Theme::class)->count([]),
            'nbRegimes' => $em->getRepository(Regime::class)->count([]),
            'nbUtilisateurs' => $em->getRepository(Utilisateur::class)->count([]),
            'nbCommandes' => $em->getRepository(Commande::class)->count([]),
            'nbHoraires' => $em->getRepository(Horaire::class)->count([]),
        ]);
    }
}
