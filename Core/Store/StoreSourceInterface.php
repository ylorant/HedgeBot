<?php

namespace HedgeBot\Core\Store;

/**
 * Interface defining what a store source should look like.
 * A store source is a component that will provide data to the data store.
 */
interface StoreSourceInterface
{
    /**
     * This method should return the data provided by the implementing class to the store, as an array.
     * The data can be tied to a channel, then the parameter $channel should be used to know what data to choose when returning it.
     *
     * @param string $channel The channel to restrict the data.
     *
     * @return array The data that will be provided to the store.
     */
    public function provideStoreData($channel = null);

    /**
     * The implementing method should return the namespace that'll be used for the data given by this source.
     * Of course the namespace must be unique throughout the bot.
     *
     * @return string The namespace for the source.
     */
    public static function getSourceNamespace();
}