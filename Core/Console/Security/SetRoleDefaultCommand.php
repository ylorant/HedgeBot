<?php

namespace HedgeBot\Core\Console\Security;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Security\AccessControlManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use HedgeBot\Core\Security\SecurityRole;
use InvalidArgumentException;
use RuntimeException;
use HedgeBot\Core\Console\StorageAwareCommand;

class SetRoleDefaultCommand extends StorageAwareCommand
{
    public function configure()
    {
        $this->setName('security:role-set-default')
            ->setDescription('Sets the default status of a role.')
            ->addArgument('roleId', InputArgument::REQUIRED, 'The role ID to delete.')
            ->addOption('disable', 'd', InputOption::VALUE_NONE,
                'Set this option to disable the default status of the role.');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $roleId = $input->getArgument('roleId');
        $default = !$input->getOption('disable'); // The default status is basically the opposite of the presence of the disabled option

        $accessControlManager = new AccessControlManager($this->getDataStorage());
        $role = $accessControlManager->getRole($roleId);

        if (!$role) {
            throw new RuntimeException("Unable to delete role: ID '" . $roleId . "' not found.");
        }

        $role->setDefault($default);

        $accessControlManager->saveToStorage();
    }
}
