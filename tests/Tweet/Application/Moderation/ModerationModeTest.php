<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Application\Moderation;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twitter\Tweet\Application\Moderation\ModerationMode;

#[Group('unit')]
#[CoversClass(ModerationMode::class)]
final class ModerationModeTest extends TestCase
{
    #[Test]
    public function fromStringCreatesLiveMode(): void
    {
        $mode = ModerationMode::fromString('live');

        self::assertSame(ModerationMode::LIVE, $mode);
        self::assertTrue($mode->isLive());
        self::assertFalse($mode->isDemo());
        self::assertFalse($mode->isBypass());
    }

    #[Test]
    public function fromStringCreatesDemoMode(): void
    {
        $mode = ModerationMode::fromString('demo');

        self::assertSame(ModerationMode::DEMO, $mode);
        self::assertFalse($mode->isLive());
        self::assertTrue($mode->isDemo());
        self::assertFalse($mode->isBypass());
    }

    #[Test]
    public function fromStringCreatesBypassMode(): void
    {
        $mode = ModerationMode::fromString('bypass');

        self::assertSame(ModerationMode::BYPASS, $mode);
        self::assertFalse($mode->isLive());
        self::assertFalse($mode->isDemo());
        self::assertTrue($mode->isBypass());
    }

    #[Test]
    #[DataProvider('uppercaseModeProvider')]
    public function fromStringIsCaseInsensitive(string $input, ModerationMode $expected): void
    {
        self::assertSame($expected, ModerationMode::fromString($input));
    }

    public static function uppercaseModeProvider(): array
    {
        return [
            ['LIVE', ModerationMode::LIVE],
            ['DEMO', ModerationMode::DEMO],
            ['BYPASS', ModerationMode::BYPASS],
            ['Live', ModerationMode::LIVE],
        ];
    }

    #[Test]
    public function fromStringThrowsForUnknownMode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unsupported moderation mode/');

        ModerationMode::fromString('unknown');
    }
}
