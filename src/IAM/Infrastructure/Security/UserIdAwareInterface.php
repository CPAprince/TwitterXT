<?php

declare(strict_types=1);

namespace Twitter\IAM\Infrastructure\Security;

use Symfony\Component\Security\Core\User\UserInterface;

interface UserIdAwareInterface extends UserInterface
{
    public function getId(): string;
}
