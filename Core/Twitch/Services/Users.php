<?php
namespace HedgeBot\Core\Twitch\Services;

use HedgeBot\Core\Twitch\Kraken;
use stdClass;
use DateTime;

class Users extends Service
{
    const SERVICE_NAME = "users";

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
}
