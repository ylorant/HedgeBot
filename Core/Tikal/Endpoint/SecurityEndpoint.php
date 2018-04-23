<?php

namespace HedgeBot\Core\Tikal\Endpoint;

use HedgeBot\Core\API\Security;
use HedgeBot\Core\Security\SecurityRole;
use stdClass;

class SecurityEndpoint
{
    /**
     * Gets the currently available roles into the security system.
     *
     * @return array The list of currently registered roles, with all their settings and rights.
     */
    public function getRoles()
    {
        $roleList = Security::getRoleList();
        foreach ($roleList as &$role) {
            $role = $role->toArray();
        }

        return $roleList;
    }

    /**
     * Gets information about a specific role. It will also return the users that have this role.
     *
     * @param string $roleId The ID of the role.
     *
     * @return array|null The role data, or null if the role hasn't been found.
     */
    public function getRole($roleId)
    {
        $role = Security::getRole($roleId);
        if (!$role) {
            return null;
        }

        $inheritedRights = $role->getInheritedRights();

        $role = $role->toArray();
        $role['users'] = Security::getRoleUsers($roleId);
        $role['inheritedRights'] = $inheritedRights;

        return $role;
    }

    /**
     * Saves a role into the security system.
     *
     * @param string $roleId The ID of the role to save. Must already exist. Use createRole() if not.
     * @param object $roleData The data of the role. Refer to the output of the getRoles() method to see the
     *                         expected format.
     *
     * @return bool True if the role has been successfully saved, false if an error occured.
     *              Usual errors can be: Parent role not found when specified, parent role is invalid.
     */
    public function saveRole($roleId, stdClass $roleData)
    {
        // Try to get the role object, and if it doesn't exist, create it.
        $role = Security::getRole($roleId);

        if (empty($role)) {
            $role = new SecurityRole($roleId);
            Security::addRole($role);
        }

        // Save the parent role if found and different from the previous one. if not found, return false.
        if (!empty($roleData->parent) && (empty($role->getParent()) || $roleData->parent != $role->getParent()->getId())) {
            $parentRole = Security::getRole($roleData->parent);
            if (empty($parentRole)) {
                return false;
            }

            if (Security::rolesHaveRelation($role, $parentRole)) {
                return false;
            }

            $role->setParent($parentRole);
        }

        // Save the name
        if (!empty($roleData->name)) {
            $role->setName($roleData->name);
        }

        // Save the default status
        if (isset($roleData->default)) {
            $role->setDefault($roleData->default);
        }

        // Replace the role rights
        if (!empty($roleData->rights)) {
            $role->replaceRights((array)$roleData->rights);
        }

        // Save the users
        if (!empty($roleData->users)) {
            Security::replaceUsers($roleId, $roleData->users);
        }

        Security::saveToStorage();

        return true;
    }

    /**
     * Creates a role into the security system.
     *
     * @param  string $roleId The Id of the role to create. Must not already exist, and be syntactically correct.
     * @return bool True if the role has been created successfully, false if not.
     *              Usual errors can be: Incorrect role ID syntax, already existing role.
     */
    public function createRole($roleId)
    {
        $alreadyExistingRole = Security::getRole($roleId);
        if (!empty($alreadyExistingRole)) {
            return false;
        }

        if (!SecurityRole::checkId($roleId)) {
            return false;
        }

        $newRole = new SecurityRole($roleId);
        $roleCreated = Security::addRole($newRole);

        if (!$roleCreated) {
            return false;
        }

        Security::saveToStorage();

        return true;
    }

    public function deleteRole($roleId)
    {
        return Security::deleteRole($roleId);
    }

    /**
     * Gets the tree hierarchy of the roles registered into the bot.
     *
     * @return array The role tree as a recursive array. Each leaf has 2 keys:
     *               - role: The role ID
     *               - children: The role children
     */
    public function getRoleTree()
    {
        $roleTree = Security::getRoleTree();
        $roleTreeOut = [];

        $simplifyRoleTree = function (&$level) use (&$simplifyRoleTree) {
            foreach ($level as &$role) {
                $role['role'] = $role['role']->getId();
                if (!empty($role['children'])) {
                    $simplifyRoleTree($role['children']);
                }
            }
        };

        $simplifyRoleTree($roleTree);

        return $roleTree;
    }

    /**
     * Gets the registered users in the bot.
     *
     * @return array The user list as an array with the username as key, and its roles as the value.
     */
    public function getUsers()
    {
        return Security::getUserList();
    }

    /**
     * Gets the complete list of all the registered rights into the rights system.
     *
     * @return array The list of rights currently registered in the security management system.
     */
    public function getRights()
    {
        return Security::getRightList();
    }
}