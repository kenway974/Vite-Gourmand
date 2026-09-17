<?php

namespace App\Controller;

use App\Entity\Menu;
use App\Repository\AllergeneRepository;
use App\Repository\AvisRepository;
use App\Repository\MenuRepository;
use App\Repository\RegimeRepository;
use App\Repository\ThemeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Catalogue public : c'est la seule porte d'entrée vers un menu, et donc
 * vers la commande. Aucune authentification n'est demandée pour consulter.
 */
class CatalogueController extends AbstractController
{
    private const PAR_PAGE = 9;

    #[Route('/menus', name: 'app_catalogue', methods: ['GET'])]
    public function index(
        Request $request,
        MenuRepository $menus,
        AvisRepository $avis,
        ThemeRepository $themes,
        RegimeRepository $regimes,
    ): Response {
        $filtres = [
            'recherche' => trim($request->query->getString('q')) ?: null,
            'tri' => $request->query->getString('tri') ?: null,
            // find(0) déclencherait une requête inutile à chaque visite sans
            // filtre : on ne va en base que si un identifiant est réellement
            // passé. Un identifiant inconnu vaut « pas de filtre ».
            'theme' => ($idTheme = $request->query->getInt('theme')) > 0 ? $themes->find($idTheme) : null,
            'regime' => ($idRegime = $request->query->getInt('regime')) > 0 ? $regimes->find($idRegime) : null,
            // Hors palier, le filtre est ignoré : la valeur vient de l'URL et
            // n'a pas à atteindre la requête telle quelle.
            'nbPersonnes' => \in_array($convives = $request->query->getInt('convives'), MenuRepository::PALIERS_CONVIVES, true)
                ? $convives
                : null,
            'seulementCommandables' => $request->query->getBoolean('commandables'),
        ];

        $page = max(1, $request->query->getInt('page', 1));
        $resultats = $menus->findCatalogue($filtres, $page, self::PAR_PAGE);

        $total = \count($resultats);
        $liste = iterator_to_array($resultats, false);

        return $this->render('catalogue/index.html.twig', [
            'menus' => $liste,
            'notes' => $avis->notesParMenu(array_map(fn (Menu $m) => $m->getId(), $liste)),
            'themes' => $themes->findBy([], ['libelle' => 'ASC']),
            'regimes' => $regimes->findBy([], ['libelle' => 'ASC']),
            'tris' => MenuRepository::TRIS,
            'paliers' => MenuRepository::PALIERS_CONVIVES,
            'filtres' => $filtres,
            'page' => $page,
            'nbPages' => max(1, (int) ceil($total / self::PAR_PAGE)),
            'total' => $total,
        ]);
    }

    #[Route('/menus/{id}', name: 'app_catalogue_menu', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(
        int $id,
        MenuRepository $menus,
        AllergeneRepository $allergenes,
        AvisRepository $avis,
    ): Response {
        $menu = $menus->findDetail($id);

        if (null === $menu) {
            throw $this->createNotFoundException('Ce menu n\'existe pas.');
        }

        return $this->render('catalogue/show.html.twig', [
            'menu' => $menu,
            // Les allergènes ne sont pas saisis sur le menu : ils sont déduits
            // de ses plats et de leurs ingrédients. C'est une obligation
            // réglementaire, une liste recopiée à la main dériverait.
            'allergenes' => $allergenes->findPourMenu($menu),
            'avis' => $avis->findPubliesPourMenu($menu),
            'note' => $avis->notesParMenu([$menu->getId()])[$menu->getId()] ?? null,
        ]);
    }
}
