<?php

namespace App\Controller\Admin;

use App\Entity\ZoneLivraison;
use App\Form\ZoneLivraisonType;
use App\Repository\CommandeRepository;
use App\Repository\ZoneLivraisonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Zones de livraison desservies.
 *
 * Retirer une zone revient à cesser de livrer la commune : les commandes déjà
 * passées gardent le supplément qui leur a été appliqué, il est recopié sur
 * la commande au moment du calcul.
 */
#[Route('/admin/zones')]
#[IsGranted('ROLE_ADMIN')]
class ZoneLivraisonController extends AbstractController
{
    private const INTITULE = 'La zone';

    #[Route('', name: 'app_admin_zone_index', methods: ['GET'])]
    public function index(ZoneLivraisonRepository $depot): Response
    {
        return $this->render('admin/zone/index.html.twig', [
            'elements' => $depot->findToutes(),
        ]);
    }

    #[Route('/nouveau', name: 'app_admin_zone_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $element = new ZoneLivraison();
        $form = $this->createForm(ZoneLivraisonType::class, $element);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($element);
            $em->flush();

            $this->addFlash('success', sprintf('%s %s a été ajoutée.', self::INTITULE, $element->libelle()));

            return $this->redirectToRoute('app_admin_zone_index');
        }

        return $this->render('admin/zone/new.html.twig', [
            'element' => $element,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_admin_zone_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, ZoneLivraison $element, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ZoneLivraisonType::class, $element);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', sprintf('%s %s a été modifiée.', self::INTITULE, $element->libelle()));

            return $this->redirectToRoute('app_admin_zone_index');
        }

        return $this->render('admin/zone/edit.html.twig', [
            'element' => $element,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_admin_zone_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(
        Request $request,
        ZoneLivraison $element,
        CommandeRepository $commandes,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('supprimer-zone-'.$element->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        // Rien n'empêche techniquement la suppression — aucune clé étrangère
        // ne relie la commande à la zone — mais prévenir vaut mieux que de
        // laisser des commandes en cours vers une commune qu'on ne dessert plus.
        $enCours = $commandes->compterEnCoursPour($element->getCodePostal());

        if ($enCours > 0) {
            $this->addFlash('danger', sprintf(
                '%s %s ne peut pas être retirée : %d commande(s) en cours y sont attendues.',
                self::INTITULE,
                $element->libelle(),
                $enCours,
            ));

            return $this->redirectToRoute('app_admin_zone_index');
        }

        $libelle = $element->libelle();

        $em->remove($element);
        $em->flush();

        $this->addFlash('success', sprintf('%s %s n\'est plus desservie.', self::INTITULE, $libelle));

        return $this->redirectToRoute('app_admin_zone_index');
    }
}
