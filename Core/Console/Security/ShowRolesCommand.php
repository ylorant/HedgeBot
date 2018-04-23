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

class ShowRolesCommand extends StorageAwareCommand
{
    public function configure()
    {
        $this->setName('security:role-show')
            ->setDescription('Shows the currently registered roles and their rights.')
            ->addArgument('roleId', InputArgument::OPTIONAL, 'Filter by a specific role.');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $roleId = $input->getArgument('roleId');
        $accessControlManager = new AccessControlManager($this->getDataStorage());

        if (!empty($roleId)) {
            $roleList = [$accessControlManager->getRole($roleId)];
        } else {
            $roleList = $accessControlManager->getRoleList();
        }

        foreach ($roleList as $role) {
            $output->writeln([
                $role->getId() . ":",
                "\tName: " . $role->getName(),
                "\tParent: " . (!empty($role->getParent()) ? $role->getParent()->getId() : "-"),
                "\tDefault: " . ($role->isDefault() ? "yes" : "no"),
                "\tRights: "
            ]);

            $this->writeRoleRights($output, $role);

            $output->writeln("");
        }
    }

    public function writeRoleRights($output, $role, $inherited = false, $shownRights = [])
    {
        foreach ($role->getRights() as $rightName => $granted) {
            if (!in_array($rightName, $shownRights)) {
                $shownRights[] = $rightName;
                $output->writeln("\t\t" . $rightName . ": " . ($granted ? "<fg=green>Granted</>" : "<fg=red>Denied</>") . ($inherited ? " <fg=yellow>(Inherited)</>" : ''));
            }
        }

        if ($role->getParent()) {
            $this->writeRoleRights($output, $role->getParent(), true, $shownRights);
        }
    }
}