<?php

namespace HedgeBot\Core\Store;

use HedgeBot\Core\Store\Formatter\FormatterInterface;
use HedgeBot\Core\Store\Formatter\TextFormatter;

/**
 * Class Store
 * The Store is a way to share internal live data between plugins and more generally different parts of the bot.
 * Each part that does want to share data have to register a class implementing the StoreSource interface,
 * using the Store::registerSource() method. Then, classes who want it can access the store data using the
 * Store::getData() method. Thhe store will then ask all sources to give their data and transfer them back to the
 * calling method.
 *
 * When calling the store for data, queriers have to give the channel for which the data is needed, since that'll
 * help data sources narrow down the data to send back. If the data is not tied to a channel in particular, they
 * can call the getData() method without any parameter, but the channel-tied data might not be returned, or may
 * be returned in a different manner (for example an array with all the different channels) to the discretion of
 * the implementing data source. Also, the keys of the data structure returned by the sources should respect the
 * PHP variable naming constraints (like if you were naming variables), as defined by the following regexp:
 * `[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*`
 *
 * Also, there are some wrapper classes that can do additional work over that, for example the StoreTextFormatter,
 * which can parse a template string and inject data in it.
 *
 * @package HedgeBot\Core\Store
 */
class Store
{
    /** @var array The source list for this store */
    protected $sources;
    /** @var array Formatter list for this store */
    protected $formatters;

    /**
     * Constructor. Initializes the store components and registers default formatters.
     */
    public function __construct()
    {
        $this->sources = [];
        $this->formatters = [];

        $this->registerFormatter(new TextFormatter($this));
    }

    /**
     * Registers a store source.
     *
     * @param StoreSourceInterface $source The source to register.
     * @return bool True if the source has been successfully registered, false if not.
     */
    public function registerSource(StoreSourceInterface $source)
    {
        if (in_array($source, $this->sources)) {
            return false;
        }

        $this->sources[] = $source;
        return true;
    }

    /**
     * @param StoreSourceInterface $source
     * @return bool
     */
    public function unregisterSource(StoreSourceInterface $source)
    {
        if (!in_array($source, $this->sources)) {
            return false;
        }

        $this->sources = array_filter($this->sources, function ($element) use ($source) {
            return $element != $source;
        });

        return true;
    }

    /**
     * Registers a formatter.
     *
     * @param FormatterInterface $formatter The formatter.
     * @return bool True if the formatter has been registered correctly, false if not.
     */
    public function registerFormatter(FormatterInterface $formatter)
    {
        $formatterName = $formatter::getName();
        if (isset($this->formatters[$formatterName])) {
            return false;
        }

        $this->formatters[$formatterName] = $formatter;
        return true;
    }

    /**
     * Gets a formatter by its name.
     *
     * @param string $name The formatter name.
     *
     * @return FormatterInterface|null The formatter if found, null otherwise.
     */
    public function getFormatter($name)
    {
        if (isset($this->formatters[$name])) {
            return $this->formatters[$name];
        }

        return null;
    }

    /**
     * Unregisters a formatter.
     *
     * @param mixed $formatter The formatter, can either be its name or directly the instance to unregister.
     *
     * @return bool True if the formatter has been unregistered correctly, false if not.
     */
    public function unregisterFormatter($formatter)
    {
        if (is_object($formatter) && $formatter instanceof FormatterInterface) {
            $formatterName = $formatter::getName();
        } else {
            $formatterName = $formatter;
        }

        if (!isset($this->formatters[$formatterName])) {
            return false;
        }

        unset($this->formatters[$formatterName]);
        return true;
    }

    /**
     * Gets the data in the store.
     * This methods will query all the data sources for their data, and then return the resulting store.
     *
     * @param string $channel The channel the data is tied to.
     * @param string $sourceNamespaceRestraint Restraint data to a specific source namespace. That way, only the source
     *                                         corresponding to that namespace will be loaded.
     * @param bool   $simulateData Set this to true to ask the sources to return data even if the source is not supposed
     *                             to return data at the current state. Useful to allow users to configure displays and
     *                             everything with sample data and have previews.
     * @param array $simulateContext A context for simulation, to ask the store for a more specific dataset than using the 
     *                               channel only as specification. 
     * 
     * @return array The store as an array.
     */
    public function getData($channel = null, $sourceNamespaceRestraint = null, $simulateData = false, $simulateContext = null)
    {
        $store = [];

        foreach ($this->sources as $source) {
            $sourceNamespace = $source::getSourceNamespace();

            // Ignore if the source namespace has been given but doesn't match
            if (!empty($sourceNamespaceRestraint) && $sourceNamespace != $sourceNamespaceRestraint) {
                continue;
            }

            $store[$sourceNamespace] = $source->provideStoreData($channel, $simulateData, $simulateContext);
        }

        return $store;
    }
}
