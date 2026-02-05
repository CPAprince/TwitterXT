<?php

declare(strict_types=1);

namespace Twitter\Tests\Profile\Application\UseCase\CreateProfile;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twitter\Profile\Application\UseCase\CreateProfile\CreateProfileCommand;
use Twitter\Profile\Application\UseCase\CreateProfile\CreateProfileCommandHandler;
use Twitter\Profile\Domain\Profile\Exception\ProfileAlreadyExistsException;
use Twitter\Profile\Domain\Profile\Exception\UserNotFoundException;
use Twitter\Profile\Domain\Profile\Model\Profile;
use Twitter\Profile\Domain\Profile\Model\ProfileRepository;

#[Group('unit')]
#[CoversMethod(CreateProfileCommandHandler::class, 'handle')]
final class CreateProfileCommandHandlerTest extends TestCase
{
    private CreateProfileCommandHandler $handler;
    private MockObject|ProfileRepository $profileRepository;

    protected function setUp(): void
    {
        $this->profileRepository = $this->createMock(ProfileRepository::class);
        $this->handler = new CreateProfileCommandHandler($this->profileRepository);
    }

    #[Test]
    public function handleCreatesProfileWithProvidedData(): void
    {
        $userId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $name = 'John Doe';
        $bio = 'Software Developer';

        $command = new CreateProfileCommand(
            userId: $userId,
            name: $name,
            bio: $bio
        );

        $this->profileRepository
            ->expects(self::once())
            ->method('add')
            ->with(self::callback(
                fn (Profile $profile): bool => $userId === $profile->userId()
                    && $name === $profile->name()
                    && $bio === $profile->bio()
            ));

        $this->handler->handle($command);
    }

    #[Test]
    public function handleCreatesProfileWithEmptyBioWhenNullProvided(): void
    {
        $userId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $name = 'John Doe';

        $command = new CreateProfileCommand(
            userId: $userId,
            name: $name,
            bio: null
        );

        $this->profileRepository
            ->expects(self::once())
            ->method('add')
            ->with(self::callback(
                fn (Profile $profile): bool => $userId === $profile->userId()
                    && $name === $profile->name()
                    && '' === $profile->bio()
            ));

        $this->handler->handle($command);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function handlePropagatesProfileAlreadyExistsException(): void
    {
        $userId = '019b5f3f-d110-7908-9177-5df439942a8b';

        $command = new CreateProfileCommand(
            userId: $userId,
            name: 'John Doe',
            bio: null
        );

        $this->profileRepository
            ->method('add')
            ->willThrowException(new ProfileAlreadyExistsException($userId));

        $this->expectException(ProfileAlreadyExistsException::class);

        $this->handler->handle($command);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function handlePropagatesUserNotFoundException(): void
    {
        $userId = '019b5f3f-d110-7908-9177-5df439942a8b';

        $command = new CreateProfileCommand(
            userId: $userId,
            name: 'John Doe',
            bio: null
        );

        $this->profileRepository
            ->method('add')
            ->willThrowException(new UserNotFoundException($userId));

        $this->expectException(UserNotFoundException::class);

        $this->handler->handle($command);
    }
}
