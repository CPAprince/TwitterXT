<?php

declare(strict_types=1);

namespace Twitter\Like\Application\UseCase\ToggleLike;

final readonly class ToggleLikeCommandResult
{
    public function __construct(
        public bool $liked,
    ) {}
}
