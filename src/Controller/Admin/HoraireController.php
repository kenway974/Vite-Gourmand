<?php

namespace App\Controller\Admin;

use App\Entity\Horaire;
use App\Form\HoraireType;
use App\Repository\HoraireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/horaires')]
#[IsGranted('ROLE_EMPLOYE')]
class HoraireController extends AbstractController
{
    private const INTITULE = 'Les horaires';

    #[Route('', name: 'app_admin_horaire_index', methods: ['GET'])]
    public function index(HoraireRepository $depot): Response
    {
        return $this->render('admin/horaire/index.html.twig', [
            'elements' => $depot->semaine(),
            'joursLibres' => $depot->joursNonRenseignes(),
        ]);
    }

    #[Route('/nouveau', name: 'app_admin_horaire_new', methods: ['GET', 'POST'])]
    public function new(Request $request, HoraireRepository $depot, EntityManagerInterface $em): Response
    {
        $libres = $depot->joursNonRenseignes();

        if ([] === $libres) {
            $this->addFlash('danger', 'Les sept jours de la semaine sont déjà renseignés : modifiez le jour concerné.');

            return $this->redirectToRoute('app_admin_horaire_index');
        }

        $element = new Horaire();
        $form = $this->createForm(HoraireType::class, $element, ['jours' => $libres]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($element);
            $em->flush();

            $this->addFlash('success', sprintf('%s du %s ont été enregistrés.', self::INTITULE, $element->getJour()));

            return $this->redirectToRoute('app_admin_horaire_index');
        }

        return $this->render('admin/horaire/new.html.twig', [
            'element' => $element,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_admin_horaire_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Horaire $element, HoraireRepository $depot, EntityManagerInterface $em): Response
    {
        // Le jour en cours de modification reste proposé, sans quoi le
        // formulaire refuserait sa propre valeur.
        $jours = array_merge($depot->joursNonRenseignes(), [$element->getJour()]);
        usort($jours, fn (string $a, string $b) => array_search($a, Horaire::JOURS, true) <=> array_search($b, Horaire::JOURS, true));

        $form = $this->createForm(HoraireType::class, $element, ['jours' => $jours]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', sprintf('%s du %s ont été modifiés.', self::INTITULE, $element->getJour()));

            return $this->redirectToRoute('app_admin_horaire_index');
        }

        return $this->render('admin/horaire/edit.html.twig', [
            'element' => $element,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_admin_horaire_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Request $request, Horaire $element, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('supprimer-horaire-'.$element->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $jour = $element->getJour();

        $em->remove($element);
        $em->flush();

        // Supprimer une ligne fait disparaître le jour de l'affichage public :
        // un jour de fermeture se coche, il ne se supprime pas.
        $this->addFlash('success', sprintf('Le %s n\'apparaît plus dans les horaires.', $jour));

        return $this->redirectToRoute('app_admin_horaire_index');
    }
}
