<?php

declare(strict_types=1);

namespace Twitter\IAM\Domain\User\Model;

use Twitter\IAM\Domain\User\Exception\InvalidEmailException;

final readonly class Email
{
    private function __construct(
        private string $email,
    ) {}

    public function __toString(): string
    {
        return $this->email;
    }

    /**
     * @throws InvalidEmailException
     */
    public static function fromString(string $email): self
    {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException($email);
        }

        return new self($email);
    }
}
