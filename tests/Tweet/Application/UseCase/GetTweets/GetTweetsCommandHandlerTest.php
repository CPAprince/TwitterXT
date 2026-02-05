<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Application\UseCase\GetTweets;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twitter\Profile\Domain\Profile\Model\Profile;
use Twitter\Profile\Domain\Profile\Model\ProfileRepository;
use Twitter\Tweet\Application\UseCase\GetTweets\GetTweetsCommand;
use Twitter\Tweet\Application\UseCase\GetTweets\GetTweetsCommandHandler;
use Twitter\Tweet\Application\UseCase\Shared\TweetResponse;
use Twitter\Tweet\Domain\Tweet\Model\Tweet;
use Twitter\Tweet\Domain\Tweet\Model\TweetRepository;

#[Group('unit')]
#[CoversClass(GetTweetsCommandHandler::class)]
final class GetTweetsCommandHandlerTest extends TestCase
{
    private TweetRepository&MockObject $tweetRepository;
    private ProfileRepository&MockObject $profileRepository;
    private GetTweetsCommandHandler $handler;

    protected function setUp(): void
    {
        $this->tweetRepository = $this->createMock(TweetRepository::class);
        $this->profileRepository = $this->createMock(ProfileRepository::class);

        $this->handler = new GetTweetsCommandHandler(
            $this->tweetRepository,
            $this->profileRepository,
        );
    }

    #[Test]
    public function itReturnsEmptyListWhenNoTweetsExist(): void
    {
        $this->tweetRepository
            ->expects(self::once())
            ->method('getAllTweets')
            ->willReturn([]);

        $this->profileRepository
            ->expects(self::never())
            ->method('getByUserId');

        $result = $this->handler->handle(new GetTweetsCommand());

        self::assertSame([], $result->tweets);
    }

    #[Test]
    public function itReturnsTweetsWithAuthorDataInSameOrder(): void
    {
        $authorIdOne = '019b5f3f-d110-7908-9177-5df439942a8b';
        $authorIdTwo = '019b5f41-0e5b-7f65-8b7a-0f9c0b3b3c11';

        $tweetNewest = Tweet::create($authorIdOne, 'Newest tweet');
        $tweetOldest = Tweet::create($authorIdTwo, 'Oldest tweet');

        $profileOne = Profile::create($authorIdOne, 'User One', 'Bio');
        $profileTwo = Profile::create($authorIdTwo, 'User Two', 'Bio');

        $this->tweetRepository
            ->expects(self::once())
            ->method('getAllTweets')
            ->willReturn([$tweetNewest, $tweetOldest]);

        $this->profileRepository
            ->expects(self::once())
            ->method('findAllByUserIds')
            ->with($this->callback(function (array $userIds) use ($authorIdOne, $authorIdTwo) {
                return 2 === count($userIds)
                    && in_array($authorIdOne, $userIds, true)
                    && in_array($authorIdTwo, $userIds, true);
            }))
            ->willReturn([$profileOne, $profileTwo]);

        $result = $this->handler->handle(new GetTweetsCommand());

        self::assertCount(2, $result->tweets);

        self::assertInstanceOf(TweetResponse::class, $result->tweets[0]);
        self::assertSame($tweetNewest->id(), $result->tweets[0]->id);
        self::assertSame('Newest tweet', $result->tweets[0]->content);
        self::assertSame($authorIdOne, $result->tweets[0]->authorId);
        self::assertSame('User One', $result->tweets[0]->authorName);
        self::assertSame($tweetNewest->createdAt()->format(DATE_RFC3339), $result->tweets[0]->createdAt->format(DATE_RFC3339));
        self::assertSame($tweetNewest->updatedAt()->format(DATE_RFC3339), $result->tweets[0]->updatedAt->format(DATE_RFC3339));

        self::assertInstanceOf(TweetResponse::class, $result->tweets[1]);
        self::assertSame($tweetOldest->id(), $result->tweets[1]->id);
        self::assertSame('Oldest tweet', $result->tweets[1]->content);
        self::assertSame($authorIdTwo, $result->tweets[1]->authorId);
        self::assertSame('User Two', $result->tweets[1]->authorName);
        self::assertSame($tweetOldest->createdAt()->format(DATE_RFC3339), $result->tweets[1]->createdAt->format(DATE_RFC3339));
        self::assertSame($tweetOldest->updatedAt()->format(DATE_RFC3339), $result->tweets[1]->updatedAt->format(DATE_RFC3339));
    }

    #[Test]
    public function itFetchesAuthorNameOnlyOncePerAuthorId(): void
    {
        $authorId = '019b5f3f-d110-7908-9177-5df439942a8b';

        $tweetFirst = Tweet::create($authorId, 'First tweet');
        $tweetSecond = Tweet::create($authorId, 'Second tweet');

        $profile = Profile::create($authorId, 'Same Author', 'Bio');

        $this->tweetRepository
            ->expects(self::once())
            ->method('getAllTweets')
            ->willReturn([$tweetSecond, $tweetFirst]);

        $this->profileRepository
            ->expects(self::once())
            ->method('findAllByUserIds')
            ->with([$authorId])
            ->willReturn([$profile]);

        $result = $this->handler->handle(new GetTweetsCommand());

        self::assertCount(2, $result->tweets);
        self::assertSame('Same Author', $result->tweets[0]->authorName);
        self::assertSame('Same Author', $result->tweets[1]->authorName);
    }
}
