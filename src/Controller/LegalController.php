<?php

namespace App\Controller;

use App\Repository\HoraireRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pages légales obligatoires.
 *
 * Mentions légales (art. 6 LCEN) et conditions générales de vente
 * (art. L111-1 du code de la consommation) ne sont pas optionnelles pour un
 * site qui prend des commandes. Le formulaire d'inscription fait déjà cocher
 * « j'accepte les CGV » : sans cette page, la case ne renvoie à rien.
 */
class LegalController extends AbstractController
{
    #[Route('/mentions-legales', name: 'app_mentions_legales', methods: ['GET'])]
    public function mentions(): Response
    {
        return $this->render('legal/mentions.html.twig');
    }

    #[Route('/conditions-generales-de-vente', name: 'app_cgv', methods: ['GET'])]
    public function cgv(HoraireRepository $horaires): Response
    {
        // Les CGV citent les horaires : autant les lire en base plutôt que de
        // les recopier, sous peine de les voir diverger au premier changement.
        return $this->render('legal/cgv.html.twig', [
            'semaine' => $horaires->semaine(),
        ]);
    }

    #[Route('/politique-de-confidentialite', name: 'app_confidentialite', methods: ['GET'])]
    public function confidentialite(): Response
    {
        return $this->render('legal/confidentialite.html.twig');
    }
}
