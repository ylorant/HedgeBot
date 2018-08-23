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
     * @param $simulateData Set this to true to ask the store to provide data even if it's not supposed to in its current state.
     * @param $simulateContext Use this to provide a context to ask for specific data on a source.
     */
    public function getStoreData($channel = null, $sourceNamespaceRestraint = null, $simulateData = false, $simulateContext = null)
    {
        return Store::getData($channel, $sourceNamespaceRestraint, $simulateData, (array) $simulateContext);
    }
}