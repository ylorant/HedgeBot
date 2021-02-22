<?php

namespace HedgeBot\Core\Console\Twitch;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Command\Command;
use HedgeBot\Core\Console\StorageAwareTrait;
use HedgeBot\Core\Service\Twitch\TwitchService;

/**
 * Class RevokeAccessTokenCommand
 * @package HedgeBot\Core\Console\Twitch
 */
class RevokeAccessTokenCommand extends Command
{
    use StorageAwareTrait;

    /**
     * Configures the command.
     */
    public function configure()
    {
        $this->setName('twitch:revoke-access-token')
            ->setDescription('Revokes an auth access token from a channel.')
            ->addArgument('channel', InputArgument::REQUIRED, 'The channel from which the token will be revoked.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $channel = $input->getArgument('channel');

        $clientID = $this->config->get('twitch.auth.clientId');
        $clientSecret = $this->config->get('twitch.auth.clientSecret');

        $twitchService = new TwitchService($clientID, $clientSecret, $this->getDataStorage());
        $twitchService->removeAccessToken($channel);
    }
}
