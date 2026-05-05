<?php

declare(strict_types=1);

namespace Twitter\Shared\Infrastructure\RateLimiter;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class RateLimited
{
    public function __construct(
        public string $target,
    ) {}
}
