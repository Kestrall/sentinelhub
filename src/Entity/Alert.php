<?php

namespace App\Entity;

use App\Enum\Severity;
use App\Repository\AlertRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlertRepository::class)]
#[ORM\Index(name: 'idx_alert_open', columns: ['acknowledged_at', 'created_at'])]
class Alert
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $acknowledgedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $acknowledgedBy = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Device $device,

        #[ORM\Column(length: 10, enumType: Severity::class)]
        private Severity $severity,

        #[ORM\Column(length: 255)]
        private string $message,

        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?AlertRule $rule = null,

        #[ORM\Column(nullable: true)]
        private ?float $value = null,

        ?\DateTimeImmutable $createdAt = null,
    ) {
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDevice(): Device
    {
        return $this->device;
    }

    public function getSeverity(): Severity
    {
        return $this->severity;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getRule(): ?AlertRule
    {
        return $this->rule;
    }

    public function getValue(): ?float
    {
        return $this->value;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isAcknowledged(): bool
    {
        return null !== $this->acknowledgedAt;
    }

    public function acknowledge(User $by): void
    {
        if ($this->isAcknowledged()) {
            return;
        }
        $this->acknowledgedAt = new \DateTimeImmutable();
        $this->acknowledgedBy = $by;
    }

    public function getAcknowledgedAt(): ?\DateTimeImmutable
    {
        return $this->acknowledgedAt;
    }

    public function getAcknowledgedBy(): ?User
    {
        return $this->acknowledgedBy;
    }
}
