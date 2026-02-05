<?php

declare(strict_types=1);

namespace Twitter\Like\Domain\Like\Model;

use Twitter\Like\Domain\Like\Exception\LikeAlreadyExistsException;

interface LikeRepository
{
    /**
     * @throws LikeAlreadyExistsException
     */
    public function add(Like $like): void;

    public function remove(Like $like): void;

    public function findOneByTweetAndUser(string $tweetId, string $userId): ?Like;
}
