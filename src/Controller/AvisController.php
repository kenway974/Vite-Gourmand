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

    #[Route('/avis', name: 'app_avis', methods: ['GET'])]
    public function index(Request $request, AvisRepository $avis): Response
    {
        // getInt() lève une exception sur « abc » et rendrait un 400 : une
        // adresse mal recopiée ne doit pas afficher une erreur au visiteur.
        // Le transtypage ramène tout ce qui n'est pas un nombre à 0, que
        // max() renvoie sur la première page plutôt que sur un décalage
        // négatif.
        $page = max(1, (int) $request->query->get('page', 1));

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
