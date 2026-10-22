<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\Moderation;

interface ModeratedTweetsCacheInvalidatorInterface
{
    /**
     * Invalidate feed caches for authors of newly approved tweets.
     *
     * @param list<string> $authorIds List of author user UUIDs
     */
    public function invalidateAuthorFeeds(array $authorIds): void;
}
