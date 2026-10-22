<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\Moderation;

interface ModerationFlushProcessorInterface
{
    public function processOnce(): ModerationFlushReport;
}
