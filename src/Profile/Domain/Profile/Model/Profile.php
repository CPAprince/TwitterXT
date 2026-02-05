<?php

declare(strict_types=1);

namespace Twitter\Profile\Domain\Profile\Model;

use Assert\Assert;
use DateTimeImmutable;

final class Profile
{
    private function __construct(
        private readonly string $userId,
        private string $name,
        private string $bio,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    public static function create(string $userId, string $name, ?string $bio = null): self
    {
        Assert::lazy()->tryAll()
            ->that($userId, 'userId')->notBlank()->uuid()
            ->that($name, 'name')->notBlank()->minLength(3)->maxLength(50)
            ->that($bio, 'bio')->nullOr()->maxLength(300)
            ->verifyNow();

        return new self(
            $userId,
            $name,
            $bio ?? '',
            new DateTimeImmutable(),
            new DateTimeImmutable(),
        );
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function bio(): string
    {
        return $this->bio;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updateName(string $name): void
    {
        Assert::lazy()->tryAll()
            ->that($name, 'name')->notBlank()->minLength(3)->maxLength(50)
            ->verifyNow();

        $this->name = $name;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateBio(?string $bio): void
    {
        Assert::lazy()->tryAll()
            ->that($bio, 'bio')->nullOr()->maxLength(300)
            ->verifyNow();

        $this->bio = $bio ?? '';
        $this->updatedAt = new DateTimeImmutable();
    }
}
