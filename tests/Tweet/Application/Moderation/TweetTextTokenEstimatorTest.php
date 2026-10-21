<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Application\Moderation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twitter\Tweet\Application\Moderation\TweetTextTokenEstimator;

#[Group('unit')]
#[CoversClass(TweetTextTokenEstimator::class)]
final class TweetTextTokenEstimatorTest extends TestCase
{
    private TweetTextTokenEstimator $estimator;

    protected function setUp(): void
    {
        $this->estimator = new TweetTextTokenEstimator();
    }

    #[Test]
    public function estimateReturnsAtLeastOneForSingleCharacter(): void
    {
        // 1 char / 3.5 = 0.28 → ceil → 1
        self::assertSame(1, $this->estimator->estimate('a'));
    }

    #[Test]
    public function estimateReturnsAtLeastOneForEmptyAfterTrim(): void
    {
        // Empty string after trim → max(1, 0) / 3.5 = 0.28 → ceil → 1
        self::assertSame(1, $this->estimator->estimate('   '));
    }

    #[Test]
    #[DataProvider('textTokenProvider')]
    public function estimateCalculatesCorrectTokenCount(string $text, int $expected): void
    {
        self::assertSame($expected, $this->estimator->estimate($text));
    }

    public static function textTokenProvider(): array
    {
        return [
            // ceil(7 / 3.5) = 2
            '7 chars' => ['1234567', 2],
            // ceil(35 / 3.5) = 10
            '35 chars' => [str_repeat('a', 35), 10],
            // ceil(280 / 3.5) = 80
            'max tweet length' => [str_repeat('x', 280), 80],
        ];
    }

    #[Test]
    public function estimateBatchSumsTokensForAllTexts(): void
    {
        // Arrange – two 7-char strings: 2 tokens each → 4 total
        $texts = ['1234567', '1234567'];

        // Act
        $total = $this->estimator->estimateBatch($texts);

        // Assert
        self::assertSame(4, $total);
    }

    #[Test]
    public function estimateBatchReturnsZeroForEmptyArray(): void
    {
        self::assertSame(0, $this->estimator->estimateBatch([]));
    }
}
