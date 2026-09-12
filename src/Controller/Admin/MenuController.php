<?php

namespace App\Controller\Admin;

use App\Entity\Menu;
use App\Form\MenuType;
use App\Repository\MenuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion des menus : création, consultation, modification, suppression.
 */
#[Route('/admin/menus')]
#[IsGranted('ROLE_ADMIN')]
class MenuController extends AbstractController
{
    #[Route('', name: 'app_admin_menu_index', methods: ['GET'])]
    public function index(MenuRepository $menus): Response
    {
        return $this->render('admin/menu/index.html.twig', [
            'menus' => $menus->findBy([], ['titre' => 'ASC']),
        ]);
    }

    #[Route('/nouveau', name: 'app_admin_menu_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $menu = new Menu();
        $form = $this->createForm(MenuType::class, $menu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($menu);
            $em->flush();

            $this->addFlash('success', sprintf('Le menu « %s » a été créé.', $menu->getTitre()));

            return $this->redirectToRoute('app_admin_menu_index');
        }

        return $this->render('admin/menu/new.html.twig', [
            'menu' => $menu,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_menu_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Menu $menu): Response
    {
        return $this->render('admin/menu/show.html.twig', [
            'menu' => $menu,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_admin_menu_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Menu $menu, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(MenuType::class, $menu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', sprintf('Le menu « %s » a été modifié.', $menu->getTitre()));

            return $this->redirectToRoute('app_admin_menu_index');
        }

        return $this->render('admin/menu/edit.html.twig', [
            'menu' => $menu,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_admin_menu_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Menu $menu, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('supprimer-menu-'.$menu->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        // Un menu déjà commandé est référencé par des commandes : le supprimer
        // ferait échouer la contrainte de clé étrangère et effacerait au passage
        // une partie de l'historique client.
        if (!$menu->getCommandes()->isEmpty()) {
            $this->addFlash('danger', sprintf(
                'Le menu « %s » ne peut pas être supprimé : il est rattaché à %d commande(s). Mettez son stock à zéro pour le retirer de la vente.',
                $menu->getTitre(),
                $menu->getCommandes()->count(),
            ));

            return $this->redirectToRoute('app_admin_menu_index');
        }

        $titre = $menu->getTitre();

        $em->remove($menu);
        $em->flush();

        $this->addFlash('success', sprintf('Le menu « %s » a été supprimé.', $titre));

        return $this->redirectToRoute('app_admin_menu_index');
    }
}
