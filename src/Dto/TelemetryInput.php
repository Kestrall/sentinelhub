<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Corps JSON attendu sur POST /api/v1/telemetry.
 *
 * {"metrics": {"temperature": 21.4, "humidity": 48, "rssi": -61}, "firmware": "1.4.2"}
 */
final class TelemetryInput
{
    /**
     * @param array<string, float|int> $metrics
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Au moins une métrique est requise.')]
        #[Assert\Count(max: 20)]
        #[Assert\All([new Assert\Type('numeric')])]
        public array $metrics = [],

        #[Assert\Length(max: 20)]
        #[Assert\Regex('/^[0-9A-Za-z.\-+]+$/')]
        public ?string $firmware = null,

        /** Horodatage côté appareil (NTP). Absent = heure de réception. */
        #[Assert\Range(min: 1_600_000_000)]
        public ?int $timestamp = null,
    ) {
    }
}
