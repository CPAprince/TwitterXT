<?php

declare(strict_types=1);

namespace Twitter\Tweet\UI\CLI;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Twitter\Tweet\Application\Moderation\ModerationBatchItem;
use Twitter\Tweet\Application\Moderation\ModerationConfig;
use Twitter\Tweet\Application\Moderation\TweetModerationProviderInterface;

#[AsCommand(
    name: 'app:test-moderation-provider',
    description: 'Test moderation provider resolution and batch moderation'
)]
final class TestModerationProviderCommand extends Command
{
    public function __construct(
        private readonly ModerationConfig $config,
        private readonly TweetModerationProviderInterface $provider,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Mode: '.$this->config->mode->value);
        $output->writeln('Model: '.$this->config->model);

        $items = [
            new ModerationBatchItem(
                tweetId: 'demo-tweet-1',
                text: 'GLAD TO SEE YOU! Keep going! Be wise!',
                moderationVersion: 1,
                estimatedTokens: 9,
            ),
            new ModerationBatchItem(
                tweetId: 'demo-tweet-2',
                text: 'I will kill you little m@theF@CK@!',
                moderationVersion: 1,
                estimatedTokens: 7,
            ),
        ];

        try {
            $decisions = $this->provider->moderateBatch($items);

            foreach ($decisions as $decision) {
                $output->writeln(sprintf(
                    '%s => %s%s',
                    $decision->tweetId,
                    $decision->approved ? 'APPROVED' : 'REJECTED',
                    null !== $decision->reason ? ' ('.$decision->reason.')' : ''
                ));
            }
        } catch (Throwable $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
