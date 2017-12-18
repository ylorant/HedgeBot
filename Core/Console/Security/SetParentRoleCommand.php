<?php
namespace HedgeBot\Core\Console\Security;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Security\AccessControlManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use HedgeBot\Core\Security\SecurityRole;
use InvalidArgumentException;

class SetParentRoleCommand extends SecurityCommand
{
    public function configure()
    {
        $this->setName('security:role-set-parent')
            ->setDescription('Sets the parent role for a role. The two roles must already exist.')
            ->addArgument('roleId', InputArgument::REQUIRED, 'The ID of the role to set the parent of.')
            ->addArgument('parentRoleId', InputArgument::REQUIRED, 'The ID of the parent role to set.');

        // Adding default arguments for security commands
        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        // Call parent method to build the data storage
        parent::execute($input, $output);
        
        $roleId = $input->getArgument('roleId');
        $parentRoleId = $input->getArgument('parentRoleId');

        $accessControlManager = new AccessControlManager($this->getDataStorage());
        $role = $accessControlManager->getRole($roleId);
        $parentRole = $accessControlManager->getRole($parentRoleId);

        if(empty($role) || empty($parentRole))
            throw new InvalidArgumentException("Unable to load role '". (empty($roleId) ? $roleId : $parentRoleId). "': role does not exist.");
        
        $role->setParent($parentRole);
        $accessControlManager->saveToStorage();
    }
}