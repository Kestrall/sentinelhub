<?php

namespace App\Controller;

use App\Document\DeviceEvent;
use App\Entity\User;
use App\Repository\DeviceEventRepository;
use App\Repository\DeviceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class EventController extends AbstractController
{
    #[Route('/events', name: 'app_event_index', methods: ['GET'])]
    public function index(
        #[CurrentUser] User $user,
        DeviceRepository $devices,
        DeviceEventRepository $events,
        #[MapQueryParameter] ?string $type = null,
    ): Response {
        $type = array_key_exists((string) $type, DeviceEvent::TYPES) ? $type : null;
        $ids = $devices->findIdsForOwner($user);

        return $this->render('event/index.html.twig', [
            'events' => $events->findRecent($ids, 100, $type),
            'counts' => $events->countByType($ids, new \DateTimeImmutable('-7 days')),
            'types' => DeviceEvent::TYPES,
            'current' => $type,
        ]);
    }
}
