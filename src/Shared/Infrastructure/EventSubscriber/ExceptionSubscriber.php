<?php

declare(strict_types=1);

namespace Twitter\Shared\Infrastructure\EventSubscriber;

use Assert\AssertionFailedException;
use Assert\InvalidArgumentException;
use Assert\LazyAssertionException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;
use Twitter\IAM\Domain\Auth\Exception\BadRequestException;
use Twitter\IAM\Domain\Auth\Exception\TokenInvalidException;
use Twitter\IAM\Domain\Auth\Exception\UnauthorizedException;
use Twitter\IAM\Domain\Auth\Exception\ValidationErrorException;
use Twitter\IAM\Domain\User\Exception\InvalidEmailException;
use Twitter\IAM\Domain\User\Exception\InvalidPasswordException;
use Twitter\IAM\Domain\User\Exception\UserAlreadyExistsException;
use Twitter\Like\Domain\Like\Exception\LikeAlreadyExistsException;
use Twitter\Profile\Domain\Profile\Exception\ProfileAlreadyExistsException;
use Twitter\Profile\Domain\Profile\Exception\ProfileNotFoundException;
use Twitter\Profile\Domain\Profile\Exception\UserNotFoundException as ProfileUserNotFoundExceptionAlias;
use Twitter\Tweet\Domain\Tweet\Exception\TweetAccessDeniedException;
use Twitter\Tweet\Domain\Tweet\Exception\TweetNotFoundException;
use Twitter\Tweet\Domain\Tweet\Exception\UserNotFoundException as TweetUserNotFoundExceptionAlias;

final readonly class ExceptionSubscriber implements EventSubscriberInterface
{
    private const array EXCEPTION_MAPPING = [
        BadRequestException::class => [
            'code' => BadRequestException::ERROR_CODE,
            'message' => 'Bad request.',
            'status' => Response::HTTP_BAD_REQUEST,
        ],
        UnauthorizedException::class => [
            'code' => UnauthorizedException::ERROR_CODE,
            'message' => 'Unauthorized.',
            'status' => Response::HTTP_UNAUTHORIZED,
        ],
        TokenInvalidException::class => [
            'code' => TokenInvalidException::ERROR_CODE,
            'message' => 'Invalid or expired token.',
            'status' => Response::HTTP_UNAUTHORIZED,
        ],
        ValidationErrorException::class => [
            'code' => ValidationErrorException::ERROR_CODE,
            'message' => 'Validation failed.',
            'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
        ],

        InvalidEmailException::class => [
            'code' => 'INVALID_EMAIL',
            'message' => 'The provided email address is invalid',
            'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
        ],
        InvalidPasswordException::class => [
            'code' => 'INVALID_PASSWORD',
            'message' => 'The provided password does not meet requirements',
            'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
        ],
        UserAlreadyExistsException::class => [
            'code' => 'USER_ALREADY_EXISTS',
            'message' => 'A user with this email already exists',
            'status' => Response::HTTP_CONFLICT,
        ],
        ProfileUserNotFoundExceptionAlias::class => [
            'code' => 'USER_NOT_FOUND',
            'message' => 'The user with this ID was not found',
            'status' => Response::HTTP_NOT_FOUND,
        ],
        TweetAccessDeniedException::class => [
            'code' => 'ACCESS_DENIED',
            'message' => 'You are not allowed to access this tweet',
            'status' => Response::HTTP_FORBIDDEN,
        ],
        TweetUserNotFoundExceptionAlias::class => [
            'code' => 'USER_NOT_FOUND',
            'message' => 'The user with this ID was not found',
            'status' => Response::HTTP_NOT_FOUND,
        ],
        ProfileNotFoundException::class => [
            'code' => 'PROFILE_NOT_FOUND',
            'message' => 'The profile with this user ID was not found',
            'status' => Response::HTTP_NOT_FOUND,
        ],
        ProfileAlreadyExistsException::class => [
            'code' => 'PROFILE_ALREADY_EXISTS',
            'message' => 'A profile with this user already exists',
            'status' => Response::HTTP_CONFLICT,
        ],
        TweetNotFoundException::class => [
            'code' => 'TWEET_NOT_FOUND',
            'message' => 'The tweet with this ID was not found',
            'status' => Response::HTTP_NOT_FOUND,
        ],
        LikeAlreadyExistsException::class => [
            'code' => 'LIKE_ALREADY_EXISTS',
            'message' => 'The tweet has already been liked by this user',
            'status' => Response::HTTP_CONFLICT,
        ],
    ];

    public function __construct(
        private LoggerInterface $logger,
        private string $environment = 'prod',
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Only handle API routes - let Symfony handle web routes normally
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api')) {
            return; // Let Symfony handle web route exceptions normally
        }

        $throwable = $event->getThrowable();

        $this->logException($throwable);

        $response = $this->createErrorResponse($throwable);
        $event->setResponse($response);
    }

    private function logException(Throwable $throwable): void
    {
        $context = [
            'exception' => $throwable::class,
            'message' => $throwable->getMessage(),
        ];

        if ('dev' === $this->environment) {
            $context['trace'] = $throwable->getTraceAsString();
        }

        $this->logger->error('Exception caught', $context);
    }

    private function createErrorResponse(Throwable $throwable): JsonResponse
    {
        $class = $throwable::class;
        $code = 'INTERNAL_SERVER_ERROR';
        $message = 'An unexpected error occurred. Please try again later';
        $status = Response::HTTP_INTERNAL_SERVER_ERROR;

        if ($response = $this->validationErrorResponse($throwable)) {
            return $response;
        }

        if (array_key_exists($class, self::EXCEPTION_MAPPING)) {
            $config = self::EXCEPTION_MAPPING[$class];
            $code = $config['code'];
            $message = $config['message'];
            $status = $config['status'];
        } elseif ($throwable instanceof HttpExceptionInterface) {
            $code = 'HTTP_ERROR';
            $message = 'An error occurred';
            $status = $throwable->getStatusCode();
        }

        return new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }

    private function validationErrorResponse(Throwable $throwable): ?JsonResponse
    {
        if (!$throwable instanceof AssertionFailedException) {
            return null;
        }

        return new JsonResponse([
            'errors' => $this->mapValidationErrors($throwable),
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function mapValidationErrors(Throwable $throwable): array
    {
        if ($throwable instanceof LazyAssertionException) {
            return array_map(
                static fn (InvalidArgumentException $error): array => [
                    'field' => $error->getPropertyPath(),
                    'message' => $error->getMessage(),
                ],
                $throwable->getErrorExceptions()
            );
        }

        if ($throwable instanceof AssertionFailedException) {
            return [
                'field' => $throwable->getPropertyPath(),
                'message' => $throwable->getMessage(),
            ];
        }

        return [];
    }
}
