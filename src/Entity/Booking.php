<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\BookingRepository;
use App\State\BookingUuidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\Table(name: 'bookings')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['booking:read']],
            security: "is_granted('ROLE_USER')"
        ),
        new Post(
            denormalizationContext: ['groups' => ['booking:write']],
            normalizationContext: ['groups' => ['booking:read']],
            security: "is_granted('ROLE_USER')",
            processor: BookingUuidGenerator::class
        ),
        new Get(
            normalizationContext: ['groups' => ['booking:read']],
            security: "is_granted('ROLE_USER')"
        ),
        new Put(
            denormalizationContext: ['groups' => ['booking:write']],
            normalizationContext: ['groups' => ['booking:read']],
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Patch(
            denormalizationContext: ['groups' => ['booking:write']],
            normalizationContext: ['groups' => ['booking:read']],
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Delete(
            security: "is_granted('ROLE_ADMIN')"
        ),
    ],
    normalizationContext: ['groups' => ['booking:read']],
    denormalizationContext: ['groups' => ['booking:write']]
)]
class Booking
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    #[Groups(['booking:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Tour::class)]
    #[ORM\JoinColumn(name: 'tour_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    #[Groups(['booking:read', 'booking:write'])]
    private ?Tour $tour = null;

    #[ORM\Column(name: 'user_email', type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Groups(['booking:read', 'booking:write'])]
    private ?string $userEmail = null;

    #[ORM\Column(name: 'user_name', type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Groups(['booking:read', 'booking:write'])]
    private ?string $userName = null;

    #[ORM\Column(name: 'booking_date', type: Types::DATE_IMMUTABLE)]
    #[Assert\NotBlank]
    #[Groups(['booking:read', 'booking:write'])]
    private ?\DateTimeImmutable $bookingDate = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotBlank]
    #[Assert\Positive(message: 'Le nombre de participants doit être supérieur à 0')]
    #[Groups(['booking:read', 'booking:write'])]
    private ?int $participants = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    #[Groups(['booking:read', 'booking:write'])]
    private ?string $totalPrice = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['default' => 'pending'])]
    #[Assert\Choice(choices: ['pending', 'confirmed', 'cancelled'], message: 'Le statut doit être "pending", "confirmed" ou "cancelled"')]
    #[Groups(['booking:read', 'booking:write'])]
    private ?string $status = 'pending';

    #[ORM\Column(name: 'payment_id', type: Types::TEXT, nullable: true)]
    #[Groups(['booking:read', 'booking:write'])]
    private ?string $paymentId = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    #[Groups(['booking:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    #[Groups(['booking:read'])]
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

    public function getTour(): ?Tour
    {
        return $this->tour;
    }

    public function setTour(?Tour $tour): static
    {
        $this->tour = $tour;

        return $this;
    }

    public function getUserEmail(): ?string
    {
        return $this->userEmail;
    }

    public function setUserEmail(string $userEmail): static
    {
        $this->userEmail = $userEmail;

        return $this;
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function setUserName(string $userName): static
    {
        $this->userName = $userName;

        return $this;
    }

    public function getBookingDate(): ?\DateTimeImmutable
    {
        return $this->bookingDate;
    }

    public function setBookingDate(\DateTimeImmutable $bookingDate): static
    {
        $this->bookingDate = $bookingDate;

        return $this;
    }

    public function getParticipants(): ?int
    {
        return $this->participants;
    }

    public function setParticipants(int $participants): static
    {
        $this->participants = $participants;

        return $this;
    }

    public function getTotalPrice(): ?string
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(string $totalPrice): static
    {
        $this->totalPrice = $totalPrice;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPaymentId(): ?string
    {
        return $this->paymentId;
    }

    public function setPaymentId(?string $paymentId): static
    {
        $this->paymentId = $paymentId;

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
        if ($this->status === null) {
            $this->status = 'pending';
        }
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}

