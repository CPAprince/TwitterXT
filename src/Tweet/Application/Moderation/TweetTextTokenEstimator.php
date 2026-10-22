<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\Moderation;

final class TweetTextTokenEstimator
{
    private const CHARS_PER_TOKEN_ESTIMATE = 3.5;

    public function estimate(string $text): int
    {
        $length = mb_strlen(trim($text));

        return (int) ceil(max(1, $length) / self::CHARS_PER_TOKEN_ESTIMATE);
    }

    /**
     * @param string[] $texts
     */
    public function estimateBatch(array $texts): int
    {
        $total = 0;

        foreach ($texts as $text) {
            $total += $this->estimate($text);
        }

        return $total;
    }
}
