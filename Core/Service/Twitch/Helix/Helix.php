<?php

namespace HedgeBot\Core\Service\Twitch\Helix;

use HedgeBot\Core\Service\Twitch\AuthManager;

/**
 * Class Helix
 * @package HedgeBot\Core\Service\Twitch\Helix
 */
class Helix
{
    /** @var AuthManager The authentication manager this client will use. */
    protected $authManager;

    /**
     * Constructor.
     *
     * @param AuthManager $authManager The authentication manager to use throughout the calls to the API.
     */
    public function __construct(AuthManager $authManager)
    {
        $this->authManager;
    }
}
