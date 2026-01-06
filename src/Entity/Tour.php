<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\TourRepository;
use App\State\TourImageProcessor;
use App\State\TourUuidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TourRepository::class)]
#[ORM\Table(name: 'tours')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['tour:read']],
            security: "is_granted('ROLE_USER')"
        ),
        new Post(
            denormalizationContext: ['groups' => ['tour:write']],
            normalizationContext: ['groups' => ['tour:read']],
            security: "is_granted('ROLE_ADMIN')",
            processor: TourImageProcessor::class
        ),
        new Get(
            normalizationContext: ['groups' => ['tour:read']],
            security: "is_granted('ROLE_USER')"
        ),
        new Put(
            denormalizationContext: ['groups' => ['tour:write']],
            normalizationContext: ['groups' => ['tour:read']],
            security: "is_granted('ROLE_ADMIN')",
            processor: TourImageProcessor::class
        ),
        new Patch(
            denormalizationContext: ['groups' => ['tour:write']],
            normalizationContext: ['groups' => ['tour:read']],
            security: "is_granted('ROLE_ADMIN')",
            processor: TourImageProcessor::class
        ),
        new Delete(
            security: "is_granted('ROLE_ADMIN')"
        ),
    ],
    normalizationContext: ['groups' => ['tour:read']],
    denormalizationContext: ['groups' => ['tour:write']]
)]
class Tour
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    #[Groups(['tour:read'])]
    private ?string $id = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Groups(['tour:read', 'tour:write'])]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Groups(['tour:read', 'tour:write'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    #[Groups(['tour:read', 'tour:write'])]
    private ?string $price = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Groups(['tour:read', 'tour:write'])]
    private ?string $duration = null;

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull]
    #[Groups(['tour:read', 'tour:write'])]
    private ?array $highlights = null;

    #[ORM\Column(name: 'image_urls', type: Types::JSON, nullable: true)]
    #[Groups(['tour:read', 'tour:write'])]
    private ?array $imageUrls = null;

    #[ORM\Column(name: 'is_active', type: Types::BOOLEAN, nullable: true, options: ['default' => true])]
    #[Groups(['tour:read', 'tour:write'])]
    private ?bool $isActive = true;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    #[Groups(['tour:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    #[Groups(['tour:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->highlights = null;
        $this->imageUrls = null;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

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

    public function getDuration(): ?string
    {
        return $this->duration;
    }

    public function setDuration(string $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function getHighlights(): array
    {
        // Initialiser la propriété si elle est null
        if ($this->highlights === null) {
            $this->highlights = [];
        }
        return $this->highlights;
    }

    /**
     * @param string[]|null $highlights
     */
    public function setHighlights(?array $highlights): static
    {
        $this->highlights = $highlights ?? [];

        return $this;
    }

    /**
     * @return string[]
     */
    public function getImageUrls(): array
    {
        // Initialiser la propriété si elle est null
        if ($this->imageUrls === null) {
            $this->imageUrls = [];
        }
        return $this->imageUrls;
    }

    /**
     * @param string[]|null $imageUrls
     */
    public function setImageUrls(?array $imageUrls): static
    {
        $this->imageUrls = $imageUrls ?? [];

        return $this;
    }

    /**
     * Ajoute une URL d'image à la liste
     */
    public function addImageUrl(string $imageUrl): static
    {
        // Initialiser la propriété si elle est null
        if ($this->imageUrls === null) {
            $this->imageUrls = [];
        }
        if (!in_array($imageUrl, $this->imageUrls, true)) {
            $this->imageUrls[] = $imageUrl;
        }

        return $this;
    }

    /**
     * Supprime une URL d'image de la liste
     */
    public function removeImageUrl(string $imageUrl): static
    {
        // Initialiser la propriété si elle est null
        if ($this->imageUrls === null) {
            $this->imageUrls = [];
        }
        $key = array_search($imageUrl, $this->imageUrls, true);
        if ($key !== false) {
            unset($this->imageUrls[$key]);
            $this->imageUrls = array_values($this->imageUrls); // Réindexer le tableau
        }

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

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function initializeImageUrls(): void
    {
        // S'assurer que imageUrls est toujours un tableau, jamais null
        if ($this->imageUrls === null) {
            $this->imageUrls = [];
        }
        // S'assurer que highlights est toujours un tableau, jamais null
        if ($this->highlights === null) {
            $this->highlights = [];
        }
    }
}

