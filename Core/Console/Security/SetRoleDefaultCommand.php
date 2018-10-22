<?php

namespace HedgeBot\Core\Console\Security;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Security\AccessControlManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use RuntimeException;
use HedgeBot\Core\Console\StorageAwareTrait;
use Symfony\Component\Console\Command\Command;

/**
 * Class SetRoleDefaultCommand
 * @package HedgeBot\Core\Console\Security
 */
class SetRoleDefaultCommand extends Command
{
    use StorageAwareTrait;

    /**
     * Configures the command.
     */
    public function configure()
    {
        $this->setName('security:role-set-default')
            ->setDescription('Sets the default status of a role.')
            ->addArgument('roleId', InputArgument::REQUIRED, 'The role ID to delete.')
            ->addOption(
                'disable',
                'd',
                InputOption::VALUE_NONE,
                'Set this option to disable the default status of the role.'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $roleId = $input->getArgument('roleId');
        // The default status is basically the opposite of the presence of the disabled option
        $default = !$input->getOption('disable');

        $accessControlManager = new AccessControlManager($this->getDataStorage());
        $role = $accessControlManager->getRole($roleId);

        if (!$role) {
            throw new RuntimeException("Unable to delete role: ID '" . $roleId . "' not found.");
        }

        $role->setDefault($default);

        $accessControlManager->saveToStorage();
    }
}
