<?php

namespace App\Controller\Admin;

use App\Entity\Allergene;
use App\Form\AllergeneType;
use App\Repository\AllergeneRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/allergenes')]
#[IsGranted('ROLE_EMPLOYE')]
class AllergeneController extends AbstractController
{
    private const INTITULE = 'L\'allergène';

    #[Route('', name: 'app_admin_allergene_index', methods: ['GET'])]
    public function index(AllergeneRepository $depot): Response
    {
        return $this->render('admin/allergene/index.html.twig', [
            'elements' => $depot->findPourAdministration(),
        ]);
    }

    #[Route('/nouveau', name: 'app_admin_allergene_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $element = new Allergene();
        $form = $this->createForm(AllergeneType::class, $element);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($element);
            $em->flush();

            $this->addFlash('success', sprintf('%s « %s » a été créé.', self::INTITULE, $element->getLibelle()));

            return $this->redirectToRoute('app_admin_allergene_index');
        }

        return $this->render('admin/allergene/new.html.twig', [
            'element' => $element,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_admin_allergene_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Allergene $element, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(AllergeneType::class, $element);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', sprintf('%s « %s » a été modifié.', self::INTITULE, $element->getLibelle()));

            return $this->redirectToRoute('app_admin_allergene_index');
        }

        return $this->render('admin/allergene/edit.html.twig', [
            'element' => $element,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_admin_allergene_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Request $request, Allergene $element, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('supprimer-allergene-'.$element->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        // On ne supprime jamais un élément encore référencé ailleurs : cela
        // romprait une contrainte de clé étrangère, ou modifierait en silence
        // des données existantes.
        $references = $element->getIngredients();

        if (!$references->isEmpty()) {
            $this->addFlash('danger', sprintf(
                '%s « %s » ne peut pas être supprimé : il est rattaché à %d ingrédient(s). Détachez-le d\'abord de ces ingrédients : supprimer un allergène encore référencé effacerait une information de sécurité alimentaire.',
                self::INTITULE,
                $element->getLibelle(),
                $references->count(),
            ));

            return $this->redirectToRoute('app_admin_allergene_index');
        }

        $intitule = $element->getLibelle();

        $em->remove($element);
        $em->flush();

        $this->addFlash('success', sprintf('%s « %s » a été supprimé.', self::INTITULE, $intitule));

        return $this->redirectToRoute('app_admin_allergene_index');
    }
}
