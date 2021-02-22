<?php
namespace HedgeBot\Core\Console\Twitch;

use HedgeBot\Core\Console\StorageAwareTrait;
use HedgeBot\Core\Service\Twitch\TwitchService;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TwitchClient\API\Auth\Authentication;

/** 
 * Class GenerateDefaultAccessToken
 * @package HedgeBot\Core\Console\Twitch
 */
class GenerateDefaultAccessToken extends Command
{
    use StorageAwareTrait;
    
    /**
     * Configures the command.
     */
    public function configure()
    {
        $this->setName('twitch:generate-default-token')
            ->setDescription('Generates a default access token for Twitch API calls not bound to a channel.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $clientID = $this->config->get('twitch.auth.clientId');
        $clientSecret = $this->config->get('twitch.auth.clientSecret');

        $twitchService = new TwitchService($clientID, $clientSecret, $this->getDataStorage());
        /** @var Authentication $authAPI */
        $authAPI = $twitchService->getClient(TwitchService::CLIENT_TYPE_AUTH);

        // Getting the client credentials auto-registers them in the token provider
        $token = $authAPI->getClientCredentialsToken();

        if($token === false) {
            throw new RuntimeException("Failed fetching the default access token.");
        }

        $output->writeln([
            "Fetched default access token:",
            "Token: " . $token['token'],
            "Refresh: " . (!empty($token['refresh']) ? $token['refresh'] : "null")
        ]);
    }
}