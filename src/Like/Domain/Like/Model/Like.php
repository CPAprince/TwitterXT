<?php

declare(strict_types=1);

namespace Twitter\Like\Domain\Like\Model;

use Assert\Assert;
use DateTimeImmutable;

final class Like
{
    private function __construct(
        private readonly string $tweetId,
        private readonly string $userId,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        string $tweetId,
        string $userId,
    ): self {
        Assert::lazy()->tryAll()
            ->that($tweetId, 'tweetId')->notBlank()->uuid()
            ->that($userId, 'userId')->notBlank()->uuid()
            ->verifyNow();

        return new self(
            $tweetId,
            $userId,
            new DateTimeImmutable(),
        );
    }

    public function tweetId(): string
    {
        return $this->tweetId;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
