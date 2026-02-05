<?php

declare(strict_types=1);

namespace Twitter\Tweet\Domain\Tweet\Model;

use Twitter\Tweet\Domain\Tweet\Exception\TweetNotFoundException;

interface TweetRepository
{
    public function add(Tweet $tweet): void;

    public function flush(): void;

    /**
     * @throws TweetNotFoundException
     */
    public function getById(string $tweetId): Tweet;

    /**
     * Returns all tweets sorted by createdAt DESC (newest first).
     *
     * @return list<Tweet>
     */
    public function getAllTweets(int $limit, int $page): array;

    /**
     * Returns User tweets sorted by createdAt DESC (newest first).
     *
     * @return list<Tweet>
     */
    public function getUserTweets(string $userId, int $limit, int $page): array;
}
