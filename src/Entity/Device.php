<?php

namespace App\Entity;

use App\Enum\DeviceType;
use App\Repository\DeviceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DeviceRepository::class)]
#[UniqueEntity(fields: ['macAddress'], message: 'Cette adresse MAC est déjà enregistrée.')]
class Device
{
    /** Au-delà de ce délai sans heartbeat, l'appareil est considéré hors ligne. */
    public const OFFLINE_AFTER_SECONDS = 120;

    public const HARDWARE = ['ESP32-WROOM-32', 'ESP32-S3', 'ESP32-C3', 'ESP32-CAM', 'ESP32-2432S028R'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'devices')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\Column(length: 60)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 60)]
    private string $name = '';

    #[ORM\Column(length: 20, enumType: DeviceType::class)]
    private DeviceType $type = DeviceType::Sensor;

    #[ORM\Column(length: 40)]
    #[Assert\NotBlank]
    private string $hardware = 'ESP32-WROOM-32';

    #[ORM\Column(length: 17, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', message: 'Format attendu : AA:BB:CC:DD:EE:FF')]
    private string $macAddress = '';

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $firmwareVersion = null;

    /** Hash SHA-256 du token d'API : le token en clair n'est jamais stocké. */
    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash = '';

    #[ORM\Column(length: 12)]
    private string $tokenPrefix = '';

    #[ORM\Column]
    private bool $armed = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $lastIp = null;

    /** @var Collection<int, AlertRule> */
    #[ORM\OneToMany(targetEntity: AlertRule::class, mappedBy: 'device', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $rules;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Site $site)
    {
        $this->site = $site;
        $this->rules = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function setSite(Site $site): static
    {
        $this->site = $site;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function getType(): DeviceType
    {
        return $this->type;
    }

    public function setType(DeviceType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getHardware(): string
    {
        return $this->hardware;
    }

    public function setHardware(string $hardware): static
    {
        $this->hardware = $hardware;

        return $this;
    }

    public function getMacAddress(): string
    {
        return $this->macAddress;
    }

    public function setMacAddress(string $macAddress): static
    {
        $this->macAddress = strtoupper(trim(str_replace('-', ':', $macAddress)));

        return $this;
    }

    public function getFirmwareVersion(): ?string
    {
        return $this->firmwareVersion;
    }

    public function setFirmwareVersion(?string $firmwareVersion): static
    {
        $this->firmwareVersion = $firmwareVersion;

        return $this;
    }

    public function getTokenPrefix(): string
    {
        return $this->tokenPrefix;
    }

    public function setTokenHash(string $hash, string $prefix): static
    {
        $this->tokenHash = $hash;
        $this->tokenPrefix = $prefix;

        return $this;
    }

    public function isArmed(): bool
    {
        return $this->armed;
    }

    public function setArmed(bool $armed): static
    {
        $this->armed = $armed;

        return $this;
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function getLastIp(): ?string
    {
        return $this->lastIp;
    }

    public function markSeen(\DateTimeImmutable $at, ?string $ip = null): static
    {
        $this->lastSeenAt = $at;
        $this->lastIp = $ip ?? $this->lastIp;

        return $this;
    }

    public function isOnline(?\DateTimeImmutable $now = null): bool
    {
        if (null === $this->lastSeenAt) {
            return false;
        }

        $now ??= new \DateTimeImmutable();

        return $now->getTimestamp() - $this->lastSeenAt->getTimestamp() <= self::OFFLINE_AFTER_SECONDS;
    }

    /** @return Collection<int, AlertRule> */
    public function getRules(): Collection
    {
        return $this->rules;
    }

    public function addRule(AlertRule $rule): static
    {
        if (!$this->rules->contains($rule)) {
            $this->rules->add($rule);
        }

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
