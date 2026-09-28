<?php

namespace App\Entity;

use App\Enum\Metric;
use App\Enum\Operator;
use App\Enum\Severity;
use App\Repository\AlertRuleRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AlertRuleRepository::class)]
class AlertRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastTriggeredAt = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'rules')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Device $device,

        #[ORM\Column(length: 20, enumType: Metric::class)]
        private Metric $metric = Metric::Temperature,

        #[ORM\Column(length: 2, enumType: Operator::class)]
        private Operator $operator = Operator::GreaterThan,

        #[ORM\Column]
        private float $threshold = 0.0,

        #[ORM\Column(length: 10, enumType: Severity::class)]
        private Severity $severity = Severity::Warning,

        /** Délai minimal entre deux alertes de la même règle, pour éviter le spam. */
        #[ORM\Column]
        #[Assert\Range(min: 0, max: 1440)]
        private int $cooldownMinutes = 15,

        #[ORM\Column]
        private bool $enabled = true,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDevice(): Device
    {
        return $this->device;
    }

    public function getMetric(): Metric
    {
        return $this->metric;
    }

    public function setMetric(Metric $metric): static
    {
        $this->metric = $metric;

        return $this;
    }

    public function getOperator(): Operator
    {
        return $this->operator;
    }

    public function setOperator(Operator $operator): static
    {
        $this->operator = $operator;

        return $this;
    }

    public function getThreshold(): float
    {
        return $this->threshold;
    }

    public function setThreshold(float $threshold): static
    {
        $this->threshold = $threshold;

        return $this;
    }

    public function getSeverity(): Severity
    {
        return $this->severity;
    }

    public function setSeverity(Severity $severity): static
    {
        $this->severity = $severity;

        return $this;
    }

    public function getCooldownMinutes(): int
    {
        return $this->cooldownMinutes;
    }

    public function setCooldownMinutes(int $cooldownMinutes): static
    {
        $this->cooldownMinutes = $cooldownMinutes;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getLastTriggeredAt(): ?\DateTimeImmutable
    {
        return $this->lastTriggeredAt;
    }

    public function isCoolingDown(\DateTimeImmutable $now): bool
    {
        return null !== $this->lastTriggeredAt
            && $now->getTimestamp() - $this->lastTriggeredAt->getTimestamp() < $this->cooldownMinutes * 60;
    }

    /**
     * Retourne true si la valeur déclenche la règle (et mémorise l'instant du déclenchement).
     */
    public function evaluate(float $value, \DateTimeImmutable $now): bool
    {
        if (!$this->enabled || $this->isCoolingDown($now) || !$this->operator->matches($value, $this->threshold)) {
            return false;
        }

        $this->lastTriggeredAt = $now;

        return true;
    }

    public function describe(): string
    {
        return sprintf('%s %s %s %s', $this->metric->label(), $this->operator->symbol(), $this->threshold + 0, $this->metric->unit());
    }
}
