<?php

namespace App\Service;

use App\Entity\Alert;
use App\Entity\Device;
use App\Enum\Metric;

/**
 * Confronte une mesure aux règles d'alerte de l'appareil.
 * Classe pure (sans I/O) : facile à tester unitairement.
 */
final class AlertEvaluator
{
    /**
     * @param array<string, float|int> $metrics
     *
     * @return list<Alert>
     */
    public function evaluate(Device $device, array $metrics, \DateTimeImmutable $now): array
    {
        $alerts = [];

        foreach ($device->getRules() as $rule) {
            $key = $rule->getMetric()->value;
            if (!array_key_exists($key, $metrics)) {
                continue;
            }

            $value = (float) $metrics[$key];
            if (!$rule->evaluate($value, $now)) {
                continue;
            }

            $alerts[] = new Alert(
                device: $device,
                severity: $rule->getSeverity(),
                message: sprintf(
                    '%s : %s = %s %s (seuil %s)',
                    $device->getName(),
                    $rule->getMetric()->label(),
                    $value + 0,
                    $rule->getMetric()->unit(),
                    $rule->getOperator()->symbol().' '.($rule->getThreshold() + 0),
                ),
                rule: $rule,
                value: $value,
                createdAt: $now,
            );
        }

        return $alerts;
    }

    /**
     * Supprime les clés inconnues : on n'accepte que les métriques déclarées dans l'enum.
     *
     * @param array<string, mixed> $metrics
     *
     * @return array<string, float>
     */
    public static function sanitize(array $metrics): array
    {
        $clean = [];
        foreach ($metrics as $key => $value) {
            if (null !== Metric::tryFrom((string) $key) && is_numeric($value)) {
                $clean[$key] = (float) $value;
            }
        }

        return $clean;
    }
}
