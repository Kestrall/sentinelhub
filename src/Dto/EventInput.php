<?php

namespace App\Dto;

use App\Document\DeviceEvent;
use App\Enum\Severity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Corps JSON attendu sur POST /api/v1/events.
 *
 * {"type": "motion", "severity": "warning", "message": "PIR salon", "payload": {"zone": 2}}
 */
final class EventInput
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(callback: [self::class, 'types'])]
        public string $type = '',

        public Severity $severity = Severity::Info,

        #[Assert\Length(max: 255)]
        public ?string $message = null,

        #[Assert\Count(max: 30)]
        public array $payload = [],
    ) {
    }

    /** @return list<string> */
    public static function types(): array
    {
        return array_keys(DeviceEvent::TYPES);
    }
}
