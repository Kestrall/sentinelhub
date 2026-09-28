<?php

namespace App\Document;

use App\Repository\DeviceEventRepository;
use Doctrine\ODM\MongoDB\Mapping\Attribute as ODM;

/**
 * Journal des événements de sécurité et système (détection de mouvement, sabotage,
 * redémarrage, mise à jour OTA...). Le champ "payload" dépend du type d'événement.
 */
#[ODM\Document(collection: 'device_events', repositoryClass: DeviceEventRepository::class)]
#[ODM\Index(keys: ['deviceId' => 'asc', 'occurredAt' => 'desc'])]
#[ODM\Index(keys: ['type' => 'asc', 'occurredAt' => 'desc'])]
#[ODM\Index(keys: ['occurredAt' => 'asc'], options: ['expireAfterSeconds' => 15_552_000])]
class DeviceEvent
{
    public const TYPES = [
        'motion' => 'Mouvement détecté',
        'door_open' => 'Ouverture',
        'tamper' => 'Tentative de sabotage',
        'boot' => 'Démarrage',
        'ota_update' => 'Mise à jour OTA',
        'auth_failure' => 'Échec d\'authentification',
        'alert' => 'Alerte déclenchée',
        'armed' => 'Armement',
    ];

    #[ODM\Id]
    private ?string $id = null;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        #[ODM\Field(type: 'int')] private int $deviceId,
        #[ODM\Field(type: 'int')] private int $siteId,
        #[ODM\Field] private string $deviceName,
        #[ODM\Field] private string $type,
        #[ODM\Field] private string $severity,
        #[ODM\Field] private string $message,
        #[ODM\Field(type: 'hash')] private array $payload = [],
        #[ODM\Field(type: 'date_immutable')] private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
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

    public function getDeviceName(): string
    {
        return $this->deviceName;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'deviceId' => $this->deviceId,
            'deviceName' => $this->deviceName,
            'type' => $this->type,
            'typeLabel' => $this->getTypeLabel(),
            'severity' => $this->severity,
            'message' => $this->message,
            'occurredAt' => $this->occurredAt->format(\DATE_ATOM),
        ];
    }
}
