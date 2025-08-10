<?php

namespace App\Entity;

use App\Repository\LatestReplierRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LatestReplierRepository::class)]
#[ORM\Table(name: '"tbmc_latestReplier"')]
class LatestReplier extends TimestampedEntity
{
    #[ORM\Column, ORM\Id] public int $id;
    #[ORM\Column] public ?int $uid;
    #[ORM\Column] public ?string $name;
    /** @var ?resource */
    #[ORM\Column] public $displayName;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $value): self
    {
        $this->id = $value;
        return $this;
    }

    public function getUid(): ?int
    {
        return $this->uid;
    }

    public function setUid(?int $value): self
    {
        $this->uid = $value;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $value): self
    {
        $this->name = $value;
        return $this;
    }

    public function getDisplayName(): ?string
    {
        return BlobResourceGetter::resource($this->displayName);
    }

    /** @param ?resource $displayName */
    public function setDisplayName($displayName): self
    {
        $this->displayName = $displayName;
        return $this;
    }
}
