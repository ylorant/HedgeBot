<?php
namespace HedgeBot\Plugins\Twitter\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Console\PluginAwareTrait;
use Symfony\Component\Console\Question\Question;
use RuntimeException;
use Symfony\Component\Console\Command\Command;

/**
 * Class CreateTokenCommand
 * @package HedgeBot\Plugins\Twitter\Console
 */
class CreateTokenCommand extends Command
{
    use PluginAwareTrait;

    /**
     * Configures the command.
     */
    public function configure()
    {
        $this->setName('twitter:create-token')
            ->setDescription('Creates a token for an user on Twitter.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var TwitterService $service */
        $service = $this->getPlugin()->getService();
        $helper = $this->getHelper('question');

        $oauthUrl = $service->getAuthorizeUrl();

        $output->writeln([
            'To link your Twitter account, go to the following URL:',
            '',
            $oauthUrl,
            '',
            'Once validated, you will be redirected to the app\'s OAuth redirect URL, '.
            'with an "oauth_verifier" GET parameter. Take the value of this parameter and '.
            'write it below.',
            ''
        ]);
        
        $question = new Question("oauth_verifier: ");
        $oauthVerifier = $helper->ask($input, $output, $question);

        $tokenCreated = $service->createAccessToken($oauthVerifier);

        if(!$tokenCreated) {
            throw new RuntimeException("Cannot create the access token.");
        }
    }
}