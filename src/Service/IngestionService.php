<?php

namespace App\Service;

use App\Document\DeviceEvent;
use App\Document\TelemetryPoint;
use App\Dto\EventInput;
use App\Dto\TelemetryInput;
use App\Entity\Alert;
use App\Entity\Device;
use App\Enum\Severity;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Point d'entrée unique des données envoyées par les appareils.
 *
 * Les deux bases sont utilisées selon leurs forces :
 *  - MySQL : état courant de l'appareil (last seen, firmware) et alertes à acquitter,
 *            données relationnelles et transactionnelles ;
 *  - MongoDB : flux de mesures et d'événements, append-only, gros volume.
 */
final class IngestionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DocumentManager $dm,
        private readonly AlertEvaluator $evaluator,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return array{stored: int, alerts: int}
     */
    public function ingestTelemetry(Device $device, TelemetryInput $input, ?string $ip = null): array
    {
        $now = $this->clock->now();
        $recordedAt = $input->timestamp ? (new \DateTimeImmutable())->setTimestamp($input->timestamp) : $now;
        // un horodatage dans le futur trahit une horloge non synchronisée
        if ($recordedAt > $now->modify('+5 minutes')) {
            $recordedAt = $now;
        }

        $metrics = AlertEvaluator::sanitize($input->metrics);

        $device->markSeen($now, $ip);
        if ($input->firmware && $input->firmware !== $device->getFirmwareVersion()) {
            $this->recordEvent($device, 'ota_update', Severity::Info, sprintf(
                'Firmware %s → %s', $device->getFirmwareVersion() ?? '?', $input->firmware,
            ), ['from' => $device->getFirmwareVersion(), 'to' => $input->firmware], $now);
            $device->setFirmwareVersion($input->firmware);
        }

        if ($metrics) {
            $this->dm->persist(new TelemetryPoint($device->getId(), $device->getSite()->getId(), $metrics, $recordedAt));
        }

        $alerts = $this->evaluator->evaluate($device, $metrics, $now);
        foreach ($alerts as $alert) {
            $this->em->persist($alert);
            $this->recordEvent($device, 'alert', $alert->getSeverity(), $alert->getMessage(), ['value' => $alert->getValue()], $now);
        }

        $this->em->flush();
        $this->dm->flush();

        return ['stored' => $metrics ? 1 : 0, 'alerts' => count($alerts)];
    }

    public function ingestEvent(Device $device, EventInput $input, ?string $ip = null): DeviceEvent
    {
        $now = $this->clock->now();
        $device->markSeen($now, $ip);

        // Un mouvement détecté alors que le système est armé devient une intrusion.
        $severity = $input->severity;
        if ($device->isArmed() && in_array($input->type, ['motion', 'door_open', 'tamper'], true)) {
            $severity = Severity::Critical;
            $this->em->persist(new Alert(
                device: $device,
                severity: Severity::Critical,
                message: sprintf('Intrusion possible : %s (%s)', DeviceEvent::TYPES[$input->type], $device->getName()),
                createdAt: $now,
            ));
        }

        $event = $this->recordEvent(
            $device,
            $input->type,
            $severity,
            $input->message ?: DeviceEvent::TYPES[$input->type],
            $input->payload,
            $now,
        );

        $this->em->flush();
        $this->dm->flush();

        return $event;
    }

    public function flushEvents(): void
    {
        $this->dm->flush();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function recordEvent(Device $device, string $type, Severity $severity, string $message, array $payload = [], ?\DateTimeImmutable $at = null): DeviceEvent
    {
        $event = new DeviceEvent(
            deviceId: $device->getId(),
            siteId: $device->getSite()->getId(),
            deviceName: $device->getName(),
            type: $type,
            severity: $severity->value,
            message: $message,
            payload: $payload,
            occurredAt: $at ?? $this->clock->now(),
        );
        $this->dm->persist($event);

        return $event;
    }
}
