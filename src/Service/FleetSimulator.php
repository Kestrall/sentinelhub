<?php

namespace App\Service;

use App\Entity\Device;
use App\Enum\DeviceType;

/**
 * Génère des mesures réalistes (cycle jour/nuit, bruit, dérive) pour faire vivre
 * la démo sans matériel. Déterministe par appareil grâce à un décalage basé sur l'id.
 */
final class FleetSimulator
{
    /**
     * @return array<string, float>
     */
    public function metricsFor(Device $device, \DateTimeImmutable $at): array
    {
        $id = (int) $device->getId();
        $hour = (int) $at->format('G') + (int) $at->format('i') / 60;
        // pic de chaleur vers 16 h
        $daily = sin(($hour - 10) / 24 * 2 * M_PI);
        $name = mb_strtolower($device->getName());

        $baseTemp = match (true) {
            str_contains($name, 'rack') || str_contains($name, 'serveur') => 31.0,
            str_contains($name, 'garage') => 15.0,
            default => 20.5 + ($id % 3),
        };

        $metrics = [
            'temperature' => round($baseTemp + 3.2 * $daily + $this->noise(0.4), 1),
            'rssi' => (float) max(-95, min(-35, -55 - ($id * 7 % 25) + (int) round($this->noise(4)))),
            'heap' => (float) round(180 - ($id * 13 % 60) + $this->noise(6)),
        ];

        if (DeviceType::Sensor === $device->getType()) {
            $metrics['humidity'] = round(max(20, min(90, 52 - 9 * $daily + $this->noise(2))), 1);
        }

        if ('ESP32-C3' === $device->getHardware()) {
            // batterie qui se vide lentement sur la journée
            $metrics['battery'] = round(max(5, 100 - ($at->getTimestamp() / 3600 % 72) * 1.2 + $this->noise(1)), 0);
        }

        return $metrics;
    }

    /**
     * Tire éventuellement un événement de sécurité.
     *
     * @return array{type: string, severity: string, message: string, payload: array<string, mixed>}|null
     */
    public function maybeEvent(Device $device, float $probability): ?array
    {
        if (mt_rand() / mt_getrandmax() > $probability) {
            return null;
        }

        $candidates = match ($device->getType()) {
            DeviceType::Camera => [
                ['motion', 'info', 'Mouvement détecté dans le champ', ['confidence' => mt_rand(60, 99) / 100, 'snapshot' => sprintf('snap_%d.jpg', time())]],
                ['motion', 'warning', 'Personne détectée', ['confidence' => mt_rand(80, 99) / 100, 'class' => 'person']],
            ],
            DeviceType::Sensor => [
                ['door_open', 'info', 'Ouverture détectée', ['contact' => 'reed']],
                ['motion', 'info', 'Présence PIR', ['zone' => mt_rand(1, 4)]],
            ],
            DeviceType::Siren => [['tamper', 'warning', 'Boîtier ouvert', ['switch' => 'lid']]],
            DeviceType::Gateway => [
                ['auth_failure', 'warning', 'Échec d\'authentification MQTT', ['client' => sprintf('192.168.1.%d', mt_rand(20, 250))]],
                ['boot', 'info', 'Redémarrage (watchdog)', ['reason' => 'TASK_WDT', 'uptime' => mt_rand(3600, 900000)]],
            ],
        };

        [$type, $severity, $message, $payload] = $candidates[array_rand($candidates)];

        return ['type' => $type, 'severity' => $severity, 'message' => $message, 'payload' => $payload];
    }

    private function noise(float $amplitude): float
    {
        return (mt_rand() / mt_getrandmax() * 2 - 1) * $amplitude;
    }
}
