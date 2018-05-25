<?php

namespace HedgeBot\Core\Tikal\Endpoint;

use HedgeBot\Core\API\Store;


/**
 * Store Tikal endpoint, allows clients to access the store from the API.
 */
class StoreEndpoint
{
    /**
     * Gets the current state of the store.
     * 
     * @param $channel The channel of which to get the store.
     * @param $sourceNamespaceRestraint Restraint data to be fetched only from a specific source namesapce in the store.
     */
    public function getStoreData($channel = null, $sourceNamespaceRestraint = null)
    {
        return Store::getData($channel, $sourceNamespaceRestraint);
    }
}