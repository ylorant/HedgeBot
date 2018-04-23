<?php

namespace HedgeBot\Core\Service\Twitch\Kraken\Services;

use HedgeBot\Core\Service\Twitch\Kraken\Kraken;
use stdClass;
use DateTime;

class Users extends Service
{
    const SERVICE_NAME = "users";

    /** @var array  user names => user IDs match table cache, to avoir duplicating requests when doing operations on the same channel multiple times */
    protected static $userIdCache = [];

    public function __construct(Kraken $kraken)
    {
        parent::__construct($kraken);
        $this->servicepath = '/users';
    }

    /**
     * Gets info on an user.
     *
     * @param $nickname The nickname of the user.
     * @return An array containing the user info.
     *
     * @see https://github.com/justintv/Twitch-API/blob/master/v3_resources/users.md#get-usersuser
     */
    public function info($nickname)
    {
        $user = $this->query(Kraken::QUERY_TYPE_GET, "/$nickname");

        $userObject = new stdClass();
        $userObject->name = $user->name;
        $userObject->registrationDate = new DateTime($user->created_at);
        $userObject->displayName = $user->display_name;
        $userObject->type = $user->type;
        $userObject->bio = $user->bio;

        return $userObject;
    }

    public function getUserId($username)
    {
        if (empty(self::$userIdCache[$username])) {
            $response = $this->query(Kraken::QUERY_TYPE_GET, "", ['login' => $username]);
            $user = reset($response->users);

            self::$userIdCache[$username] = $user->_id;

        }
        return self::$userIdCache[$username];
    }
}
