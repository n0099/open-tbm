<?php /** @noinspection PhpPropertyOnlyWrittenInspection */

namespace App\Entity;

use App\Repository\BilibiliVoteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BilibiliVoteRepository::class)]
#[ORM\Table(name: '"tbm_bilibiliVote"')]
class BilibiliVote
{
    #[ORM\Column, ORM\Id] private int $pid;
    #[ORM\Column] private int $authorUid;
    #[ORM\Column] private ?int $authorExpGrade;
    #[ORM\Column] private bool $isValid;
    #[ORM\Column] private ?string $voteBy;
    #[ORM\Column] private ?string $voteFor;
    #[ORM\Column(type: 'json')] private array $replyContent;
    #[ORM\Column(type: 'datetimetz_immutable')] private \DateTimeImmutable $postTime;

    public function getPid(): int
    {
        return $this->pid;
    }

    public function getAuthorUid(): int
    {
        return $this->authorUid;
    }

    public function getAuthorExpGrade(): ?int
    {
        return $this->authorExpGrade;
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function getVoteBy(): ?string
    {
        return $this->voteBy;
    }

    public function getVoteFor(): ?string
    {
        return $this->voteFor;
    }

    public function getReplyContent(): array
    {
        return $this->replyContent;
    }

    public function getPostTime(): \DateTimeImmutable
    {
        return $this->postTime;
    }
}
