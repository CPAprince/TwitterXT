<?php

declare(strict_types=1);

namespace Twitter\Tests\Profile\Application\UseCase\UpdateProfile;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twitter\IAM\Domain\Auth\Exception\UnauthorizedException;
use Twitter\Profile\Application\UseCase\UpdateProfile\UpdateProfileCommand;
use Twitter\Profile\Application\UseCase\UpdateProfile\UpdateProfileCommandHandler;
use Twitter\Profile\Domain\Profile\Exception\ProfileNotFoundException;
use Twitter\Profile\Domain\Profile\Model\Profile;
use Twitter\Profile\Domain\Profile\Model\ProfileRepository;

#[Group('unit')]
#[CoversMethod(UpdateProfileCommandHandler::class, 'handle')]
final class UpdateProfileCommandHandlerTest extends TestCase
{
    private UpdateProfileCommandHandler $handler;
    private MockObject|ProfileRepository $profileRepository;

    protected function setUp(): void
    {
        $this->profileRepository = $this->createMock(ProfileRepository::class);
        $this->handler = new UpdateProfileCommandHandler($this->profileRepository);
    }

    /**
     * @throws ProfileNotFoundException
     * @throws UnauthorizedException
     */
    #[Test]
    public function updatesProfileNameWhenNameIsProvided(): void
    {
        $userId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $newName = 'Jane Doe';

        $profile = Profile::create($userId, 'John Doe', 'Old bio');

        $this->profileRepository
            ->expects(self::once())
            ->method('getByUserId')
            ->with($userId)
            ->willReturn($profile);

        $this->profileRepository
            ->expects(self::once())
            ->method('flush');

        $command = new UpdateProfileCommand(
            userId: $userId,
            name: $newName,
            bio: null
        );

        $result = $this->handler->handle($command);

        self::assertSame($newName, $result->name);
        self::assertSame('Old bio', $result->bio);
        self::assertSame($newName, $profile->name());
        self::assertInstanceOf(DateTimeImmutable::class, $result->updatedAt);
    }

    /**
     * @throws ProfileNotFoundException
     * @throws UnauthorizedException
     */
    #[Test]
    public function updatesProfileBioWhenBioIsProvided(): void
    {
        $userId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $newBio = 'New bio text';

        $profile = Profile::create($userId, 'John Doe', 'Old bio');

        $this->profileRepository
            ->expects(self::once())
            ->method('getByUserId')
            ->with($userId)
            ->willReturn($profile);

        $this->profileRepository
            ->expects(self::once())
            ->method('flush');

        $command = new UpdateProfileCommand(
            userId: $userId,
            name: null,
            bio: $newBio
        );

        $result = $this->handler->handle($command);

        self::assertSame('John Doe', $result->name);
        self::assertSame($newBio, $result->bio);
        self::assertSame($newBio, $profile->bio());
        self::assertInstanceOf(DateTimeImmutable::class, $result->updatedAt);
    }

    /**
     * @throws ProfileNotFoundException
     * @throws UnauthorizedException
     */
    #[Test]
    public function updatesBothNameAndBioWhenBothAreProvided(): void
    {
        $userId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $newName = 'Jane Doe';
        $newBio = 'Updated bio';

        $profile = Profile::create($userId, 'John Doe', 'Old bio');

        $this->profileRepository
            ->expects(self::once())
            ->method('getByUserId')
            ->with($userId)
            ->willReturn($profile);

        $this->profileRepository
            ->expects(self::once())
            ->method('flush');

        $command = new UpdateProfileCommand(
            userId: $userId,
            name: $newName,
            bio: $newBio
        );

        $result = $this->handler->handle($command);

        self::assertSame($newName, $result->name);
        self::assertSame($newBio, $result->bio);
        self::assertSame($newName, $profile->name());
        self::assertSame($newBio, $profile->bio());
        self::assertInstanceOf(DateTimeImmutable::class, $result->updatedAt);
    }

    /**
     * @throws ProfileNotFoundException
     */
    #[Test]
    public function throwsExceptionWhenUserIdDoesNotMatchProfileUserId(): void
    {
        $userId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $differentUserId = '019b5f41-0e5b-7f65-8b7a-0f9c0b3b3c11';

        $profile = Profile::create($differentUserId, 'John Doe', 'Bio');

        $this->profileRepository
            ->expects(self::once())
            ->method('getByUserId')
            ->with($userId)
            ->willReturn($profile);

        $this->profileRepository
            ->expects(self::never())
            ->method('flush');

        $command = new UpdateProfileCommand(
            userId: $userId,
            name: 'New Name',
            bio: null
        );

        $this->expectException(UnauthorizedException::class);

        $this->handler->handle($command);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function propagatesProfileNotFoundException(): void
    {
        $userId = '019b5f3f-d110-7908-9177-5df439942a8b';

        $this->profileRepository
            ->method('getByUserId')
            ->with($userId)
            ->willThrowException(new ProfileNotFoundException($userId));

        $this->profileRepository
            ->expects(self::never())
            ->method('flush');

        $command = new UpdateProfileCommand(
            userId: $userId,
            name: 'New Name',
            bio: null
        );

        $this->expectException(ProfileNotFoundException::class);

        $this->handler->handle($command);
    }

    /**
     * @throws ProfileNotFoundException
     * @throws UnauthorizedException
     */
    #[Test]
    public function doesNotUpdateFieldsWhenBothAreNull(): void
    {
        $userId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $originalName = 'John Doe';
        $originalBio = 'Original bio';

        $profile = Profile::create($userId, $originalName, $originalBio);

        $this->profileRepository
            ->expects(self::once())
            ->method('getByUserId')
            ->with($userId)
            ->willReturn($profile);

        $this->profileRepository
            ->expects(self::once())
            ->method('flush');

        $command = new UpdateProfileCommand(
            userId: $userId,
            name: null,
            bio: null
        );

        $result = $this->handler->handle($command);

        self::assertSame($originalName, $result->name);
        self::assertSame($originalBio, $result->bio);
        self::assertSame($originalName, $profile->name());
        self::assertSame($originalBio, $profile->bio());
    }
}
