<?php

namespace HedgeBot\Core\Console\Twitch;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Command\Command;
use HedgeBot\Core\Console\StorageAwareTrait;
use HedgeBot\Core\Service\Twitch\TwitchService;

/**
 * Class RegisterAccessTokenCommand
 * @package HedgeBot\Core\Console\Twitch
 */
class RegisterAccessTokenCommand extends Command
{
    use StorageAwareTrait;

    /**
     * Configures the command.
     */
    public function configure()
    {
        $this->setName('twitch:register-access-token')
            ->setDescription('Registers/update a token on Twitch to be used on a channel.')
            ->addArgument('channel', InputArgument::REQUIRED, 'The channel where the token will be active.')
            ->addArgument('token', InputArgument::REQUIRED, 'The access token.')
            ->addArgument('refresh', InputArgument::REQUIRED, 'The refresh token.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $channel = $input->getArgument('channel');
        $token = $input->getArgument('token');
        $refresh = $input->getArgument('refresh');

        $clientID = $this->config->get('twitch.auth.clientId');
        $clientSecret = $this->config->get('twitch.auth.clientSecret');

        $twitchService = new TwitchService($clientID, $clientSecret, $this->getDataStorage());
        $twitchService->setAccessToken($channel, $token);
        $twitchService->setRefreshToken($channel, $refresh);
    }
}
