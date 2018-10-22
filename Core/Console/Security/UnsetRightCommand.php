<?php

namespace HedgeBot\Core\Console\Security;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Security\AccessControlManager;
use Symfony\Component\Console\Input\InputArgument;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use HedgeBot\Core\Console\StorageAwareTrait;

/**
 * Class UnsetRightCommand
 * @package HedgeBot\Core\Console\Security
 */
class UnsetRightCommand extends Command
{
    use StorageAwareTrait;

    /**
     * Configures the command.
     */
    public function configure()
    {
        $this->setName('security:right-unset')
            ->setDescription('Unsets a right to a role, i.e. removes it from the list of defined rights for that role.')
            ->addArgument('roleId', InputArgument::REQUIRED, 'The ID of the role to unset a right from.')
            ->addArgument('rightName', InputArgument::REQUIRED, 'The right to unset.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $roleId = $input->getArgument('roleId');
        $rightName = $input->getArgument('rightName');

        $accessControlManager = new AccessControlManager($this->getDataStorage());
        $role = $accessControlManager->getRole($roleId);

        if (empty($role)) {
            throw new InvalidArgumentException("Unable to load role '" . $roleId . "': role does not exist.");
        }

        $role->unsetRight($rightName);
        $accessControlManager->saveToStorage();
    }
}
