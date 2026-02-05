<?php

declare(strict_types=1);

namespace Twitter\IAM\Domain\User\Model;

use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class UserId
{
    private function __construct(
        private string $id,
    ) {}

    public function __toString(): string
    {
        return $this->id;
    }

    public static function generate(): self
    {
        return new self(Uuid::v7()->toRfc4122());
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function fromString(string $id): self
    {
        if (!Uuid::isValid($id)) {
            throw new InvalidArgumentException('Invalid user ID');
        }

        return new self($id);
    }
}
