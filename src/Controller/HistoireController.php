<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Notre histoire » — page éditoriale de présentation.
 *
 * Contenu fixe, écrit dans le gabarit : rien ici ne dépend de la base, et
 * rendre ce texte administrable demanderait une entité, un formulaire et un
 * écran de rédaction que le cahier des charges ne demande pas.
 */
class HistoireController extends AbstractController
{
    #[Route('/notre-histoire', name: 'app_histoire', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('histoire/index.html.twig');
    }
}
