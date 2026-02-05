<?php

declare(strict_types=1);

namespace Twitter\IAM\UI\Web\Registration;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[Route('/registration', name: 'registration', methods: [Request::METHOD_GET, Request::METHOD_POST])]
final readonly class RegistrationController
{
    public function __construct(private Environment $twig) {}

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function __invoke(): Response
    {
        return new Response($this->twig->render('page/registration.html.twig'), Response::HTTP_OK);
    }
}
