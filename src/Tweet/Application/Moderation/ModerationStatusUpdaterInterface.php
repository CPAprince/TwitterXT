<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\Moderation;

interface ModerationStatusUpdaterInterface
{
    /**
     * @param list<ModerationDecision> $decisions
     */
    public function apply(array $decisions): void;
}
