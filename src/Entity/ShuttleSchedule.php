<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\ShuttleScheduleRepository;
use App\State\ShuttleScheduleUuidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ShuttleScheduleRepository::class)]
#[ORM\Table(name: 'shuttle_schedules')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['shuttle_schedule:read']],
            security: "is_granted('ROLE_USER')"
        ),
        new Post(
            denormalizationContext: ['groups' => ['shuttle_schedule:write']],
            normalizationContext: ['groups' => ['shuttle_schedule:read']],
            security: "is_granted('ROLE_ADMIN')",
            processor: ShuttleScheduleUuidGenerator::class
        ),
        new Get(
            normalizationContext: ['groups' => ['shuttle_schedule:read']],
            security: "is_granted('ROLE_USER')"
        ),
        new Put(
            denormalizationContext: ['groups' => ['shuttle_schedule:write']],
            normalizationContext: ['groups' => ['shuttle_schedule:read']],
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Patch(
            denormalizationContext: ['groups' => ['shuttle_schedule:write']],
            normalizationContext: ['groups' => ['shuttle_schedule:read']],
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Delete(
            security: "is_granted('ROLE_ADMIN')"
        ),
    ],
    normalizationContext: ['groups' => ['shuttle_schedule:read']],
    denormalizationContext: ['groups' => ['shuttle_schedule:write']]
)]
class ShuttleSchedule
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    #[Groups(['shuttle_schedule:read'])]
    private ?string $id = null;

    #[ORM\Column(name: 'departure_time', type: Types::STRING, length: 8)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', message: 'Le format de l\'heure de départ doit être HH:MM:SS')]
    #[Groups(['shuttle_schedule:read', 'shuttle_schedule:write'])]
    private ?string $departureTime = null;

    #[ORM\Column(name: 'arrival_time', type: Types::STRING, length: 8)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', message: 'Le format de l\'heure d\'arrivée doit être HH:MM:SS')]
    #[Groups(['shuttle_schedule:read', 'shuttle_schedule:write'])]
    private ?string $arrivalTime = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Groups(['shuttle_schedule:read', 'shuttle_schedule:write'])]
    private ?string $route = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    #[Groups(['shuttle_schedule:read', 'shuttle_schedule:write'])]
    private ?string $price = null;

    #[ORM\Column(name: 'is_active', type: Types::BOOLEAN, nullable: true, options: ['default' => true])]
    #[Groups(['shuttle_schedule:read', 'shuttle_schedule:write'])]
    private ?bool $isActive = true;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Choice(choices: ['airport-to-city', 'city-to-airport'], message: 'La direction doit être soit "airport-to-city" soit "city-to-airport"')]
    #[Groups(['shuttle_schedule:read', 'shuttle_schedule:write'])]
    private ?string $direction = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    #[Groups(['shuttle_schedule:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    #[Groups(['shuttle_schedule:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getDepartureTime(): ?string
    {
        return $this->departureTime;
    }

    public function setDepartureTime(string $departureTime): static
    {
        $this->departureTime = $departureTime;

        return $this;
    }

    public function getArrivalTime(): ?string
    {
        return $this->arrivalTime;
    }

    public function setArrivalTime(string $arrivalTime): static
    {
        $this->arrivalTime = $arrivalTime;

        return $this;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(string $route): static
    {
        $this->route = $route;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(?bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getDirection(): ?string
    {
        return $this->direction;
    }

    public function setDirection(?string $direction): static
    {
        $this->direction = $direction;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}

