<?php
namespace HedgeBot\Core\Logger;

use HedgeBot\Core\Data\Provider;

/**
 * Logger class. Handles intelligent event logging.
 * @package HedgeBot\Core\Logger
 */
class Logger
{
    /** @var string $archivePath Path where the log archives should be stored. */
    protected $archivePath;
    /** @var array Extra retention rules for specific log categories. */
    protected $retentionRules;
    /** @var array Latest logs, for direct access */
    protected $logs = [];
    /** @var Provider Storage provider for latest logs */
    protected $storage;

    // Default retention time, in hours (one week)
    const DEFAULT_RETENTION_TIME = 24 * 7;

    /**
     * Constructor. Builds the instance up with the given storage.
     * 
     * @param Provider $storage The storage to use to store the log lines.
     * @return self
     */
    public function __construct(Provider $storage, array $config = [])
    {
        $this->archivePath = $config['archivePath'];
        $this->retentionRules = $config['retentionRules'];

        $this->storage = $storage;
    }

    /**
     * Saves a log line.
     * 
     * @param Log $log The log to save.
     * @return bool True if the log succeeded, false if not.
     */
    public function log(Log $log)
    {
        $this->logs[] = $log;
    }
    
    /**
     * Gets the latest logs, filtered either by category or by type.
     * 
     * @param string|null $category The category to look up.
     * @param string|null $type The log type to look up.
     * @param int $count The maximum log count to return. Defaults to 30.
     * @return array The matching array elements.
     */
    public function getLogs(string $category = null, string $type = null, int $count = 30)
    {
        
    }
}