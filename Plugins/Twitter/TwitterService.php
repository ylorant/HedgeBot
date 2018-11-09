<?php
namespace HedgeBot\Plugins\Twitter;

use HedgeBot\Core\Data\ObjectAccess;
use Codebird\Codebird;

/**
 * Class TwitterService
 * Handles (and abstracts) communication between the plugin and the underlying Twitter library, allowing to change
 * the library if needed without having to recode all the plugin.
 * 
 * @package Hedgebot\Plugins\Twitter
 */
class TwitterService
{
    /** @var Codebird Twitter client */
    protected $client;
    /** @var string The callback URL for the app */
    protected $callbackUrl;
    /** @var array Access tokens for each user on Twitter */
    protected $accessTokens;

    /** 
     * Constructor.
     * 
     * @param string $consumerKey The consumer key for Twitter's API.
     * @param string $consumerSecret The consumer secret for Twitter's API.
     * @param ObjectAccess $tokenStorage The storage medium (as an object-access interface) for tokens.
     */
    public function __construct($consumerKey, $consumerSecret, $callbackUrl, ObjectAccess $tokenStorage)
    {
        Codebird::setConsumerKey($consumerKey, $consumerSecret);
        $this->client = Codebird::getInstance();
        $this->callbackUrl = $callbackUrl;
        $this->tokenStorage = $tokenStorage;

        $this->reloadTokens();
    }
    
    /// OAUTH WORKFLOW ///

    /**
     * Gets the OAuth authorize URL for the app from Twitter.
     * 
     * @param string $callbackUrl The OAuth callback URL defined in the app settings on Twitter.
     * 
     * @return string The OAuth authorize URL.
     */
    public function getAuthorizeUrl()
    {
        $tokenReply = $this->client->oauth_requestToken([
            'oauth_callback' => $this->callbackUrl
        ]);
        
        $requestToken = [
            'token' => $tokenReply->oauth_token,
            'secret' => $tokenReply->oauth_token_secret
        ];

        $this->client->setToken($requestToken['token'], $requestToken['secret']);

        return $this->client->oauth_authorize();
    }
    
    /**
     * Creates an access token from the given oauth verifier, queries the username from Twitter, and stores it for
     * future access.
     * 
     * @param string $oauthVerifier The OAuth verifier given in the authorize callback URL.
     */
    public function createAccessToken($oauthVerifier)
    {
        // Get the token pair
        $accessReply = $this->client->oauth_accessToken([
            'oauth_verifier' => $oauthVerifier
        ]);

        // If the token is empty, assume there's an error
        if(!$accessReply) {
            return false;
        }

        // Set the new token for the client
        $this->client->setToken($accessReply->oauth_token, $accessReply->oauth_token_secret);

        // Get the user credential info, to link the token to the twitter username
        $credentials = $this->client->account_settings();
        if(!empty($credentials->errors)) {
            return false;
        }
        
        $this->accessTokens[$credentials->screen_name] = [
            'token' => $accessReply->oauth_token,
            'secret' => $accessReply->oauth_token_secret
        ];

        $this->saveTokens();
        return true;
    }

    /**
     * Sets the current account that will be used for operations.
     * 
     * @param string $account The account name to use.
     * 
     * @return bool True if the account has been set, false if not (mainly the account doesn't exist).
     */
    public function setCurrentAccount($account)
    {
        // Check the account existence
        if(!isset($this->accessTokens[$account])) {
            return false;
        }

        $this->client->setToken($this->accessTokens[$account]['token'], $this->accessTokens[$account]['secret']);

        return true;
    }

    /// TOKEN DB MANAGEMENT ///

    /**
     * Checks if an account has an access token defined in the token list.
     * 
     * @param string $account The account to check the token of.
     * 
     * @return bool true if the token exists, false if not.
     */
    public function hasToken($account)
    {
        return isset($this->accessTokens[$account]);
    }

    /**
     * Gets the tokens registered into the service.
     * 
     * @return array The list of tokens, indexed by channel.
     */
    public function getTokens()
    {
        return $this->accessTokens;
    }

    /**
     * Gets the list of accounts that have tokens in the token list.
     * 
     * @return array An array of all the tokens in the token list.
     */
    public function getTokenAccounts()
    {
        return array_keys($this->accessTokens);
    }

    /**
     * Saves the tokens into the storage.
     */
    public function saveTokens()
    {
        $this->tokenStorage->accessTokens = $this->accessTokens;
    }

    /**
     * Loads the tokens from the storage.
     */
    public function reloadTokens()
    {
        $this->accessTokens = $this->tokenStorage->accessTokens->toArray();
    }

    /// POSTING METHODS ///
    
    /**
     * Uploads a media to Twitter.
     * 
     * @param string $mediaUrl The URL of the media to be embedded. Can be a local file or an online media.
     * 
     * @return string|bool The uploaded media URL, or False if an error occured.
     */
    public function uploadMedia($mediaUrl)
    {
        $reply = $this->client->media_upload([
            'media' => $mediaUrl
        ]);

        if(empty($reply->media_id_string)) {
            return false;
        }

        return $reply->media_id_string;
    }

    /**
     * Posts a tweet.
     * 
     * @param string $account The account name to post with.
     * @param string $content The tweet content.
     * @param array  $media   The list of media IDs to join to the tweet.
     * 
     * @return bool True if the tweet was successfully sent, false if not.
     */
    public function tweet($content, array $media)
    {
        $params = [
            'status' => $content,
            'media_ids' => join(',', $media)
        ];

        return $this->client->statuses_update($params);
    }
}