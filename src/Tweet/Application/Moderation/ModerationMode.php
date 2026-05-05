<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\Moderation;

use InvalidArgumentException;

enum ModerationMode: string
{
    case LIVE = 'live';
    case DEMO = 'demo';
    case BYPASS = 'bypass';

    public static function fromString(string $value): self
    {
        return match (mb_strtolower($value)) {
            'live' => self::LIVE,
            'demo' => self::DEMO,
            'bypass' => self::BYPASS,
            default => throw new InvalidArgumentException(sprintf('Unsupported moderation mode "%s". Allowed values: live, demo, bypass.', $value)),
        };
    }

    public function isLive(): bool
    {
        return self::LIVE === $this;
    }

    public function isDemo(): bool
    {
        return self::DEMO === $this;
    }

    public function isBypass(): bool
    {
        return self::BYPASS === $this;
    }
}
