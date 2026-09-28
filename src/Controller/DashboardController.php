<?php

namespace App\Controller;

use App\Document\DeviceEvent;
use App\Entity\User;
use App\Repository\AlertRepository;
use App\Repository\DeviceEventRepository;
use App\Repository\DeviceRepository;
use App\Repository\TelemetryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(
        #[CurrentUser] User $user,
        DeviceRepository $devices,
        AlertRepository $alerts,
        DeviceEventRepository $events,
        TelemetryRepository $telemetry,
    ): Response {
        $fleet = $devices->findForOwner($user);
        $ids = array_map(static fn ($d) => $d->getId(), $fleet);

        return $this->render('dashboard/index.html.twig', [
            'devices' => $fleet,
            'online' => count(array_filter($fleet, static fn ($d) => $d->isOnline())),
            'latest' => $telemetry->latestByDevice($ids),
            'openAlerts' => $alerts->findForOwner($user, openOnly: true, limit: 6),
            'alertCounts' => $alerts->countOpenBySeverity($user),
            'events' => $events->findRecent($ids, 12),
            'eventTypes' => $events->countByType($ids, new \DateTimeImmutable('-24 hours')),
            'eventLabels' => DeviceEvent::TYPES,
        ]);
    }
}
