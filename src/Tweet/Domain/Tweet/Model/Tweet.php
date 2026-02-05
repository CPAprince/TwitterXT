<?php

declare(strict_types=1);

namespace Twitter\Tweet\Domain\Tweet\Model;

use Assert\Assert;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final class Tweet
{
    private int $likesCount = 0;

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

    public function updateContent(string $content): void
    {
        Assert::lazy()
            ->that($content, 'content')->notBlank()->maxLength(280)
            ->verifyNow();

        $this->content = $content;
        $this->updatedAt = new DateTimeImmutable();
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
}
