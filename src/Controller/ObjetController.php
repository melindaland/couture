<?php

namespace App\Controller;

use App\Entity\Objet;
use App\Form\ObjetType;
use App\Repository\ObjetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/objet')]
final class ObjetController extends AbstractController
{
    // LISTE DES OBJETS - visible à tous
    #[Route(name: 'app_objet_index', methods: ['GET'])]
    public function index(ObjetRepository $objetRepository): Response
    {
        $objets = $objetRepository->findAll();
        return $this->render('objet/index.html.twig', [
            'objets' => $objets,
        ]);
    }

    // AJOUT D'UN OBJET - autorisé à PRO et ADMIN et USER
    #[Route('/new', name: 'app_objet_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_USER') && !$this->isGranted('ROLE_PRO') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException("Accès refusé : vous devez être connecté.");
        }

        $objet = new Objet();
        $form = $this->createForm(ObjetType::class, $objet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $objet->setProprietaire($this->getUser());
            $entityManager->persist($objet);
            $entityManager->flush();

            return $this->redirectToRoute('app_objet_index');
        }

        return $this->render('objet/new.html.twig', [
            'form' => $form,
            'objet' => $objet,
        ]);
    }

    // AFFICHAGE DÉTAILLÉ D’UN OBJET 
    #[Route('/{id}', name: 'app_objet_show', methods: ['GET'])]
    public function show(Objet $objet): Response
    {
        return $this->render('objet/show.html.twig', [
            'objet' => $objet,
        ]);
    }

    // MODIFICATION D’UN OBJET - PRO peut modifier ses objets / ADMIN peut tout modifier
    #[Route('/{id}/edit', name: 'app_objet_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Objet $objet, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && $objet->getProprietaire()  !== $this->getUser()) {
            throw $this->createAccessDeniedException("Vous n'avez pas le droit de modifier cet objet. Vous ne l'avez pas mis à disposition.");
        }

        $form = $this->createForm(ObjetType::class, $objet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            return $this->redirectToRoute('app_objet_index');
        }

        return $this->render('objet/edit.html.twig', [
            'form' => $form,
            'objet' => $objet,
        ]);
    }

    // SUPPRESSION D’UN OBJET - PRO peut supprimer ses objets / ADMIN peut tout supprimer
    #[Route('/{id}', name: 'app_objet_delete', methods: ['POST'])]
    public function delete(Request $request, Objet $objet, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && ($objet->getProprietaire() !== $this->getUser() || !$this->isGranted('ROLE_PRO'))) {
            throw $this->createAccessDeniedException("Vous n'avez pas le droit de supprimer cet objet. Vous ne l'avez pas mis à disposition.");
        }
        
        if ($this->isCsrfTokenValid('delete'.$objet->getId(), $request->get('_token'))) {
            foreach ($objet->getReservations() as $reservation) {
                $reservation->setObjet(null);
                $entityManager->persist($reservation);
            }
            $entityManager->remove($objet);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_objet_index');
    }
}
