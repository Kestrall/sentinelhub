<?php

namespace App\Entity;

use App\Repository\SiteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SiteRepository::class)]
class Site
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank(message: 'Donne un nom à ce site.')]
    #[Assert\Length(max: 80)]
    private string $name = '';

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $location = null;

    #[ORM\Column(length: 40)]
    #[Assert\Timezone]
    private string $timezone = 'Europe/Paris';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    /** @var Collection<int, Device> */
    #[ORM\OneToMany(targetEntity: Device::class, mappedBy: 'site', cascade: ['remove'])]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $devices;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $owner)
    {
        $this->owner = $owner;
        $this->devices = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    /** @return Collection<int, Device> */
    public function getDevices(): Collection
    {
        return $this->devices;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
