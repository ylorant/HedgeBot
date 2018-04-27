<?php

namespace HedgeBot\Core\Console\Security;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Security\AccessControlManager;
use Symfony\Component\Console\Input\InputArgument;
use RuntimeException;
use HedgeBot\Core\Console\StorageAwareCommand;

/**
 * Class DeleteRoleCommand
 * @package HedgeBot\Core\Console\Security
 */
class DeleteRoleCommand extends StorageAwareCommand
{
    /**
     *
     */
    public function configure()
    {
        $this->setName('security:role-delete')
            ->setDescription('Deletes a role.')
            ->addArgument('roleId', InputArgument::REQUIRED, 'The role ID to delete.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $roleId = $input->getArgument('roleId');

        $accessControlManager = new AccessControlManager($this->getDataStorage());
        $roleCreated = $accessControlManager->deleteRole($roleId);

        if (!$roleCreated) {
            throw new RuntimeException("Unable to delete role: ID '" . $roleId . "' not found.");
        }

        $accessControlManager->saveToStorage();
    }
}
