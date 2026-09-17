<?php

namespace App\Controller;

use App\Repository\AvisRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Les avis publiés, tous menus confondus.
 *
 * Page publique : un visiteur qui hésite doit pouvoir lire les retours sans
 * créer de compte. Seuls les avis validés par le personnel sortent d'ici — la
 * page d'accueil et les fiches menu appliquent déjà la même règle.
 *
 * Le dépôt d'un avis, lui, reste réservé au client qui a reçu la commande :
 * c'est ce qui donne son sens au mot « vérifiés » affiché à côté de la note.
 */
class AvisController extends AbstractController
{
    private const PAR_PAGE = 10;

    /**
     * Plafond du numéro de page.
     *
     * Sans lui, « ?page=9999999999999999999 » déborde la capacité des entiers
     * au calcul du décalage : le produit devient un flottant et Doctrine
     * refuse l'argument, ce qui rend un 500. Au-delà de ce plafond il n'y a de
     * toute façon aucun avis à montrer.
     */
    private const PAGE_MAX = 1_000_000;

    #[Route('/avis', name: 'app_avis', methods: ['GET'])]
    public function index(Request $request, AvisRepository $avis): Response
    {
        // Une adresse mal recopiée ne doit jamais afficher d'erreur au
        // visiteur. Deux pièges évités ici :
        //   - get() et getInt() lèvent une exception sur « ?page[]=2 », donc
        //     un 400 : on lit la valeur brute et on écarte ce qui n'est pas
        //     scalaire ;
        //   - le transtypage d'un nombre démesuré rend PHP_INT_MAX, dont le
        //     produit par PAR_PAGE déborde en flottant : le plafond l'évite.
        $brut = $request->query->all()['page'] ?? 1;
        $page = \is_scalar($brut) ? (int) $brut : 1;
        $page = max(1, min($page, self::PAGE_MAX));

        $publies = $avis->findPublies($page, self::PAR_PAGE);
        // Paginator::count() rend le total de la recherche, pas la taille de
        // la tranche : les deux sont nécessaires et ne doivent pas être
        // confondus. Le gabarit reçoit la tranche, déroulée en tableau.
        $total = \count($publies);

        return $this->render('avis/index.html.twig', [
            'avis' => iterator_to_array($publies),
            'total' => $total,
            // La moyenne porte sur l'ensemble des avis publiés, pas sur la
            // page affichée : une note qui changerait en tournant la page ne
            // voudrait rien dire.
            'notation' => $avis->statistiquesPubliees(),
            'page' => $page,
            'nbPages' => (int) ceil($total / self::PAR_PAGE),
        ]);
    }
}
