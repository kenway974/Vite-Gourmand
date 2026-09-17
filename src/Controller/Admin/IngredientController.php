<?php

namespace App\Controller\Admin;

use App\Entity\Ingredient;
use App\Form\IngredientType;
use App\Repository\IngredientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/ingredients')]
#[IsGranted('ROLE_ADMIN')]
class IngredientController extends AbstractController
{
    private const INTITULE = 'L\'ingrédient';

    #[Route('', name: 'app_admin_ingredient_index', methods: ['GET'])]
    public function index(IngredientRepository $depot): Response
    {
        return $this->render('admin/ingredient/index.html.twig', [
            'elements' => $depot->findPourAdministration(),
        ]);
    }

    #[Route('/nouveau', name: 'app_admin_ingredient_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $element = new Ingredient();
        $form = $this->createForm(IngredientType::class, $element);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($element);
            $em->flush();

            $this->addFlash('success', sprintf('%s « %s » a été créé.', self::INTITULE, $element->getNom()));

            return $this->redirectToRoute('app_admin_ingredient_index');
        }

        return $this->render('admin/ingredient/new.html.twig', [
            'element' => $element,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_admin_ingredient_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Ingredient $element, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(IngredientType::class, $element);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', sprintf('%s « %s » a été modifié.', self::INTITULE, $element->getNom()));

            return $this->redirectToRoute('app_admin_ingredient_index');
        }

        return $this->render('admin/ingredient/edit.html.twig', [
            'element' => $element,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_admin_ingredient_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Request $request, Ingredient $element, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('supprimer-ingredient-'.$element->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        // On ne supprime jamais un élément encore référencé ailleurs : cela
        // romprait une contrainte de clé étrangère, ou modifierait en silence
        // des données existantes.
        $references = $element->getPlats();

        if (!$references->isEmpty()) {
            $this->addFlash('danger', sprintf(
                '%s « %s » ne peut pas être supprimé : il entre dans la composition de %d plat(s). Retirez-le d\'abord de ces plats.',
                self::INTITULE,
                $element->getNom(),
                $references->count(),
            ));

            return $this->redirectToRoute('app_admin_ingredient_index');
        }

        $intitule = $element->getNom();

        $em->remove($element);
        $em->flush();

        $this->addFlash('success', sprintf('%s « %s » a été supprimé.', self::INTITULE, $intitule));

        return $this->redirectToRoute('app_admin_ingredient_index');
    }
}
