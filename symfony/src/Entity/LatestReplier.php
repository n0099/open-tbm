<?php /** @noinspection PhpPropertyOnlyWrittenInspection */

namespace App\Entity;

use App\Repository\LatestReplierRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LatestReplierRepository::class)]
#[ORM\Table(name: '"tbmc_latestReplier"')]
class LatestReplier extends TimestampedEntity
{
    #[ORM\Column, ORM\Id] private int $id;
    #[ORM\Column] private ?int $uid;
    #[ORM\Column] private ?string $name;
    /** @type resource|null */
    #[ORM\Column] private $displayName;

    public function getId(): int
    {
        return $this->id;
    }

    public function getUid(): ?int
    {
        return $this->uid;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getDisplayName(): ?string
    {
        return BlobResourceGetter::resource($this->displayName);
    }
}
