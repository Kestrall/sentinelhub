<?php

namespace App\Controller\Api;

use App\Entity\Device;
use App\Entity\User;
use App\Enum\Metric;
use App\Repository\AlertRepository;
use App\Repository\DeviceEventRepository;
use App\Repository\DeviceRepository;
use App\Repository\TelemetryRepository;
use App\Security\Voter\OwnershipVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Endpoints JSON consommés par le front (rafraîchissement en direct, graphiques).
 * Protégés par la session de l'utilisateur.
 */
#[Route('/api/live', format: 'json')]
class LiveController extends AbstractController
{
    #[Route('/dashboard', name: 'api_live_dashboard', methods: ['GET'])]
    public function dashboard(
        #[CurrentUser] User $user,
        DeviceRepository $devices,
        AlertRepository $alerts,
        DeviceEventRepository $events,
        TelemetryRepository $telemetry,
    ): JsonResponse {
        $fleet = $devices->findForOwner($user);
        $ids = array_map(static fn (Device $d) => $d->getId(), $fleet);
        $latest = $telemetry->latestByDevice($ids);

        return $this->json([
            'devices' => array_map(static fn (Device $d) => [
                'id' => $d->getId(),
                'online' => $d->isOnline(),
                'armed' => $d->isArmed(),
                'lastSeen' => $d->getLastSeenAt()?->format(\DATE_ATOM),
                'metrics' => $latest[$d->getId()]['metrics'] ?? null,
            ], $fleet),
            'alerts' => $alerts->countOpenBySeverity($user),
            'events' => array_map(static fn ($e) => $e->toArray(), $events->findRecent($ids, 12)),
            'histogram' => $events->hourlyHistogram($ids),
            'serverTime' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ]);
    }

    #[Route('/devices/{id}/series', name: 'api_live_series', methods: ['GET'])]
    #[IsGranted(OwnershipVoter::MANAGE, 'device')]
    public function series(
        Device $device,
        TelemetryRepository $telemetry,
        #[MapQueryParameter] string $metric = 'temperature',
        #[MapQueryParameter(options: ['min_range' => 1, 'max_range' => 168])] int $hours = 24,
    ): JsonResponse {
        $m = Metric::tryFrom($metric) ?? Metric::Temperature;

        return $this->json([
            'metric' => $m->value,
            'label' => $m->label(),
            'unit' => $m->unit(),
            'hours' => $hours,
            'points' => $telemetry->series($device->getId(), $m, $hours),
        ]);
    }
}
