<?php

namespace App\Controller\Admin;

use App\Entity\Regime;
use App\Form\RegimeType;
use App\Repository\RegimeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/regimes')]
#[IsGranted('ROLE_ADMIN')]
class RegimeController extends AbstractController
{
    private const INTITULE = 'Le régime';

    #[Route('', name: 'app_admin_regime_index', methods: ['GET'])]
    public function index(RegimeRepository $depot): Response
    {
        return $this->render('admin/regime/index.html.twig', [
            'elements' => $depot->findBy([], ['libelle' => 'ASC']),
        ]);
    }

    #[Route('/nouveau', name: 'app_admin_regime_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $element = new Regime();
        $form = $this->createForm(RegimeType::class, $element);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($element);
            $em->flush();

            $this->addFlash('success', sprintf('%s « %s » a été créé.', self::INTITULE, $element->getLibelle()));

            return $this->redirectToRoute('app_admin_regime_index');
        }

        return $this->render('admin/regime/new.html.twig', [
            'element' => $element,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_admin_regime_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Regime $element, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(RegimeType::class, $element);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', sprintf('%s « %s » a été modifié.', self::INTITULE, $element->getLibelle()));

            return $this->redirectToRoute('app_admin_regime_index');
        }

        return $this->render('admin/regime/edit.html.twig', [
            'element' => $element,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_admin_regime_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Request $request, Regime $element, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('supprimer-regime-'.$element->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        // On ne supprime jamais un élément encore référencé ailleurs : cela
        // romprait une contrainte de clé étrangère, ou modifierait en silence
        // des données existantes.
        $references = $element->getMenus();

        if (!$references->isEmpty()) {
            $this->addFlash('danger', sprintf(
                '%s « %s » ne peut pas être supprimé : il est utilisé par %d menu(s). Réaffectez ces menus à un autre régime.',
                self::INTITULE,
                $element->getLibelle(),
                $references->count(),
            ));

            return $this->redirectToRoute('app_admin_regime_index');
        }

        $intitule = $element->getLibelle();

        $em->remove($element);
        $em->flush();

        $this->addFlash('success', sprintf('%s « %s » a été supprimé.', self::INTITULE, $intitule));

        return $this->redirectToRoute('app_admin_regime_index');
    }
}
