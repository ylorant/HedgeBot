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

class DeleteRoleCommand extends SecurityCommand
{
    public function configure()
    {
        $this->setName('security:role-delete')
             ->setDescription('Deletes a role.')
             ->addArgument('roleId', InputArgument::REQUIRED, 'The role ID to delete.');

        // Adding default arguments for security commands
        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        // Call parent method to build the data storage
        parent::execute($input, $output);

        $roleId = $input->getArgument('roleId');

        $accessControlManager = new AccessControlManager($this->getDataStorage());
        $roleCreated = $accessControlManager->deleteRole($roleId);
        
        if(!$roleCreated)
            throw new RuntimeException("Unable to delete role: ID '". $roleId. "' not found.");
        
        $accessControlManager->saveToStorage();
    }
}
