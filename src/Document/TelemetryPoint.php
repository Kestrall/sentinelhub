<?php

namespace App\Document;

use App\Repository\TelemetryRepository;
use Doctrine\ODM\MongoDB\Mapping\Attribute as ODM;

/**
 * Mesure envoyée par un appareil. Gros volume, écriture continue, schéma variable
 * selon le type d'appareil : un cas d'usage typique pour MongoDB.
 * Les points sont purgés automatiquement au bout de 30 jours (index TTL).
 */
#[ODM\Document(collection: 'telemetry', repositoryClass: TelemetryRepository::class)]
#[ODM\Index(keys: ['deviceId' => 'asc', 'recordedAt' => 'desc'])]
#[ODM\Index(keys: ['recordedAt' => 'asc'], options: ['expireAfterSeconds' => 2_592_000])]
class TelemetryPoint
{
    #[ODM\Id]
    private ?string $id = null;

    /**
     * @param array<string, float|int> $metrics
     */
    public function __construct(
        #[ODM\Field(type: 'int')] private int $deviceId,
        #[ODM\Field(type: 'int')] private int $siteId,
        #[ODM\Field(type: 'hash')] private array $metrics,
        #[ODM\Field(type: 'date_immutable')] private \DateTimeImmutable $recordedAt,
    ) {
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getDeviceId(): int
    {
        return $this->deviceId;
    }

    public function getSiteId(): int
    {
        return $this->siteId;
    }

    /** @return array<string, float|int> */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function getRecordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }
}
