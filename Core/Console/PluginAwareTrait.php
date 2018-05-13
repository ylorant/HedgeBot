<?php

namespace HedgeBot\Core\Console;

use HedgeBot\Core\Plugins\Plugin;

/**
 * Trait PluginAwareTrait
 * @package HedgeBot\Core\Console
 */
trait PluginAwareTrait
{
    protected $plugin;

    /**
     * @param Plugin $plugin
     */
    public function setPlugin(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * @return mixed
     */
    public function getPlugin()
    {
        return $this->plugin;
    }
}
