<?php
namespace HedgeBot\Core\Service\Twitch;

use HedgeBot\Core\Data\Provider as DataProvider;

/**
 * Twitch Authentication manager.
 * This class manages all authentication related operations on the Twitch API, which is mainly storing access.
 */
class AuthManager
{
    /** @var string The client App ID that'll be used to access Twitch. */
    protected $clientID;
    /** @var array The list of user access tokens that can be used to interact with channels. Every access token is tied to a channel. */
    protected $accessTokens;
    /** @var DataProvider The data provider where the data will be saved */
    protected $dataProvider;

    public function __construct($clientID, DataProvider $dataProvider)
    {
        $this->clientID = $clientID;
        $this->dataProvider = $dataProvider;
        $this->accessTokens = [];

        $this->loadFromStorage();
    }

    /**
     * Gets the app client ID.
     * 
     * @return string The app's client ID.
     */
    public function getClientID()
    {
        return $this->clientID;
    }

    /**
     * Gets the access token list.
     * 
     * @return array The access token list.
     */
    public function getAccessTokens()
    {
        return $this->accessTokens;
    }

    public function hasAccessToken($channel)
    {
        return !empty($this->accessTokens[$channel]);
    }

    /**
     * Gets an access token from the channel name.
     * 
     * @param string $channel The channel to get the access token of.
     * 
     * @return string|nulll The access token if found, or null if not.
     */
    public function getAccessToken($channel)
    {
        if(!empty($this->accessTokens[$channel]))
            return $this->accessTokens[$channel];
        
        return null;
    }

    /**
     * Sets the access tokens.
     * 
     * @param array $accessTokens The access token list to set.
     */
    public function setAccessTokens(array $accessTokens)
    {
        $this->accessTokens = $accessTokens;
    }

    /**
     * Adds an access token to the token list.
     * 
     * @param string $channel The channel for which the access token will be used.
     * @param string $accessToken The access token.
     */
    public function addAccessToken($channel, $accessToken)
    {
        $this->accessTokens[$channel] = $accessToken;
    }

    /**
     * Removes the access token corresponding to one channel.
     * 
     * @param string $channel The channel from which to remove the access token.
     */
    public function removeAccessToken($channel)
    {
        if(isset($this->accessTokens[$channel]))
            unset($this->accessTokens[$channel]);
    }

    /**
     * Loads the Twitch auth data from the storage.
     */
    public function loadFromStorage()
    {
        $this->accessTokens = $this->dataProvider->get('twitch.auth');
    }

    /**
     * Saves the current Twitch auth data to the storage.
     */
    public function saveToStorage()
    {
        $this->dataProvider->set('twitch.auth', $this->accessTokens);
    }
}