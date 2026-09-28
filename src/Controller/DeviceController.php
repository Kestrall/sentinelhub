<?php

namespace App\Controller;

use App\Entity\AlertRule;
use App\Entity\Device;
use App\Entity\User;
use App\Enum\Metric;
use App\Enum\Severity;
use App\Form\AlertRuleFormType;
use App\Form\DeviceFormType;
use App\Repository\DeviceEventRepository;
use App\Repository\DeviceRepository;
use App\Repository\SiteRepository;
use App\Repository\TelemetryRepository;
use App\Security\DeviceTokenManager;
use App\Security\Voter\OwnershipVoter;
use App\Service\IngestionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/devices')]
class DeviceController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DeviceTokenManager $tokens,
    ) {
    }

    #[Route('', name: 'app_device_index', methods: ['GET'])]
    public function index(
        #[CurrentUser] User $user,
        DeviceRepository $devices,
        TelemetryRepository $telemetry,
        #[MapQueryParameter] ?string $q = null,
    ): Response {
        $fleet = $devices->findForOwner($user, $q);

        return $this->render('device/index.html.twig', [
            'devices' => $fleet,
            'latest' => $telemetry->latestByDevice(array_map(static fn (Device $d) => $d->getId(), $fleet)),
            'q' => $q,
        ]);
    }

    #[Route('/new', name: 'app_device_new', methods: ['GET', 'POST'])]
    public function new(#[CurrentUser] User $user, Request $request, SiteRepository $sites): Response
    {
        $firstSite = $sites->findOneBy(['owner' => $user], ['name' => 'ASC']);
        if (!$firstSite) {
            $this->addFlash('info', 'Crée d\'abord un site pour y rattacher tes appareils.');

            return $this->redirectToRoute('app_site_new');
        }

        $device = new Device($firstSite);
        $form = $this->createForm(DeviceFormType::class, $device, ['owner' => $user])->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $token = $this->tokens->issue($device);
            $this->em->persist($device);
            $this->em->flush();

            // Le token en clair n'est affiché qu'une seule fois.
            $this->addFlash('token', $token);

            return $this->redirectToRoute('app_device_show', ['id' => $device->getId()]);
        }

        return $this->render('device/form.html.twig', ['form' => $form, 'device' => null]);
    }

    #[Route('/{id}', name: 'app_device_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(OwnershipVoter::MANAGE, 'device')]
    public function show(Device $device, Request $request, DeviceEventRepository $events, TelemetryRepository $telemetry): Response
    {
        $rule = new AlertRule($device);
        $ruleForm = $this->createForm(AlertRuleFormType::class, $rule)->handleRequest($request);

        if ($ruleForm->isSubmitted() && $ruleForm->isValid()) {
            $device->addRule($rule);
            $this->em->flush();
            $this->addFlash('success', 'Règle ajoutée : '.$rule->describe());

            return $this->redirectToRoute('app_device_show', ['id' => $device->getId()]);
        }

        return $this->render('device/show.html.twig', [
            'device' => $device,
            'latest' => $telemetry->latestByDevice([$device->getId()])[$device->getId()] ?? null,
            'events' => $events->findRecent([$device->getId()], 15),
            'metrics' => Metric::cases(),
            'ruleForm' => $ruleForm,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_device_edit', methods: ['GET', 'POST'])]
    #[IsGranted(OwnershipVoter::MANAGE, 'device')]
    public function edit(#[CurrentUser] User $user, Device $device, Request $request): Response
    {
        $form = $this->createForm(DeviceFormType::class, $device, ['owner' => $user])->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Appareil mis à jour.');

            return $this->redirectToRoute('app_device_show', ['id' => $device->getId()]);
        }

        return $this->render('device/form.html.twig', ['form' => $form, 'device' => $device]);
    }

    #[Route('/{id}/arm', name: 'app_device_arm', methods: ['POST'])]
    #[IsGranted(OwnershipVoter::MANAGE, 'device')]
    public function toggleArm(Device $device, Request $request, IngestionService $ingestion): Response
    {
        $this->checkCsrf('arm-'.$device->getId(), $request);

        $device->setArmed(!$device->isArmed());
        $ingestion->recordEvent($device, 'armed', Severity::Info, $device->isArmed() ? 'Surveillance activée' : 'Surveillance désactivée');
        $this->em->flush();
        $ingestion->flushEvents();

        $this->addFlash('success', $device->isArmed() ? '🛡️ Appareil armé.' : 'Appareil désarmé.');

        return $this->redirectToRoute('app_device_show', ['id' => $device->getId()]);
    }

    #[Route('/{id}/token', name: 'app_device_token', methods: ['POST'])]
    #[IsGranted(OwnershipVoter::MANAGE, 'device')]
    public function regenerateToken(Device $device, Request $request): Response
    {
        $this->checkCsrf('token-'.$device->getId(), $request);

        $this->addFlash('token', $this->tokens->issue($device));
        $this->em->flush();

        return $this->redirectToRoute('app_device_show', ['id' => $device->getId()]);
    }

    #[Route('/{id}/rules/{rule}/delete', name: 'app_device_rule_delete', methods: ['POST'])]
    #[IsGranted(OwnershipVoter::MANAGE, 'device')]
    public function deleteRule(Device $device, #[MapEntity(id: 'rule')] AlertRule $rule, Request $request): Response
    {
        $this->checkCsrf('rule-'.$rule->getId(), $request);

        if ($rule->getDevice() === $device) {
            $device->getRules()->removeElement($rule);
            $this->em->flush();
        }

        return $this->redirectToRoute('app_device_show', ['id' => $device->getId()]);
    }

    #[Route('/{id}/delete', name: 'app_device_delete', methods: ['POST'])]
    #[IsGranted(OwnershipVoter::MANAGE, 'device')]
    public function delete(Device $device, Request $request): Response
    {
        $this->checkCsrf('delete-device-'.$device->getId(), $request);

        $this->em->remove($device);
        $this->em->flush();
        $this->addFlash('success', 'Appareil supprimé.');

        return $this->redirectToRoute('app_device_index');
    }

    private function checkCsrf(string $id, Request $request): void
    {
        if (!$this->isCsrfTokenValid($id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
