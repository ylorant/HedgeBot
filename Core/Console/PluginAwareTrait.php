<?php
namespace HedgeBot\Core\Console;

use HedgeBot\Core\Plugins\Plugin;

trait PluginAwareTrait
{
    protected $plugin;

    public function setPlugin(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function getPlugin()
    {
        return $this->plugin;
    }
}