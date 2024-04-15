<?php
namespace HedgeBot\Plugins\RemoteTimer\Console;

use HedgeBot\Core\Console\PluginAwareTrait;
use HedgeBot\Plugins\RemoteTimer\RemoteTimer;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RenewTimerKeyCommand extends Command
{
    use PluginAwareTrait;

    /**
     * Configures the command.
     */
    public function configure()
    {
        $this->setName('remote-timer:renew-key')
        ->setDescription('Regenerates a given timer\'s key.')
        ->addArgument('key', InputArgument::REQUIRED, 'The timer key to renew.');
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $key = $input->getArgument('key');

        /** @var RemoteTimer $plugin */
        $plugin = $this->getPlugin();
        $timer = $plugin->renewTimerKey($key);
        
        if (empty($timer)) {
            throw new RuntimeException("Remote timer not found.");
        }
        
        $output->writeln("New key: " . $timer->getKey());
    }
}