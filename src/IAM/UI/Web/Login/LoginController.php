<?php

declare(strict_types=1);

namespace Twitter\IAM\UI\Web\Login;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[Route('/login', name: 'login', methods: [Request::METHOD_GET, Request::METHOD_POST])]
final readonly class LoginController
{
    public function __construct(private Environment $twig) {}

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function __invoke(): Response
    {
        return new Response($this->twig->render('page/login.html.twig'), Response::HTTP_OK);
    }
}
