<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\UI\REST\GetTweetModerationStatus;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Twitter\IAM\Domain\User\Model\Email;
use Twitter\IAM\Domain\User\Model\PasswordHash;
use Twitter\IAM\Domain\User\Model\User;
use Twitter\Tweet\Application\UseCase\GetTweetModerationStatus\GetTweetModerationStatusCommand;
use Twitter\Tweet\Application\UseCase\GetTweetModerationStatus\GetTweetModerationStatusCommandHandlerInterface;
use Twitter\Tweet\Application\UseCase\GetTweetModerationStatus\GetTweetModerationStatusResponse;
use Twitter\Tweet\UI\REST\GetTweetModerationStatus\GetTweetModerationStatusController;

#[Group('unit')]
#[CoversClass(GetTweetModerationStatusController::class)]
final class GetTweetModerationStatusControllerTest extends TestCase
{
    private GetTweetModerationStatusCommandHandlerInterface&MockObject $handler;
    private GetTweetModerationStatusController $controller;

    protected function setUp(): void
    {
        $this->handler = $this->createMock(GetTweetModerationStatusCommandHandlerInterface::class);
        $this->controller = new GetTweetModerationStatusController($this->handler);
    }

    #[Test]
    public function invokeReturnsModerationStatusJsonResponse(): void
    {
        $user = User::create(Email::fromString('author@example.com'), PasswordHash::fromPlainPassword('Valid123#'));
        $tweetId = '019b5f3f-d110-7908-9177-5df439942a8b';

        $this->handler
            ->expects(self::once())
            ->method('handle')
            ->with(self::callback(
                fn (GetTweetModerationStatusCommand $command): bool => $command->tweetId === $tweetId
                    && $command->userId === $user->id()
            ))
            ->willReturn(new GetTweetModerationStatusResponse($tweetId, 'pending'));

        $response = ($this->controller)($tweetId, $user);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertJsonStringEqualsJsonString(
            json_encode([
                'tweetId' => $tweetId,
                'status' => 'pending',
            ], JSON_THROW_ON_ERROR),
            (string) $response->getContent()
        );
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-cache'));
        self::assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
    }
}
