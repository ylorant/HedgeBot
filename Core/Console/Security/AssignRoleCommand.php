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

class AssignRoleCommand extends StorageAwareCommand
{
    public function configure()
    {
        $this->setName('security:role-assign')
            ->setDescription('Assigns a role to an user.')
            ->addArgument('username', InputArgument::REQUIRED,
                'The nickname of the user that will have this role assigned.')
            ->addArgument('roleId', InputArgument::REQUIRED, 'The role ID to assign.');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $roleId = $input->getArgument('roleId');
        $userName = $input->getArgument('username');

        $accessControlManager = new AccessControlManager($this->getDataStorage());
        $roleCreated = $accessControlManager->assignRole($userName, $roleId);

        if (!$roleCreated) {
            throw new RuntimeException("Unable to assign role: ID '" . $roleId . "' not found.");
        }

        $accessControlManager->saveToStorage();
    }
}
