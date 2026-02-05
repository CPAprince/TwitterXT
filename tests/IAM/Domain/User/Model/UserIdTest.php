<?php

declare(strict_types=1);

namespace Twitter\Tests\IAM\Domain\User\Model;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twitter\IAM\Domain\User\Model\UserId;

#[Group('unit')]
#[CoversClass(UserId::class)]
class UserIdTest extends TestCase
{
    #[Test]
    public function generatesANonEmptyIdentifier(): void
    {
        $userId = UserId::generate();

        self::assertNotEmpty((string) $userId);
    }

    #[Test]
    public function canBeRecreatedFromItsStringRepresentation(): void
    {
        $original = '019b4cf2-b0a7-7609-973c-5aa222c3f487';
        $restored = UserId::fromString($original);

        self::assertSame($original, (string) $restored);
    }

    #[Test]
    public function throwsWhenCreatedFromAnInvalidString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        UserId::fromString('abracadabra');
    }

    #[Test]
    public function twoIdsWithTheSameValueAreEqual(): void
    {
        $value = (string) UserId::generate();

        $a = UserId::fromString($value);
        $b = UserId::fromString($value);

        self::assertEquals((string) $a, (string) $b);
    }

    #[Test]
    public function twoGeneratedIdsAreNotEqual(): void
    {
        $a = UserId::generate();
        $b = UserId::generate();

        self::assertNotEquals((string) $a, (string) $b);
    }
}
