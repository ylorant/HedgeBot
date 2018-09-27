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
use Symfony\Component\Console\Command\Command;
use HedgeBot\Core\Console\StorageAwareTrait;

/**
 * Class CreateRoleCommand
 * @package HedgeBot\Core\Console\Security
 */
class CreateRoleCommand extends Command
{
    use StorageAwareTrait;

    /**
     * Configures the command.
     */
    public function configure()
    {
        $this->setName('security:role-create')
            ->setDescription('Creates a security role.')
            ->addOption(
                'role-id',
                null,
                InputOption::VALUE_REQUIRED,
                'Specify manually the role ID (lowercase alphanumeric plus underscore only).'
            )
            ->addOption(
                'parent',
                null,
                InputOption::VALUE_REQUIRED,
                'Specifies a parent for the role. The parent must already exist.'
            )
            ->addArgument('roleName', InputArgument::REQUIRED, 'The name of the role');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $roleName = $input->getArgument('roleName');
        $roleId = SecurityRole::normalizeId($roleName);
        $idOption = $input->getOption('role-id');
        $parentRoleID = $input->getOption('parent');
        $parentRole = null;

        if (!empty($idOption)) {
            if (!SecurityRole::checkId($idOption)) {
                throw new InvalidArgumentException("Role ID syntax is invalid.");
            }

            $roleId = $idOption;
        }

        $accessControlManager = new AccessControlManager($this->getDataStorage());

        // Before creating the role, check if its parent exists
        if (!empty($parentRoleID)) {
            $parentRole = $accessControlManager->getRole($parentRoleID);
            if (!$parentRole) {
                throw new InvalidArgumentException("Parent role ID '" . $parentRole . "' doesn't exist.");
            }
        }

        $newRole = new SecurityRole($roleId);
        $newRole->setName($roleName);

        if (!empty($parentRole)) {
            $newRole->setParent($parentRole);
        }

        // Create the role
        $roleCreated = $accessControlManager->addRole($newRole);

        if (!$roleCreated) {
            throw new RuntimeException("Unable to create role: ID '" . $roleId . "' already exists.");
        }

        $accessControlManager->saveToStorage();
        $output->writeln("New role ID: " . $roleId);
    }
}
