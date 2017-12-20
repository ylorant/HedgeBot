<?php
namespace HedgeBot\Core\Security;

use HedgeBot\Core\Data\Provider as DataProvider;
use HedgeBot\Core\Security\SecurityRole;
use InvalidArgumentException;
use HedgeBot\Core\API\Security;

/**
 * AccessManager class. Manages user access to bot functions.
 *
 * - ACL
 * - Roles
 * - User has n Roles
 * - Roles have parents
 * - Roles define their access to rights.
 * - Rights have a namespace (doesn't have to be represented
 *   in code from the access manager)
 * - For commands, rights are "command/<commandname>"
 * - Other rights types can be dissociated from commands
 * - Then, modifiers are applied.
 */
class AccessControlManager
{
    protected $roleList;
    protected $userList;

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
        if(empty($roleData)) {
            $roleData = [
                "roles" => [],
                "users" => []
            ];
        }

        $roleList = $roleData["roles"] ?? [];
        $this->userList = $roleData["users"] ?? [];

        // Creating role objects
        foreach($roleList as $role)
            $this->roleList[$role['id']] = SecurityRole::fromArray($role);

        // Binding parents to their roles, a bit redundant, but necessary
        foreach($roleList as $role)
        {
            if(!$this->roleList[$role['id']]->getParent() && !empty($role['parent']))
                $this->roleList[$role['id']]->setParent($role['parent']);
        }
    }

    /**
     * Saves access right data to the storage provider.
     */
    public function saveToStorage()
    {
        $roleList = [];

        foreach($this->roleList as $role)
            $roleList[$role->getId()] = $role->toArray();
        
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
        if(isset($this->roleList[$role->getId()]))
            return false;
        
        $this->roleList[$role->getId()] = $role;

        return true;
    }

    /**
     * Deletes a role.
     *
     * @param  string  $roleId The role ID.
     * @return boolean         True if the role has been deleted, False if not (role not found).
     */
    public function deleteRole($roleId)
    {
        // Check if role exists
        $role = $this->getRole($roleId);
        if(empty($role))
            return false;

        // Assign rights from this role to children rôles to minimize impact when deleting a role
        $this->reassignRightsToChildren($role);
        
        // Delete the role
        unset($this->roleList[$roleId]);

        // Delete role references in user list
        foreach($this->userList as &$user)
        {
            $key = array_search($roleId, $user);
            if($key !== false)
                unset($user[$key]);
        }
        
        return true;
    }

    /**
     * Gets a role by its ID.
     *
     * @param  string            $roleId The identifier of the role to get.
     * @return SecurityRole|null         The security role if found, null if not.
     */
    public function getRole($roleId)
    {
        if(isset($this->roleList[$roleId]))
            return $this->roleList[$roleId];
        
        return null;
    }

    /**
     * Assigns a role to an user.
     *
     * @param  string  $user   The user to assign a role to. If it doesn't exist in the database, it will be created.
     * @param  string  $roleId The role ID to assign.
     * @return boolean         True if the role has been assigned successfully, false if not (the role hasn't been found).
     */
    public function assignRole($user, $roleId)
    {
        if(!isset($this->userList[$user]))
            $this->userList[$user] = [];
        
        // Check if the role exists
        if(!$this->getRole($roleId))
            return false;
        
        if(!in_array($roleId, $this->userList[$user]))
            $this->userList[$user][] = $roleId;
        
        return true;
    }

    public function revokeRole($user, $roleId)
    {
        if(isset($this->userList[$user]))
        {
            if(!$this->getRole($roleId))
                return false;
            
            if(in_array($roleId, $this->userList[$user]))
            {
                $key = array_search($roleId, $this->userList[$user]);
                unset($this->userList[$user][$key]);
            }
        }

        return true;
    }

    public function revokeAllRoles($user)
    {
        if(isset($this->userList[$user]))
            $this->userList[$user] = [];
        
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
        if(!empty($userRoles))
        {
            // Check all the roles from the user for the right
            foreach($userRoles as $role)
            {
                if($this->roleList[$role]->hasRight($right))
                    return true;
            }
        }

        // Check default roles for the right too
        $defaultRoles = $this->getDefaultRolesList();
        foreach($defaultRoles as $defaultRole)
        {
            if($this->roleList[$defaultRole]->hasRight($right))
                return true;
        }

        // No role allows the right
        return false;
    }

    /**
     * Gets the roles from an user
     * @param  string     $user The username to get the roles of.
     * @return array|null       An array containing the roles of the user, or NULL if the user hasn't been found.
     */
    public function getUserRoles($user)
    {
        foreach($this->userList as $userName => $userRoles)
        {
            if($userName == $user)
                return $userRoles;
        }

        return null;
    }

    /**
     * Gets the roles that are marked as default.
     * 
     * @return array The list of default roles.
     */
    public function getDefaultRolesList()
    {
        $roleList = [];

        foreach($this->roleList as $role)
        {
            if($role->isDefault())
                $roleList[] = $role->getId();
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
        if(isset($this->userList[$username]))
            return $this->userList[$username];
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
        foreach($this->roleList as $role)
        {
            if(!empty($role->getParent()) && $role->getParent()->getId() == $parentRole->getId())
            {
                //Reassign parent role's parent role (i.e. grandparent role) to child, or null if it's null
                $role->setParent($parentRole->getParent() ?? null);

                // Assign rights defined in this role to children, if they don't have roles that override them
                $role->replaceRights(array_merge($parentRoleRights, $role->getRights()));
            }
        }
    }
}
