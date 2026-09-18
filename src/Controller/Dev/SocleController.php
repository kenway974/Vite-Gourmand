<?php

namespace App\Controller\Dev;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Page de référence du socle graphique : tous les composants réutilisables
 * rassemblés sur un écran, pour les relire d'un coup d'œil.
 *
 * Volontairement SANS attribut #[Route] : sa route est déclarée dans
 * config/routes/dev/socle.yaml, que Symfony ne charge qu'en environnement de
 * développement. Une page de démonstration n'a rien à faire en production,
 * et la laisser dépendre d'un simple « on oubliera de la retirer » serait
 * une mauvaise façon de s'en assurer.
 */
class SocleController extends AbstractController
{
    public function index(): Response
    {
        return $this->render('dev/socle.html.twig');
    }
}
