<?php

namespace App\Controller;

use App\Entity\Alert;
use App\Entity\User;
use App\Repository\AlertRepository;
use App\Security\Voter\OwnershipVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/alerts')]
class AlertController extends AbstractController
{
    #[Route('', name: 'app_alert_index', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, AlertRepository $alerts, #[MapQueryParameter] bool $all = false): Response
    {
        return $this->render('alert/index.html.twig', [
            'alerts' => $alerts->findForOwner($user, openOnly: !$all, limit: 100),
            'all' => $all,
        ]);
    }

    #[Route('/{id}/ack', name: 'app_alert_ack', methods: ['POST'])]
    #[IsGranted(OwnershipVoter::MANAGE, 'alert')]
    public function acknowledge(#[CurrentUser] User $user, Alert $alert, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('ack', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $alert->acknowledge($user);
        $em->flush();

        // Appel AJAX depuis le dashboard : réponse JSON, sinon redirection classique.
        if ($request->getPreferredFormat() === 'json' || $request->isXmlHttpRequest()) {
            return $this->json(['ok' => true, 'id' => $alert->getId()]);
        }

        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_alert_index'));
    }

    #[Route('/ack-all', name: 'app_alert_ack_all', methods: ['POST'], priority: 10)]
    public function acknowledgeAll(#[CurrentUser] User $user, Request $request, AlertRepository $alerts): Response
    {
        if (!$this->isCsrfTokenValid('ack', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $count = $alerts->acknowledgeAllForOwner($user);
        $this->addFlash('success', sprintf('%d alerte(s) acquittée(s).', $count));

        return $this->redirectToRoute('app_alert_index');
    }
}
