<?php

namespace App\Controller;

use App\Entity\Site;
use App\Entity\User;
use App\Form\SiteFormType;
use App\Repository\SiteRepository;
use App\Security\Voter\OwnershipVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/sites')]
class SiteController extends AbstractController
{
    #[Route('', name: 'app_site_index', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, SiteRepository $sites): Response
    {
        return $this->render('site/index.html.twig', ['sites' => $sites->findForOwner($user)]);
    }

    #[Route('/new', name: 'app_site_new', methods: ['GET', 'POST'])]
    public function new(#[CurrentUser] User $user, Request $request, EntityManagerInterface $em): Response
    {
        $site = new Site($user);
        $form = $this->createForm(SiteFormType::class, $site)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($site);
            $em->flush();
            $this->addFlash('success', sprintf('Site « %s » créé.', $site->getName()));

            return $this->redirectToRoute('app_site_index');
        }

        return $this->render('site/form.html.twig', ['form' => $form, 'site' => null]);
    }

    #[Route('/{id}/edit', name: 'app_site_edit', methods: ['GET', 'POST'])]
    #[IsGranted(OwnershipVoter::MANAGE, 'site')]
    public function edit(Site $site, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(SiteFormType::class, $site)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Site mis à jour.');

            return $this->redirectToRoute('app_site_index');
        }

        return $this->render('site/form.html.twig', ['form' => $form, 'site' => $site]);
    }

    #[Route('/{id}/delete', name: 'app_site_delete', methods: ['POST'])]
    #[IsGranted(OwnershipVoter::MANAGE, 'site')]
    public function delete(Site $site, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete-site-'.$site->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $em->remove($site);
        $em->flush();
        $this->addFlash('success', 'Site supprimé, ainsi que ses appareils.');

        return $this->redirectToRoute('app_site_index');
    }
}
