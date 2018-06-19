<?php

namespace HedgeBot\Core\Tikal\Endpoint;

use HedgeBot\Core\API\Twitch;

/**
 * Class TwitchEndpoint
 * @package HedgeBot\Core\Tikal\Endpoint
 */
class TwitchEndpoint
{
    /**
     * Gets the client ID that the authentication manager uses for the app.
     *
     * @return string The client ID.
     */
    public function getClientID()
    {
        return Twitch::getClientID();
    }

    /**
     * Gets the client secret that the authentication manager uses for the app.
     *
     * @return string The client secret.
     */
    public function getClientSecret()
    {
        return Twitch::getClientSecret();
    }

    /**
     * Gets the access tokens defined in the authentication manager.
     *
     * @return array The access tokens in an associative array with the form channel => token.
     */
    public function getAccessTokens()
    {
        return Twitch::getAllTokens();
    }

    /**
     * Gets a specific access token from the auth manager.
     *
     * @param string $channel The channel to get the access token of.
     *
     * @return string|null The token if found or null if not found.
     */
    public function getAccessToken($channel)
    {
        if (empty(Twitch::getAccessToken($channel))) {
            return null;
        }

        return [
            'token' => Twitch::getAccessToken($channel),
            'refresh' => Twitch::getRefreshToken($channel)
        ];
    }

    /**
     * Adds an access token on the bot. A token can be added only if its channel isn't already in the token list.
     *
     * @param string $channel The channel that this token will be bound to.
     * @param string $token The access token.
     * @param string $refresh The refresh token, used when the main token is expired.
     *
     * @return bool True if the token has been added successfully, false if not (mainly, it means that there is already
     *              a matching channel registered).
     */
    public function addAccessToken($channel, $token, $refresh)
    {
        $channel = strtolower($channel);

        if (!empty(Twitch::getAccessToken($channel))) {
            return false;
        }

        Twitch::setAccessToken($channel, $token);
        Twitch::setRefreshToken($channel, $refresh);
        return true;
    }

    /**
     * Removes an access token by its channel.
     *
     * @param $channel The channel to remove the token from.
     * @return mixed
     */
    public function removeAccessToken($channel)
    {
        return Twitch::removeAccessToken($channel);
    }
}
