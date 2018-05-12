<?php

namespace HedgeBot\Core\Security;

use HedgeBot\Core\Data\Provider as DataProvider;
use HedgeBot\Core\API\Security;

/**
 * AccessManager class. Manages user access to bot functions.
 *
 * - ACL
 * - Roles
 * - User has n Roles
 * - Roles have parents
 * - Roles define their access to rights.
 * - Rights have a namespace
 * - For commands, rights are "command/<commandname>"
 * - Plugins and other parts can notice the existence of the rights
 *   they create to the access control manager for easier listing by APIs.
 * - Other rights types can be dissociated from commands
 * - Then, modifiers are applied.
 */
class AccessControlManager
{
    protected $roleList;
    protected $userList;
    protected $rightList;

    protected $dataProvider;

    /**
     * Constructor. Builds the basis of the access manager.
     *
     * @constructor
     * @param       DataProvider $dataProvider The data storage provider from where to load security data.
     */
    public function __construct(DataProvider $dataProvider)
    {
        $this->roleList = [];
        $this->userList = [];
        $this->rightList = [];

        $this->dataProvider = $dataProvider;

        Security::setObject($this);
        $this->refreshFromStorage();
    }

    /**
     * Refreshes data from the data storage provider given at instanciation.
     */
    public function refreshFromStorage()
    {
        $roleData = $this->dataProvider->get('access');

        // Initializing role data if no data has been found
        if (empty($roleData)) {
            $roleData = [
                "roles" => [],
                "users" => []
            ];
        }

        $roleList = $roleData["roles"] ?? [];
        $this->userList = $roleData["users"] ?? [];

        // Creating role objects
        foreach ($roleList as $role) {
            $this->roleList[$role['id']] = SecurityRole::fromArray($role);
        }

        // Binding parents to their roles, a bit redundant, but necessary
        foreach ($roleList as $role) {
            if (!$this->roleList[$role['id']]->getParent() && !empty($role['parent'])) {
                $this->roleList[$role['id']]->setParent($role['parent']);
            }
        }
    }

    /**
     * Saves access right data to the storage provider.
     */
    public function saveToStorage()
    {
        $roleList = [];

        foreach ($this->roleList as $role) {
            $roleList[$role->getId()] = $role->toArray();
        }

        $this->dataProvider->set('access', [
            "roles" => $roleList,
            "users" => $this->userList
        ]);
    }

    /**
     * Adds a role to the access control manager.
     *
     * @param  SecurityRole $role The role to add.
     * @return boolean            True if the role has been created, False if not (the role already exists).
     */
    public function addRole(SecurityRole $role)
    {
        if (isset($this->roleList[$role->getId()])) {
            return false;
        }

        $this->roleList[$role->getId()] = $role;

        return true;
    }

    /**
     * Deletes a role.
     *
     * @param  string $roleId The role ID.
     * @return boolean         True if the role has been deleted, False if not (role not found).
     */
    public function deleteRole($roleId)
    {
        // Check if role exists
        $role = $this->getRole($roleId);
        if (empty($role)) {
            return false;
        }

        // Assign rights from this role to children rôles to minimize impact when deleting a role
        $this->reassignRightsToChildren($role);

        // Delete the role
        unset($this->roleList[$roleId]);

        // Delete role references in user list
        foreach ($this->userList as &$user) {
            $key = array_search($roleId, $user);
            if ($key !== false) {
                unset($user[$key]);
            }
        }

        return true;
    }

    /**
     * Gets a role by its ID.
     *
     * @param  string $roleId The identifier of the role to get.
     * @return SecurityRole|null         The security role if found, null if not.
     */
    public function getRole($roleId)
    {
        if (isset($this->roleList[$roleId])) {
            return $this->roleList[$roleId];
        }

        return null;
    }

    /**
     * Assigns a role to an user.
     *
     * @param  string $user The user to assign a role to. If it doesn't exist in the database, it will be created.
     * @param  string $roleId The role ID to assign.
     * @return boolean True if the role has been assigned successfully, false if not (the role hasn't been found).
     */
    public function assignRole($user, $roleId)
    {
        if (!isset($this->userList[$user])) {
            $this->userList[$user] = [];
        }

        // Check if the role exists
        if (!$this->getRole($roleId)) {
            return false;
        }

        if (!in_array($roleId, $this->userList[$user])) {
            $this->userList[$user][] = $roleId;
        }

        return true;
    }

    /**
     * @param $user
     * @param $roleId
     * @return bool
     */
    public function revokeRole($user, $roleId)
    {
        if (isset($this->userList[$user])) {
            if (!$this->getRole($roleId)) {
                return false;
            }

            if (in_array($roleId, $this->userList[$user])) {
                $key = array_search($roleId, $this->userList[$user]);
                unset($this->userList[$user][$key]);
            }
        }

        return true;
    }

    /**
     * @param $user
     * @return bool
     */
    public function revokeAllRoles($user)
    {
        if (isset($this->userList[$user])) {
            $this->userList[$user] = [];
        }

        return true;
    }

    /**
     * Replaces users by the new ones.
     *
     * @param string $roleId The role ID.
     * @param array $newUsers The new users.
     * @return bool
     */
    public function replaceUsers($roleId, $newUsers)
    {
        // Remove the role from all the users
        foreach ($this->userList as $user => $roles) {
            if (($key = array_search($roleId, $roles)) !== false) {
                unset($roles[$key]);
            }
        }

        // Reinsert the role into the new users
        foreach ($newUsers as $user) {
            $this->assignRole($user, $roleId);
        }

        return true;
    }

