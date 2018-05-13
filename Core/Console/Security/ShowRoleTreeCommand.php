<?php

namespace HedgeBot\Core\Console\Security;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Security\AccessControlManager;
use HedgeBot\Core\Console\StorageAwareCommand;

/**
 * Class ShowRoleTreeCommand
 * @package HedgeBot\Core\Console\Security
 */
class ShowRoleTreeCommand extends StorageAwareCommand
{
    /**
     *
     */
    public function configure()
    {
        $this->setName('security:role-tree')
            ->setDescription('Shows the currently registered roles rights as a tree.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $accessControlManager = new AccessControlManager($this->getDataStorage());
        $roleTree = $accessControlManager->getRoleTree();

        $this->showRoles($output, $roleTree);
    }

    /**
     * @param OutputInterface $output
     * @param $roles
     * @param int $level
     */
    public function showRoles(OutputInterface $output, $roles, $level = 0)
    {
        $prefix = str_repeat("   ", $level > 0 ? $level - 1 : 0);

        foreach ($roles as $role) {
            $output->writeln([
                ($level > 0 ? $prefix . "\xE2\x94\x94> " : "") . $role['role']->getName()
                . " [" . $role['role']->getId() . "]"
            ]);

            if (!empty($role['children'])) {
                $this->showRoles($output, $role['children'], $level + 1);
            }

            if ($level == 0) {
                $output->writeln("");
            }
        }
    }
}
