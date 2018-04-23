<?php

namespace HedgeBot\Core\Console\Security;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Security\AccessControlManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use HedgeBot\Core\Security\SecurityRole;
use InvalidArgumentException;
use HedgeBot\Core\Console\StorageAwareCommand;

class SetParentRoleCommand extends StorageAwareCommand
{
    public function configure()
    {
        $this->setName('security:role-set-parent')
            ->setDescription('Sets the parent role for a role. The two roles must already exist.')
            ->addArgument('roleId', InputArgument::REQUIRED, 'The ID of the role to set the parent of.')
            ->addArgument('parentRoleId', InputArgument::OPTIONAL, 'The ID of the parent role to set.')
            ->addOption('delete', 'd', InputOption::VALUE_NONE);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $roleId = $input->getArgument('roleId');
        $parentRoleId = $input->getArgument('parentRoleId');
        $deleteParent = $input->getOption('delete');

        $accessControlManager = new AccessControlManager($this->getDataStorage());
        $role = $accessControlManager->getRole($roleId);
        $parentRole = null;

        if (empty($role)) {
            throw new InvalidArgumentException("Unable to load role '" . $roleId . "': role does not exist.");
        }

        if (!$deleteParent) {
            $parentRole = $accessControlManager->getRole($parentRoleId);
            if (empty($parentRole)) {
                throw new InvalidArgumentException("Unable to load role '" . $parentRoleId . "': role does not exist.");
            }

            if ($accessControlManager->rolesHaveRelation($role, $parentRole)) {
                throw new InvalidArgumentException("Unable to set parent of '" . $roleId . "' to '" . $parentRoleId . "': Roles already have a parent/child relation.");
            }
        }


        $role->setParent($parentRole);
        $accessControlManager->saveToStorage();
    }
}