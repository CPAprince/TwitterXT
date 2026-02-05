<?php

declare(strict_types=1);

namespace Twitter\IAM\UI\REST\Request;

use Assert\Assert;
use Assert\AssertionFailedException;
use Twitter\IAM\Domain\Auth\Exception\ValidationErrorException;

final readonly class LogoutRequest
{
    public function __construct(private string $refreshToken)
    {
        try {
            Assert::that(trim($this->refreshToken))->notBlank();
        } catch (AssertionFailedException) {
            throw new ValidationErrorException('refreshToken is required.');
        }
    }

    public function refreshToken(): string
    {
        return $this->refreshToken;
    }
}
