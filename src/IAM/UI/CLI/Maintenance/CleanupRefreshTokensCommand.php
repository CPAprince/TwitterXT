<?php

declare(strict_types=1);

namespace Twitter\IAM\UI\CLI\Maintenance;

use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Twitter\IAM\Application\Logout\RefreshTokenRepository;

#[AsCommand(
    name: 'app:cleanup-refresh-tokens',
    description: 'Delete revoked refresh tokens that are already expired'
)]
final class CleanupRefreshTokensCommand extends Command
{
    public function __construct(
        private readonly RefreshTokenRepository $refreshTokenRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $deleted = $this->refreshTokenRepository->deleteRevokedExpired(new DateTimeImmutable());

        $output->writeln(sprintf('OK. Deleted %d refresh token(s).', $deleted));

        return Command::SUCCESS;
    }
}
