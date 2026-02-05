<?php

declare(strict_types=1);

namespace Twitter\IAM\Domain\User\Model;

use DateTimeImmutable;
use Deprecated;
use Override;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    private function __construct(
        private array $roles,
        private readonly string $id,
        private readonly string $email,
        private string $passwordHash,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    public static function create(Email $email, PasswordHash $passwordHash): self
    {
        return new self(
            ['ROLE_USER'],
            (string) UserId::generate(),
            (string) $email,
            (string) $passwordHash,
            new DateTimeImmutable(),
            new DateTimeImmutable(),
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function roles(): array
    {
        return $this->roles;
    }

    #[Override]
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function email(): string
    {
        return $this->email;
    }

    #[Override]
    public function getUserIdentifier(): string
    {
        return $this->email();
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    #[Override]
    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * This method is not used anymore but is required by the interface.
     */
    #[Deprecated]
    public function eraseCredentials(): void {}
}
