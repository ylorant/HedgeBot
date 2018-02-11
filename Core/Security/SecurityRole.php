<?php
namespace HedgeBot\Core\Security;

use HedgeBot\Core\Security\SecurityRole;
use HedgeBot\Core\API\Security;
use InvalidArgumentException;

/**
 * Security Role container. Holds all the data related to a role in the security system.
 * A single parent/child inheritance system is available for the roles, meaning that a role can derive
 * from another role. In that case, all the rights from the parent role will be derived to the child role.
 * Then, the child role will be able to override rights.
 */
class SecurityRole
{
    /** @var string Role id */
    protected $id;

    /** @var string Role name */
    protected $name;

    /** @var SecurityRole Parent role. This role will inherit all the parent role's rights */
    protected $parent;

    /** @var array List of rights in this role. */
    protected $rights;

    /** @var bool Wether the role is a default role or not */
    protected $default;

    /**
     * Constructor.
     * 
     * @constructor
     * @param       string $id The role ID.
     * 
     * @throws InvalidArgumentException
     */
    public function __construct($id)
    {
        // Check if the given role id is a valid id or the role doesn't already exist
        if(!self::checkId($id))
            throw new InvalidArgumentException("Role ID is invalid");

        $this->id = $id;
        $this->rights = [];
        $this->parent = null;
        $this->default = false;
    }

    /**
     * Gets the role ID.
     * 
     * @return string The role ID.
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Gets the role name.
     * 
     * @return string The role name.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the role name.
     * 
     * @param string $name The role name to set.
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Gets the parent role.
     * 
     * @return SecurityRole $role|null The parent role.
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Sets the parent role.
     * 
     * @param SecurityRole|null $parent The parent role.
     */
    public function setParent($parent)
    {
        if($parent instanceof SecurityRole)
            $this->parent = $parent;
        elseif(is_string($parent))
        {
            $role = Security::getRole($parent);
            if(!empty($role))
                $this->parent = $role;
        }
        elseif(is_null($parent))
            $this->parent = null;
    }

    /**
     * Gets the default status of the role.
     * @return bool True if the role is a default role, false if not.
     */
    public function isDefault()
    {
        return $this->default;
    }

    /**
     * Sets the default nature of the role.
     * @param bool $default True if the role is a default role, false if not.
     */
    public function setDefault($default)
    {
        $this->default = (bool) $default;
    }

    public function getRights()
    {
        return $this->rights;
    }

    /**
     * Gets the inherited rights of this role.
     */
    public function getInheritedRights()
    {
        if(empty($this->parent))
            return [];
        
        return array_merge($this->parent->getRights(), $this->parent->getInheritedRights());
    }

    /**
     * Adds a right to the role.
     *
     * @param string $right   The right name.
     * @param bool   $granted True if the right is granted, false if it is explicitely denied. Default to true.
     * @return void
     */
    public function setRight($right, $granted = true)
    {
        $this->rights[$right] = $granted;
    }

    /**
     * Unsets a right from the role.
     *
     * @param  string $right The right to unset
     * @return void
     */
    public function unsetRight($right)
    {
        if(isset($this->rights[$right]))
            unset($this->rights[$right]);
    }

    /**
     * Replaces the rights in this role by the ones given in parameter.
     * 
     * @param array $newRights The new rights to assign to this role.
     */
    public function replaceRights(array $newRights)
    {
        $this->rights = $newRights;
    }

    /**
     * Clears the right list of this role.
     */
    public function clearRights()
    {
        $this->rights = [];
    }

    /**
     * Serializes the role into an array.
     * @return array The role representation as an array.
     */
    public function toArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'parent' => $this->parent ? $this->parent->getId() : null,
            'default' => $this->default,
            'rights' => $this->rights
        ];
    }

    /** 
     * Unserializes a role from an array.
     * @param  array        $data The data to unserialize.
     * @return SecurityRole       The unserialized role.
     */
    public static function fromArray(array $data)
    {
        if(!isset($data['id']))
            return null;

        $role = new SecurityRole($data['id']);

        if(!empty($data['name']))
            $role->name = $data['name'];

        if(!empty($data['rights']))
            $role->rights = $data['rights'];
        
        if(!empty($data['parent']))
            $role->setParent($data['parent']);

        if(!empty($data['default']))
            $role->setDefault($data['default']);
        
        return $role;
    }

    /**
     * Checks if this role has a particular right.
     * It'll bubble up on the parent role if there is one.
     * @param  string  $right The right to check.
     * @return boolean        True if the role has the asked right, false otherwise.
     */
    public function hasRight($right)
    {
        foreach($this->rights as $rightName => $allowed)
        {
            // If the right is present, return its value
            if($rightName == $right)
                return $allowed;
        }

        // Bubble up if there's a parent
        if(!empty($this->parent))
            return $this->parent->hasRight($right);

        // If we don't find anything at all, we return denied as default
        return false;
    }

    /**
     * Normalizes a role name to an ID representation.
     * Based off this SO thread: https://stackoverflow.com/questions/2955251/php-function-to-make-slug-url-string
     *
     * @param  string $name The name to normalize.
     * @return string       The normalized name.
     */
    public static function normalizeId($name)
    {
        // Replace non-letter characters by _
        $text = preg_replace('#[^\pL\d]+#u', '_', $name);

        // transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // remove unwanted character leftovers
        $text = preg_replace('#[^_\w]+#', '', $text);
        $text = trim($text, '_');

        // remove duplicate _
        $text = preg_replace('#_+#', '_', $text);

        $text = strtolower($text);

        if (empty($text))
            return null;

        return $text;
    }

    /**
     * Checks if the ID is a valid role ID syntax.
     * 
     * @param string $id The ID to check.
     * @return bool True if the ID is a valid ID, false if not.
     */
    public static function checkId($id)
    {
        return preg_match('#^[a-z0-9_]+$#', $id);
    }
}