    /**
     * Checks if an user has access to a specific right.
     *
     * @param $user string The user to check.
     * @param $right string The right to check.
     *
     * @return bool True if the user has access to this role, false otherwise.
     */
    public function hasRight($user, $right)
    {
        // Find user
        $userRoles = $this->getUserRoles($user);
        if (!empty($userRoles)) {
            // Check all the roles from the user for the right
            foreach ($userRoles as $role) {
                if ($this->roleList[$role]->hasRight($right)) {
                    return true;
                }
            }
        }

        // Check default roles for the right too
        $defaultRoles = $this->getDefaultRolesList();
        foreach ($defaultRoles as $defaultRole) {
            if ($this->roleList[$defaultRole]->hasRight($right)) {
                return true;
            }
        }

        // No role allows the right
        return false;
    }

    /**
     * Adds a right to the right list.
     *
     * @param mixed ...$rightNames The name of the right to add. Variadic.
     */
    public function addRights(...$rightNames)
    {
        foreach ($rightNames as $rightName) {
            if (!in_array($rightName, $this->rightList)) {
                $this->rightList[] = $rightName;
            }
        }

        $this->rightList = array_values($this->rightList); // Re-index the right list
    }

    /**
     * Removes a right from the right list.
     *
     * @param mixed ...$rightNames The name of the right to remove
     */
    public function removeRights(...$rightNames)
    {
        foreach ($rightNames as $rightNames) {
            $rightKey = array_search($rightNames, $this->rightList);
            if ($rightKey !== false) {
                unset($this->rightList[$rightKey]);
            }
        }

        $this->rightList = array_values($this->rightList); // Re-index the right list
    }

    /**
     * Gets the right list.
     */
    public function getRightList()
    {
        return $this->rightList;
    }

    /**
     * Check if the 2 given roles are in a relation one to each other
     * (one is already in the parent/children chain to another).
     *
     * @param SecurityRole $role1 The first role.
     * @param SecurityRole $role2 The second role.
     *
     * @return bool True if the roles have a relation in common, false otherwise.
     */
    public function rolesHaveRelation(SecurityRole $role1, SecurityRole $role2)
    {
        $tmpRole = $role1;

        do {
            if ($tmpRole->getId() == $role2->getId()) {
                return true;
            }

            $tmpRole = $tmpRole->getParent();
        } while ($tmpRole != null);

        $tmpRole = $role2;

        do {
            if ($tmpRole->getId() == $role1->getId()) {
                return true;
            }

            $tmpRole = $tmpRole->getParent();
        } while ($tmpRole != null);

        return false;
    }

    /**
     * Gets the role relationship complete tree.
     *
     * @return array The hierarchy of roles, with each role as a SecurityRole object.
     */
    public function getRoleTree()
    {
        // This anonymous function fills a branch
        $fillBranchFunc = function ($base) use (&$fillBranchFunc) {
            $branch = [];

            foreach ($this->roleList as $role) {
                if ($base === $role->getParent()
                    || !is_null($role->getParent()) && $role->getParent()->getId() == $base) {
                    $branch[] = [
                        'role' => $role,
                        'children' => $fillBranchFunc($role->getId())
                    ];
                }
            }

            return $branch;
        };

        return $fillBranchFunc(null);
    }

    /**
     * Gets the roles from an user
     * @param  string $user The username to get the roles of.
     * @return array|null       An array containing the roles of the user, or NULL if the user hasn't been found.
     */
    public function getUserRoles($user)
    {
        if (!isset($this->userList[$user])) {
            return null;
        }

        return $this->userList[$user];
    }

    /**
     * Gets the roles that are marked as default.
     *
     * @return array The list of default roles.
     */
    public function getDefaultRolesList()
    {
        $roleList = [];

        foreach ($this->roleList as $role) {
            if ($role->isDefault()) {
                $roleList[] = $role->getId();
            }
        }

        return $roleList;
    }

    /**
     * Gets the role list.
     *
     * @return array The list of roles, with the role name as key, and its rights as values.
     */
    public function getRoleList()
    {
        return $this->roleList;
    }

    /**
     * @param $roleId
     * @return array
     */
    public function getRoleUsers($roleId)
    {
        $users = [];
        foreach ($this->userList as $user => $userRoles) {
            if (in_array($roleId, $userRoles)) {
                $users[] = $user;
            }
        }

        return $users;
    }

    /**
     * Gets the list of registered users.
     * @return array The user list.
     */
    public function getUserList()
    {
        return $this->userList;
    }

    /**
     * Gets an user's roles.
     *
     * @param  string $username The name of the user.
     * @return array            The user's roles. If the user is not found, it'll return an empty array.
     */
    public function getUser($username)
    {
        if (isset($this->userList[$username])) {
            return $this->userList[$username];
        }
    }

    /**
     * Reassigns parent roles and rights to the children roles of the given role, basically
     * making the role orphan and ready for removal, while minimizing impact on other roles.
     *
     * @param SecurityRole $parentRole The role to reassign the children of.
     */
    protected function reassignRightsToChildren($parentRole)
    {
        $parentRoleRights = $parentRole->getRights();

        // Cycle through all roles to find children roles of this role.
        /** @var SecurityRole $role */
        foreach ($this->roleList as $role) {
            if (!empty($role->getParent()) && $role->getParent()->getId() == $parentRole->getId()) {
                //Reassign parent role's parent role (i.e. grandparent role) to child, or null if it's null
                $role->setParent($parentRole->getParent() ?? null);

                // Assign rights defined in this role to children, if they don't have roles that override them
                $role->replaceRights(array_merge($parentRoleRights, $role->getRights()));
            }
        }
    }
}
