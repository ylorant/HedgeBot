<?php

namespace HedgeBot\Plugins\AutoHost;

/**
 * Class AutoHostEndpoint
 * @package HedgeBot\Plugins\AutoHost
 */
class AutoHostEndpoint
{
    /** @var AutoHost The plugin reference */
    protected $plugin;

    /**
     * AutoHostEndpoint constructor.
     * Initializes the endpoint with the plugin to use as data source.
     *
     * @param AutoHost $plugin
     */
    public function __construct(AutoHost $plugin)
    {
        $this->plugin = $plugin;
    }
}