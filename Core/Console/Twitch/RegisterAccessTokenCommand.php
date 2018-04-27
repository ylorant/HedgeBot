<?php

namespace HedgeBot\Core\Console\Twitch;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use HedgeBot\Core\Console\StorageAwareCommand;
use HedgeBot\Core\Service\Twitch\AuthManager;

/**
 * Class RegisterAccessTokenCommand
 * @package HedgeBot\Core\Console\Twitch
 */
class RegisterAccessTokenCommand extends StorageAwareCommand
{
    /**
     *
     */
    public function configure()
    {
        $this->setName('twitch:register-access-token')
            ->setDescription('Registers/update a token on Twitch to be used on a channel.')
            ->addArgument('channel', InputArgument::REQUIRED, 'The channel where the token will be active.')
            ->addArgument('token', InputArgument::REQUIRED, 'The token.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $config = $this->getConfigStorage();
        $channel = $input->getArgument('channel');
        $token = $input->getArgument('token');

        $twitchAuth = new AuthManager($config->get('twitch.auth.clientId'), $this->getDataStorage());
        $twitchAuth->setAccessToken($channel, $token);

        $twitchAuth->saveToStorage();
    }
}