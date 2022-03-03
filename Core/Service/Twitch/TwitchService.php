<?php
namespace HedgeBot\Core\Service\Twitch;

use TwitchClient\Authentication\TokenProvider;
use HedgeBot\Core\Data\Provider;
use TwitchClient\API\Kraken\Kraken;
use TwitchClient\API\Auth\Authentication;
use TwitchClient\API\Helix\Helix;

/**
 * Twitch service class. Allows to create Twitch clients and holds the token provider functionality.
 * @package HedgeBot\Core\Service\Twitch
 */
class TwitchService implements TokenProvider
{
    /** @var string Client ID */
    protected $clientID = null;
    /** @var string Client secret */
    protected $clientSecret = null;
    /** @var Provider The data provider for the tokens */
    protected $dataProvider = null;

    // Token basepath in the data storage
    const DATA_TOKEN_BASEPATH = 'twitch.auth';
    // 
    const DATA_DEFAULT_TOKEN_BASEPATH = 'twitch.default_auth';
    // Client type name: Kraken
    const CLIENT_TYPE_KRAKEN = 'kraken';
    // Client type name: Authentication
    const CLIENT_TYPE_AUTH = 'auth';
    // Client type name: Helix
    const CLIENT_TYPE_HELIX = 'helix';

    /**
     * Constructor, initializes the service.
     * 
     * @param string $clientID The client ID for the application on Twitch
     * @param string $clientSecret The client secret for the app
     * @param Provider $provider The provider that the service will use to get and store tokens
     */
    public function __construct($clientID, $clientSecret, Provider $provider)
    {
        $this->clientID = $clientID;
        $this->clientSecret = $clientSecret;

        $this->dataProvider = $provider;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientID()
    {
        return $this->clientID;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientSecret()
    {
        return $this->clientSecret;
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessToken($target)
    {
        $token = $this->dataProvider->get(self::DATA_TOKEN_BASEPATH . '.' . $target);

        if(empty($token)) {
            return null;
        }

        return $token['token'];
    }

    /**
     * {@inheritdoc}
     */
    public function getRefreshToken($target)
    {
        $token = $this->dataProvider->get(self::DATA_TOKEN_BASEPATH . '.' . $target);

        if(empty($token)) {
            return null;
        }

        return $token['refresh'];
    }

    /**
     * {@inheritdoc}
     */
    public function setAccessToken($target, $token)
    {
        $tokenObject = $this->dataProvider->get(self::DATA_TOKEN_BASEPATH . '.' . $target);
        if(empty($token)) {
            $tokenObject = [
                'token' => '',
                'refresh' => ''
            ];
        }

        $tokenObject['token'] = $token;

        $this->dataProvider->set(self::DATA_TOKEN_BASEPATH . '.' . $target, $tokenObject);
    }

    /**
     * {@inheritdoc}
     */
    public function setRefreshToken($target, $token)
    {
        $tokenObject = $this->dataProvider->get(self::DATA_TOKEN_BASEPATH . '.' . $target);
        if(empty($token)) {
            $tokenObject = [
                'token' => '',
                'refresh' => ''
            ];
        }

        $tokenObject['refresh'] = $token;

        $this->dataProvider->set(self::DATA_TOKEN_BASEPATH . '.' . $target, $tokenObject);
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultAccessToken()
    {
        $tokenObject = $this->dataProvider->get(self::DATA_DEFAULT_TOKEN_BASEPATH);

        if(empty($tokenObject)) {
            return null;
        }

        return $tokenObject['token'];
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultAccessToken($accessToken)
    {
        $tokenObject = $this->dataProvider->get(self::DATA_DEFAULT_TOKEN_BASEPATH);
        if(empty($accessToken)) {
            $tokenObject = [
                'token' => '',
                'refresh' => ''
            ];
        }

        $tokenObject['token'] = $accessToken;
        $this->dataProvider->set(self::DATA_DEFAULT_TOKEN_BASEPATH, $tokenObject);
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultRefreshToken()
    {
        $tokenObject = $this->dataProvider->get(self::DATA_DEFAULT_TOKEN_BASEPATH);

        if(empty($tokenObject)) {
            return null;
        }

        return $tokenObject['refresh'];
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultRefreshToken($refreshToken)
    {
        $tokenObject = $this->dataProvider->get(self::DATA_DEFAULT_TOKEN_BASEPATH);
        if(empty($tokenObject)) {
            $tokenObject = [
                'token' => '',
                'refresh' => ''
            ];
        }

        $tokenObject['refresh'] = $refreshToken;
        $this->dataProvider->set(self::DATA_DEFAULT_TOKEN_BASEPATH, $tokenObject);
    }

    /**
     * Gets the API client.
     * 
     * @return object The API client.
     */
    public function getClient($clientType = self::CLIENT_TYPE_HELIX)
    {
        switch ($clientType) {
            case self::CLIENT_TYPE_KRAKEN:
                return new Kraken($this);
                // no-break
            
            case self::CLIENT_TYPE_HELIX:
                return new Helix($this);
                // no-break
            
            case self::CLIENT_TYPE_AUTH:
                return new Authentication($this);
                // no-break
        }
    }

    /**
     * Gets all the access token/refresh token pairs that are stored in the data store.
     * 
     * @return array An array containing all tokens.
     */
    public function getAllTokens()
    {
        return $this->dataProvider->get(self::DATA_TOKEN_BASEPATH);
    }

    /**
     * Removes an access token/refresh token pair from the database.
     * 
     * @param string $target The target to remove the tokens of.
     * 
     * @return bool True if the token was removed successfully, false if not (because the target doesn't have a token).
     */
    public function removeAccessToken($target)
    {
        $token = $this->dataProvider->get(self::DATA_TOKEN_BASEPATH . '.' . $target);

        if (empty($token)) {
            return false;
        }

        $this->dataProvider->remove(self::DATA_TOKEN_BASEPATH . '.' . $target);
    }
}