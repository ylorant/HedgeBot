<?php
namespace HedgeBot\Core\Console\Doc;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use HedgeBot\Documentor\Documentor;

/**
 * Class DocGenerateCommand
 * @package HedgeBot\Core\Console
 */
class DocGenerateCommand extends Command
{
    /**
     *
     */
    protected function configure()
    {
        $this->setName('doc:generate')
            ->setDescription('Generates the user documentation for the bot\'s commands.')
            ->addArgument('outputDir', InputArgument::OPTIONAL, 'Sets the output directory for the generated doc.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $documentor = new Documentor($output);

        if (!empty($input->getArgument('outputDir'))) {
            $documentor->setOutputDirectory($input->getArgument('outputDir'));
        }

        $documentor->generate();
    }
}
