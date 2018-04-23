<?php

namespace HedgeBot\Core\Tikal\Endpoint;

use HedgeBot\Core\API\Twitch\Auth;


class TwitchEndpoint
{
    /**
     * Gets the client ID that the authentication manager uses for the app.
     *
     * @return string The client ID.
     */
    public function getClientID()
    {
        return Auth::getClientID();
    }

    /**
     * Gets the client secret that the authentication manager uses for the app.
     *
     * @return string The client secret.
     */
    public function getClientSecret()
    {
        return Auth::getClientSecret();
    }

    /**
     * Gets the access tokens defined in the authentication manager.
     *
     * @return array The access tokens in an associative array with the form channel => token.
     */
    public function getAccessTokens()
    {
        return Auth::getAccessTokens();
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
        return Auth::getAccessToken($channel);
    }

    /**
     * Adds an access token on the bot. A token can be added only if its channel isn't already in the token list.
     *
     * @param string $channel The channel that this token will be bound to.
     * @param string $token The access token.
     *
     * @return bool True if the token has been added successfully, false if not (mainly, it means that there is already
     *              a matching channel registered).
     */
    public function addAccessToken($channel, $token)
    {
        $accessTokens = Auth::getAccessTokens();

        if (isset($accessTokens[strtolower($channel)])) {
            return false;
        }

        Auth::setAccessToken($channel, $token);
        return true;
    }

    /**
     * Removes an access token by its channel.
     *
     * @param string $channel The channel to remove the token from.
     */
    public function removeAccessToken($channel)
    {
        return Auth::removeAccessToken($channel);
    }
}