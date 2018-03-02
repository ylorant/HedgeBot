<?php
namespace HedgeBot\Core\Service\Twitch\Services;

use HedgeBot\Core\Twitch\Kraken;
use stdClass;
use DateTime;

/**
 * Twitch API service: channels info handler.
 */
class Channels extends Service
{
    const SERVICE_NAME = "channels";

    public function __construct(Kraken $kraken)
    {
        parent::__construct($kraken);
        $this->servicepath = '/channels';
    }

    /**
     * Fetch informations for a given channel. Basically executes the API call to get channel data from Twitch and returns it.
     *
     * @param $channel The channel name.
     * @return Available information as stdClass.
     *
     * @see https://github.com/justintv/Twitch-API/blob/master/v3_resources/channels.md#get-channelschannel
     */
    public function info($channel)
    {
        return $this->query(Kraken::QUERY_TYPE_GET, "/$channel");
    }

    /**
     * Fetch the list of followers for a channel. Since Twitch's API doesn't allow to get the whole list of followers in one go,
     * the limitation is present there too. Also, since Twitch supports cursor-based pagination instead of regular one,
     * you'll have to get each page one by one (the cursor for the next page is given in each result).
     *
     * @param $channel The channel name.
     * @param $parameters The parameters for the list to retrieve. No parameter is mandatory. Available parameters:
     *                    - limit: The limit for the element count in the list. Follows Twitch's limit of maximum 100 followers.
     *                    - start; Where to start from. Expects a cursor, refer to the Twitch API for more info.
     *                    - order: The order in which the results will be presented, desc or asc. Order will be by follow time.
     *                    - detailed info: boolean indicating whether to get extended info for each user or only the nickname.
     * @return An object containing the resulting list, along with other useful data (count, cursor). If detailed info is requested,
     *         then each user is listed in an object. If not, only the nickname as a string will be returned in the list.
     *
     * @see https://github.com/justintv/Twitch-API/blob/master/v3_resources/follows.md#get-channelschannelfollows
     */
    public function followers($channel, array $parameters = [])
    {
        $queryParameters = array(
            'limit' => !empty($parameters['limit']) ? $parameters['limit'] : '',
            'cursor' => !empty($parameters['start']) ? $parameters['start'] : '',
            'direction' => !empty($parameters['order']) ? $parameters['order'] : '',
        );

        $queryParameters = array_filter($queryParameters);

        $userList = $this->query(Kraken::QUERY_TYPE_GET, "/$channel/follows");

        $return = new stdClass();
        $return->total = $userList->_total;
        $return->cursor = $userList->_cursor;
        $return->list = array();

        // Handling the return format
        foreach($userList->follows as $user)
        {
            // If needed, fetch a lot of info
            if(!empty($parameters['detailed_info']))
            {
                $userObject = new stdClass();
                $userObject->name = $user->user->name;
                $userObject->followDate = new DateTime($user->created_at);
                $userObject->registrationDate = new DateTime($user->user->created_at);
                $userObject->displayName = $user->user->display_name;
                $userObject->type = $user->user->type;
                $userObject->logo = $user->user->logo;
                $userObject->bio = $user->user->bio;
                $userObject->hasNotifications = $user->notifications;
            }
            else // Return only the nickname by default
                $return->list[] = $user->user->name;
        }

        return $return;
    }
}
