<?php

declare(strict_types=1);

namespace Twitter\Tweet\UI\REST\CreateTweet;

final readonly class CreateTweetRequest
{
    public function __construct(private string $content) {}

    public function content(): string
    {
        return $this->content;
    }
}
