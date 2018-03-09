<?php
namespace HedgeBot\Core\Console\Twitch;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Security\AccessControlManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use HedgeBot\Core\Console\StorageAwareCommand;
use HedgeBot\Core\Service\Twitch\AuthManager;

class RevokeAccessTokenCommand extends StorageAwareCommand
{
    public function configure()
    {
        $this->setName('twitch:revoke-access-token')
             ->setDescription('Revokes an auth access token from a channel.')
             ->addArgument('channel', InputArgument::REQUIRED, 'The channel from which the token will be revoked.');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $config = $this->getConfigStorage();
        $channel = $input->getArgument('channel');

        $twitchAuth = new AuthManager($config->get('twitch.auth.clientId'), $this->getDataStorage());
        $twitchAuth->removeAccessToken($channel);

        $twitchAuth->saveToStorage();
    }
}