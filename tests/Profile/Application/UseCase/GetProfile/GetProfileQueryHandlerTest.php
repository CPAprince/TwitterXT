<?php

declare(strict_types=1);

namespace Twitter\Tests\Profile\Application\UseCase\GetProfile;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twitter\Profile\Application\UseCase\GetProfile\GetProfileQuery;
use Twitter\Profile\Application\UseCase\GetProfile\GetProfileQueryHandler;
use Twitter\Profile\Domain\Profile\Exception\ProfileNotFoundException;
use Twitter\Profile\Domain\Profile\Model\Profile;
use Twitter\Profile\Domain\Profile\Model\ProfileRepository;

#[Group('integration')]
#[CoversMethod(GetProfileQueryHandler::class, 'handle')]
final class GetProfileQueryHandlerTest extends TestCase
{
    private GetProfileQueryHandler $handler;
    private MockObject|ProfileRepository $profileRepository;

    protected function setUp(): void
    {
        $this->profileRepository = $this->createMock(ProfileRepository::class);
        $this->handler = new GetProfileQueryHandler($this->profileRepository);
    }

    /**
     * @throws ProfileNotFoundException
     */
    #[Test]
    public function returnsProfileQueryResultWhenProfileExists(): void
    {
        $userId = '019b5f3f-d110-7908-9177-5df439942a8b';

        $profile = Profile::create(
            userId: $userId,
            name: 'John Doe',
            bio: 'Some bio',
        );

        $this->profileRepository
            ->expects(self::once())
            ->method('getByUserId')
            ->with($userId)
            ->willReturn($profile);

        $result = $this->handler->handle(new GetProfileQuery($userId));

        self::assertSame('John Doe', $result->name);
        self::assertSame('Some bio', $result->bio);
        self::assertInstanceOf(DateTimeImmutable::class, $result->createdAt);
        self::assertInstanceOf(DateTimeImmutable::class, $result->updatedAt);
    }

    #[Test]
    public function throwsExceptionWhenProfileDoesNotExist(): void
    {
        $userId = 'missing-user';

        $this->profileRepository
            ->expects(self::once())
            ->method('getByUserId')
            ->with($userId)
            ->willThrowException(new ProfileNotFoundException($userId));

        $this->expectException(ProfileNotFoundException::class);
        $this->expectExceptionMessage(
            'Profile not found for user with id "'.$userId.'"'
        );

        $this->handler->handle(new GetProfileQuery($userId));
    }
}
