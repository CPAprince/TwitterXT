<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Infrastructure\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Twitter\Tweet\Infrastructure\Cache\ModeratedTweetsCacheInvalidator;

#[Group('unit')]
#[CoversClass(ModeratedTweetsCacheInvalidator::class)]
final class ModeratedTweetsCacheInvalidatorTest extends TestCase
{
    private TagAwareCacheInterface&MockObject $cache;
    private ModeratedTweetsCacheInvalidator $invalidator;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(TagAwareCacheInterface::class);
        $this->invalidator = new ModeratedTweetsCacheInvalidator($this->cache);
    }

    #[Test]
    public function doesNothingWhenAuthorIdsListIsEmpty(): void
    {
        $this->cache
            ->expects(self::never())
            ->method('invalidateTags');

        $this->invalidator->invalidateAuthorFeeds([]);
    }

    #[Test]
    public function invalidatesAuthorFeedTagsAndDeduplicates(): void
    {
        $author1 = '019b5f3f-d110-7908-9177-5df439942a8b';
        $author2 = '019b5f41-0e5b-7f65-8b7a-0f9c0b3b3c11';

        $this->cache
            ->expects(self::once())
            ->method('invalidateTags')
            ->with([
                'user_tweets_'.$author1,
                'user_tweets_'.$author2,
            ]);

        $this->invalidator->invalidateAuthorFeeds([$author1, $author2, $author1]);
    }
}
