<?php

declare(strict_types=1);

namespace Twitter\Realtime\Infrastructure\EventSubscriber;

use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Throwable;
use Twitter\Like\Domain\Like\Event\TweetWasLiked;
use Twitter\Like\Domain\Like\Event\TweetWasUnliked;
use Twitter\Tweet\Domain\Tweet\Exception\TweetNotFoundException;
use Twitter\Tweet\Domain\Tweet\Model\TweetRepository;

final readonly class LikeBroadcastSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private HubInterface $hub,
        private TweetRepository $tweetRepository,
        private string $topicBaseUrl,
        private ?LoggerInterface $logger = null,
    ) {}

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            TweetWasLiked::class => ['onLikeChanged', -10],
            TweetWasUnliked::class => ['onLikeChanged', -10],
        ];
    }

    public function onLikeChanged(TweetWasLiked|TweetWasUnliked $event): void
    {
        try {
            $tweet = $this->tweetRepository->getById($event->tweetId);
        } catch (TweetNotFoundException) {
            return;
        }

        // Normalize base URL by removing default ports to match browser's window.location.origin
        $parsedUrl = parse_url($this->topicBaseUrl);
        $scheme = $parsedUrl['scheme'] ?? 'https';
        $host = $parsedUrl['host'] ?? 'localhost';
        $port = $parsedUrl['port'] ?? null;

        // Strip default ports (443 for HTTPS, 80 for HTTP)
        if (('https' === $scheme && 443 === $port) || ('http' === $scheme && 80 === $port)) {
            $port = null;
        }

        $normalizedBase = $scheme.'://'.$host.(null !== $port ? ':'.$port : '');
        $topic = $normalizedBase.'/tweets/likes';

        $update = new Update(
            topics: [$topic],
            data: json_encode([
                'tweetId' => $event->tweetId,
                'likesCount' => $tweet->likes(),
                'triggeredBy' => $event->userId,
            ], JSON_THROW_ON_ERROR),
        );

        try {
            $this->hub->publish($update);
        } catch (Throwable $e) {
            // Log the error but don't break the like operation
            $this->logger?->warning('Failed to broadcast like update', [
                'tweetId' => $event->tweetId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
