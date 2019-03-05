<?php
namespace HedgeBot\Plugins\Twitter;

use HedgeBot\Plugins\Twitter\Entity\ScheduledTweet;


class TwitterEndpoint
{
    /** @var Twitter The plugin reference */
    protected $plugin;

    /**
     * Constructor. Initializes the endpoint.
     * 
     * @param Twitter $plugin The Twitter plugin reference.
     */
    public function __construct(Twitter $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Gets the authorize URL from the service.
     * 
     * @see TwitterService::getAuthorizeUrl()
     */
    public function getAuthorizeUrl()
    {
        return $this->plugin->getService()->getAuthorizeUrl();
    }
    
    /**
     * Gets the list of Twitter access tokens for each account.
     * 
     * @see TwitterService::getAccessTokens()
     */
    public function getAccessTokens()
    {
        return $this->plugin->getService()->getAccessTokens();
    }

    /**
     * Gets the list of accounts that have access tokens registered.
     * 
     * @see TwitterService::getAccessTokenAccounts()
     */
    public function getAccessTokenAccounts()
    {
        return $this->plugin->getService()->getAccessTokenAccounts();
    }

    /**
     * Creates an access token from an oauth verifier.
     * 
     * @see TwitterService::createAccessToken()
     */
    public function createAccessToken($oauthVerifier)
    {
        return $this->plugin->getService()->createAccessToken($oauthVerifier);
    }

    /**
     * Deletes a registered access token.
     * 
     * @see TwitterService::deleteAccessToken()
     */
    public function deleteAccessToken($account)
    {
        return $this->plugin->getService()->deleteAccessToken($account);
    }

    /**
     * Gets the scheduled tweets.
     * 
     * @see Twitter::getScheduledTweets()
     */
    public function getScheduledTweets()
    {
        return $this->plugin->getScheduledTweets();
    }

    /**
     * Gets a scheduled tweet by its ID.
     * 
     * @see Twitter::getScheduledTweet()
     */
    public function getScheduledTweet($tweetId)
    {
        return $this->plugin->getScheduledTweet($tweetId);
    }

    /**
     * Saves a scheduled tweet.
     * 
     * @param array $tweet The tweet data as an array.
     * 
     * @see Twitter::scheduleTweet()
     * @see ScheduledTweet::fromArray()
     */
    public function saveScheduledTweet($tweet)
    {
        return $this->plugin->scheduleTweet(ScheduledTweet::fromArray((array) $tweet));
    }
}