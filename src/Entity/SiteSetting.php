<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\SiteSettingRepository;
use App\State\SiteSettingStateProvider;
use App\State\SiteSettingUuidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SiteSettingRepository::class)]
#[ORM\Table(name: 'site_settings')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['site_setting:read']],
            security: "is_granted('ROLE_USER')",
            provider: SiteSettingStateProvider::class
        ),
        new Post(
            denormalizationContext: ['groups' => ['site_setting:write']],
            normalizationContext: ['groups' => ['site_setting:read']],
            security: "is_granted('ROLE_ADMIN')",
            processor: SiteSettingUuidGenerator::class
        ),
        new Get(
            normalizationContext: ['groups' => ['site_setting:read']],
            security: "is_granted('ROLE_USER')",
            provider: SiteSettingStateProvider::class
        ),
        new Put(
            denormalizationContext: ['groups' => ['site_setting:write']],
            normalizationContext: ['groups' => ['site_setting:read']],
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Patch(
            denormalizationContext: ['groups' => ['site_setting:write']],
            normalizationContext: ['groups' => ['site_setting:read']],
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Delete(
            security: "is_granted('ROLE_ADMIN')"
        ),
    ],
    normalizationContext: ['groups' => ['site_setting:read']],
    denormalizationContext: ['groups' => ['site_setting:write']]
)]
class SiteSetting
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    #[Groups(['site_setting:read'])]
    private ?string $id = null;

    #[ORM\Column(name: 'setting_key', type: Types::STRING, length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['site_setting:read', 'site_setting:write'])]
    private ?string $key = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['site_setting:read', 'site_setting:write'])]
    private ?string $value = null;

    #[ORM\Column(name: 'value_type', type: Types::STRING, length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['boolean', 'string', 'number', 'json'])]
    #[Groups(['site_setting:read', 'site_setting:write'])]
    private string $valueType = 'string';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['site_setting:read', 'site_setting:write'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\Length(max: 100)]
    #[Groups(['site_setting:read', 'site_setting:write'])]
    private string $category = 'general';

    #[ORM\Column(name: 'is_public', type: Types::BOOLEAN)]
    #[Groups(['site_setting:read', 'site_setting:write'])]
    private bool $isPublic = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    #[Groups(['site_setting:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    #[Groups(['site_setting:read'])]
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

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getValueType(): string
    {
        return $this->valueType;
    }

    public function setValueType(string $valueType): static
    {
        $this->valueType = $valueType;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): static
    {
        $this->isPublic = $isPublic;

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

