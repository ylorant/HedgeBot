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

class RevokeRoleCommand extends StorageAwareCommand
{
    public function configure()
    {
        $this->setName('security:role-revoke')
             ->setDescription('Revokes a role from an user.')
             ->addArgument('username', InputArgument::REQUIRED, 'The nickname of the user that will have this role assigned.')
             ->addArgument('roleId', InputArgument::OPTIONAL, 'The role ID to assign. Required if not using the --all modifier')
             ->addOption('all', 'a', InputOption::VALUE_NONE, 'Add this option to revoke all roles from the user');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $roleId = $input->getArgument('roleId');
        $userName = $input->getArgument('username');
        $allRoles = $input->getOption('all');

        if(!$allRoles && empty($roleId))
            throw new InvalidArgumentException("Role ID has not been specified.");

        $accessControlManager = new AccessControlManager($this->getDataStorage());

        $roleRevoked = null;
        if($allRoles)
            $roleRevoked = $accessControlManager->revokeAllRoles($userName);
        else
            $roleRevoked = $accessControlManager->revokeRole($userName, $roleId);
        
        if(!$roleRevoked)
            throw new RuntimeException("Unable to revoke role: ID '". $roleId. "' not found.");
        
        $accessControlManager->saveToStorage();
    }
}
