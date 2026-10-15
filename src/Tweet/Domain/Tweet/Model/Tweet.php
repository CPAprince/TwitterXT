<?php

declare(strict_types=1);

namespace Twitter\Tweet\Domain\Tweet\Model;

use Assert\Assert;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final class Tweet
{
    public const int MODERATION_PENDING = 0;
    public const int MODERATION_APPROVED = 1;
    public const int MODERATION_REJECTED = 2;
    private int $moderationVersion = 1;

    private int $likesCount = 0;

    private int $moderationStatus = self::MODERATION_PENDING;

    private ?DateTimeImmutable $moderatedAt = null;

    private function __construct(
        private readonly string $id,
        private readonly string $userId,
        private string $content,
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
        private DateTimeImmutable $updatedAt = new DateTimeImmutable(),
    ) {}

    public static function create(string $userId, string $content): self
    {
        Assert::lazy()->tryAll()
            ->that($userId, 'userId')->notBlank()->uuid()
            ->that($content, 'content')->notBlank()->maxLength(280)
            ->verifyNow();

        return new self(
            Uuid::v7()->toRfc4122(),
            $userId,
            $content,
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function updateContent(string $content): bool
    {
        Assert::lazy()
            ->that($content, 'content')->notBlank()->maxLength(280)
            ->verifyNow();

        if ($this->content === $content) {
            return false;
        }

        $this->content = $content;
        $this->updatedAt = new DateTimeImmutable();
        ++$this->moderationVersion;
        $this->resetModeration();

        return true;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function likes(): int
    {
        return $this->likesCount;
    }

    public function like(): void
    {
        ++$this->likesCount;
    }

    public function dislike(): void
    {
        if ($this->likesCount > 0) {
            --$this->likesCount;
        }
    }

    public function moderationStatus(): int
    {
        return $this->moderationStatus;
    }

    public function moderatedAt(): ?DateTimeImmutable
    {
        return $this->moderatedAt;
    }

    public function resetModeration(): void
    {
        $this->moderationStatus = self::MODERATION_PENDING;
        $this->moderatedAt = null;
    }

    public function approveModeration(): void
    {
        $this->moderationStatus = self::MODERATION_APPROVED;
        $this->moderatedAt = new DateTimeImmutable();
    }

    public function rejectModeration(): void
    {
        $this->moderationStatus = self::MODERATION_REJECTED;
        $this->moderatedAt = new DateTimeImmutable();
    }

    public function moderationVersion(): int
    {
        return $this->moderationVersion;
    }
}
