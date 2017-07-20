<?php
namespace HedgeBot\Core\Tikal\Endpoint;

use HedgeBot\Core\API\Plugin;

class PluginEndpoint
{
    /**
     * Gets the plugin list.
     * 
     * @return array The plugin list as an array.
     */
    public function getList()
    {
        return Plugin::getManager()->getLoadedPlugins();
    }
}