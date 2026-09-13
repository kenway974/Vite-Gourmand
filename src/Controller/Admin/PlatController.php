<?php

namespace App\Controller\Admin;

use App\Entity\Plat;
use App\Form\PlatType;
use App\Repository\PlatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/plats')]
#[IsGranted('ROLE_ADMIN')]
class PlatController extends AbstractController
{
    private const INTITULE = 'Le plat';

    #[Route('', name: 'app_admin_plat_index', methods: ['GET'])]
    public function index(PlatRepository $depot): Response
    {
        return $this->render('admin/plat/index.html.twig', [
            'elements' => $depot->findBy([], ['nom' => 'ASC']),
        ]);
    }

    #[Route('/nouveau', name: 'app_admin_plat_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $element = new Plat();
        $form = $this->createForm(PlatType::class, $element);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($element);
            $em->flush();

            $this->addFlash('success', sprintf('%s « %s » a été créé.', self::INTITULE, $element->getNom()));

            return $this->redirectToRoute('app_admin_plat_index');
        }

        return $this->render('admin/plat/new.html.twig', [
            'element' => $element,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_admin_plat_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Plat $element, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PlatType::class, $element);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', sprintf('%s « %s » a été modifié.', self::INTITULE, $element->getNom()));

            return $this->redirectToRoute('app_admin_plat_index');
        }

        return $this->render('admin/plat/edit.html.twig', [
            'element' => $element,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_admin_plat_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Request $request, Plat $element, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('supprimer-plat-'.$element->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        // On ne supprime jamais un élément encore référencé ailleurs : cela
        // romprait une contrainte de clé étrangère, ou modifierait en silence
        // des données existantes.
        $references = $element->getMenus();

        if (!$references->isEmpty()) {
            $this->addFlash('danger', sprintf(
                '%s « %s » ne peut pas être supprimé : il est utilisé dans %d menu(s). Retirez-le d\'abord de ces menus.',
                self::INTITULE,
                $element->getNom(),
                $references->count(),
            ));

            return $this->redirectToRoute('app_admin_plat_index');
        }

        $intitule = $element->getNom();

        $em->remove($element);
        $em->flush();

        $this->addFlash('success', sprintf('%s « %s » a été supprimé.', self::INTITULE, $intitule));

        return $this->redirectToRoute('app_admin_plat_index');
    }
}
