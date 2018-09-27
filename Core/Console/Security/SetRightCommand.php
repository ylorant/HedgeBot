<?php

namespace HedgeBot\Core\Console\Security;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Security\AccessControlManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use HedgeBot\Core\Console\StorageAwareTrait;

/**
 * Class SetRightCommand
 * @package HedgeBot\Core\Console\Security
 */
class SetRightCommand extends Command
{
    use StorageAwareTrait;

    /**
     * Configures the command.
     */
    public function configure()
    {
        $this->setName('security:right-set')
            ->setDescription('Sets a right to a role. Implicitely, the right is granted, but you can deny it explicitely too.')
            ->addArgument('roleId', InputArgument::REQUIRED, 'The ID of the role to add a right to.')
            ->addArgument('rightName', InputArgument::REQUIRED, 'The right to set.')
            ->addOption(
                'denied',
                'd',
                InputOption::VALUE_NONE,
                'Use this option to explicitly set the right to denied.'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $rightGranted = !$input->getOption('denied');
        $roleId = $input->getArgument('roleId');
        $rightName = $input->getArgument('rightName');

        $accessControlManager = new AccessControlManager($this->getDataStorage());
        $role = $accessControlManager->getRole($roleId);

        if (empty($role)) {
            throw new InvalidArgumentException("Unable to load role '" . $roleId . "': role does not exist.");
        }

        $role->setRight($rightName, $rightGranted);
        $accessControlManager->saveToStorage();
    }
}
