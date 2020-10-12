<?php
namespace HedgeBot\Core\Console\Bot;

use HedgeBot\Core\Console\ConsoleProvider;
use HedgeBot\Core\HedgeBot;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StartBotCommand extends Command
{
    const COMMAND_NAME = 'bot:start';

    public function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription("Starts the bot.");
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        HedgeBot::setEnv("main");

        $hedgebot = ConsoleProvider::getBot();
        $initialized = $hedgebot->init($input->getOption('config'));

        if ($initialized) {
            $hedgebot->run();
        }
    }
}