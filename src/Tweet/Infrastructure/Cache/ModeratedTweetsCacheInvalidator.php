<?php

declare(strict_types=1);

namespace Twitter\Tweet\Infrastructure\Cache;

use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Twitter\Tweet\Application\Moderation\ModeratedTweetsCacheInvalidatorInterface;

final readonly class ModeratedTweetsCacheInvalidator implements ModeratedTweetsCacheInvalidatorInterface
{
    public function __construct(
        private TagAwareCacheInterface $cacheTweets,
    ) {}

    public function invalidateAuthorFeeds(array $authorIds): void
    {
        if ([] === $authorIds) {
            return;
        }

        $uniqueAuthorIds = array_values(array_unique($authorIds));
        $tags = array_map(
            static fn (string $authorId): string => sprintf('user_tweets_%s', $authorId),
            $uniqueAuthorIds
        );

        $this->cacheTweets->invalidateTags($tags);
    }
}
