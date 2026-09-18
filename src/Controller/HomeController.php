<?php

namespace App\Controller;

use App\Repository\AvisRepository;
use App\Repository\ThemeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page d'accueil.
 *
 * Tout ce qu'elle affiche vient de la base : les thèmes mis en avant, la note
 * moyenne, les témoignages. Rien n'est recopié en dur, sans quoi la vitrine
 * finirait par annoncer autre chose que ce que le catalogue propose.
 */
class HomeController extends AbstractController
{
    /** Trois témoignages : au-delà, la bande devient un mur de texte. */
    private const NB_TEMOIGNAGES = 3;

    /** Quatre thèmes, comme les quatre cartes de la maquette. */
    private const NB_THEMES = 4;

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(AvisRepository $avis, ThemeRepository $themes): Response
    {
        return $this->render('home/index.html.twig', [
            // La moyenne annoncée en accueil porte sur TOUS les menus, là où
            // la fiche menu affiche celle de ce menu seul. Deux agrégats
            // distincts, deux requêtes distinctes.
            'notation' => $avis->statistiquesPubliees(),
            'themes' => $themes->findPourAccueil(self::NB_THEMES),
            // Les mêmes avis publiés que la page dédiée, dans le même ordre :
            // un visiteur qui suit le lien doit retrouver ce qu'il a lu.
            'temoignages' => iterator_to_array($avis->findPublies(1, self::NB_TEMOIGNAGES)),
        ]);
    }
}
