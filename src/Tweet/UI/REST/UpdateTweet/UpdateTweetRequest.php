<?php

declare(strict_types=1);

namespace Twitter\Tweet\UI\REST\UpdateTweet;

final readonly class UpdateTweetRequest
{
    public function __construct(private string $content) {}

    public function content(): string
    {
        return $this->content;
    }
}
