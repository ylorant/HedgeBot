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

class UnsetRightCommand extends StorageAwareCommand
{
    public function configure()
    {
        $this->setName('security:right-unset')
            ->setDescription('Unsets a right to a role, i.e. removes it from the list of defined rights for that role.')
            ->addArgument('roleId', InputArgument::REQUIRED, 'The ID of the role to unset a right from.')
            ->addArgument('rightName', InputArgument::REQUIRED, 'The right to unset.');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $roleId = $input->getArgument('roleId');
        $rightName = $input->getArgument('rightName');

        $accessControlManager = new AccessControlManager($this->getDataStorage());
        $role = $accessControlManager->getRole($roleId);

        if(empty($role))
            throw new InvalidArgumentException("Unable to load role '". $roleId. "': role does not exist.");
        
        $role->unsetRight($rightName);
        $accessControlManager->saveToStorage();
    }
}