<?php

namespace HedgeBot\Core\Console\Twitch;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Security\AccessControlManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use HedgeBot\Core\Console\StorageAwareCommand;
use HedgeBot\Core\Service\Twitch\AuthManager;

class RegisterAccessTokenCommand extends StorageAwareCommand
{
    public function configure()
    {
        $this->setName('twitch:register-access-token')
            ->setDescription('Registers/update a token on Twitch to be used on a channel.')
            ->addArgument('channel', InputArgument::REQUIRED, 'The channel where the token will be active.')
            ->addArgument('token', InputArgument::REQUIRED, 'The token.');
    }

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