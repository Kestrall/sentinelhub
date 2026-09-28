<?php

namespace App\Controller\Api;

use App\Dto\EventInput;
use App\Dto\TelemetryInput;
use App\Entity\Device;
use App\Service\IngestionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API appelée par les ESP32 (firmware SentinelOS).
 * Authentification : en-tête "Authorization: Bearer shd_…" propre à chaque appareil.
 */
#[Route('/api/v1', format: 'json')]
class IngestController extends AbstractController
{
    public function __construct(private readonly IngestionService $ingestion)
    {
    }

    #[Route('/telemetry', name: 'api_ingest_telemetry', methods: ['POST'])]
    public function telemetry(
        #[ValueResolver('device_token')] Device $device,
        #[MapRequestPayload] TelemetryInput $input,
        Request $request,
    ): JsonResponse {
        $result = $this->ingestion->ingestTelemetry($device, $input, $request->getClientIp());

        return $this->json([
            'ok' => true,
            'device' => $device->getId(),
            'armed' => $device->isArmed(),
            ...$result,
        ], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route('/events', name: 'api_ingest_event', methods: ['POST'])]
    public function event(
        #[ValueResolver('device_token')] Device $device,
        #[MapRequestPayload] EventInput $input,
        Request $request,
    ): JsonResponse {
        $event = $this->ingestion->ingestEvent($device, $input, $request->getClientIp());

        return $this->json(['ok' => true, 'event' => $event->toArray()], JsonResponse::HTTP_CREATED);
    }

    /**
     * Le firmware interroge cette route au démarrage pour connaître son état (armé ou non).
     */
    #[Route('/me', name: 'api_device_me', methods: ['GET'])]
    public function me(#[ValueResolver('device_token')] Device $device): JsonResponse
    {
        return $this->json([
            'id' => $device->getId(),
            'name' => $device->getName(),
            'site' => $device->getSite()->getName(),
            'armed' => $device->isArmed(),
            'rules' => array_map(static fn ($r) => $r->describe(), $device->getRules()->toArray()),
        ]);
    }
}
