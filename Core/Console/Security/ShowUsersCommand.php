<?php
namespace HedgeBot\Core\Console\Security;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Security\AccessControlManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use HedgeBot\Core\Security\SecurityRole;
use InvalidArgumentException;

class ShowUsersCommand extends SecurityCommand
{
    public function configure()
    {
        $this->setName('security:user-show')
            ->setDescription('Shows the currently registered users and their rights.')
            ->addArgument('username', InputArgument::OPTIONAL, 'Filter by a specific user name.');

        // Adding default arguments for security commands
        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        // Call parent method to build the data storage
        parent::execute($input, $output);
        
        $userName = $input->getArgument('username');
        $accessControlManager = new AccessControlManager($this->getDataStorage());
        
        if(!empty($userName))
            $userList = [$accessControlManager->getUser($userName)];
        else
            $userList = $accessControlManager->getUserList();
        
        foreach($userList as $name => $userRoles)
        {
            $output->writeln($name. ":");

            foreach($userRoles as $roleId)
            {
                $role = $accessControlManager->getRole($roleId);
                $output->writeln("\t". $role->getName(). " (". $role->getId(). ")");
            }

            $output->writeln("");
        }
    }

    public function writeRoleRights($output, $role, $inherited = false, $shownRights = [])
    {
        foreach($role->getRights() as $rightName => $granted)
        {
            if(!in_array($rightName, $shownRights))
            {
                $shownRights[] = $rightName;
                $output->writeln("\t\t". $rightName. ": ". ($granted ? "<fg=green>Granted</>" : "<fg=red>Denied</>"). ($inherited ? " <fg=yellow>(Inherited)</>" : ''));
            }
        }
        
        if($role->getParent())
            $this->writeRoleRights($output, $role->getParent(), true, $shownRights);
    }
}