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

class ShowRoleTreeCommand extends StorageAwareCommand
{
    public function configure()
    {
        $this->setName('security:role-tree')
            ->setDescription('Shows the currently registered roles rights as a tree.');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $accessControlManager = new AccessControlManager($this->getDataStorage());
        $roleTree = $accessControlManager->getRoleTree();    
        
        $this->showRoles($output, $roleTree);
    }

    public function showRoles(OutputInterface $output, $roles, $level = 0)
    {
        $prefix = str_repeat("   ", $level > 0 ? $level - 1 : 0);

        foreach($roles as $role)
        {
            $output->writeln([
                ($level > 0 ? $prefix. "\xE2\x94\x94> " : ""). $role['role']->getName(). " [". $role['role']->getId(). "]"
            ]);

            if(!empty($role['children']))
                $this->showRoles($output, $role['children'], $level + 1);
            
            if($level == 0)
                $output->writeln("");
        }
    }
}