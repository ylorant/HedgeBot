<?php
namespace HedgeBot\Core\Console\Security;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use HedgeBot\Core\Data\IniFileProvider;
use HedgeBot\Core\Data\ObjectAccess;
use HedgeBot\Core\HedgeBot;
use RuntimeException;

abstract class SecurityCommand extends Command
{
    protected $storage;
    protected $data;
    protected static $hedgebot;

    public function __construct()
    {
        parent::__construct();

        if(empty(self::$hedgebot))
            self::$hedgebot = new HedgeBot();
    }

    public function configure()
    {
        $this->addOption('config', 'c', InputOption::VALUE_REQUIRED, "Specifies the location of storage configuration");
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $configDir = $input->getOption('config') ?? HedgeBot::DEFAULT_CONFIG_DIR;

		$fileProvider = new IniFileProvider();
		$this->config = new ObjectAccess($fileProvider);
        $connected = $fileProvider->connect($configDir);

        if(!$connected)
            throw new RuntimeException("Cannot connect to storage definition at: ". $configDir);

		$dataStorage = $this->config->storage->data;

		$storageLoaded = self::$hedgebot->loadStorage($this->data, $dataStorage);
		if(!$storageLoaded)
            throw new RuntimeException("Cannot connect to data storage.");
        
        $this->data = $this->data->getProvider();
    }

    public function getDataStorage()
    {
        return $this->data;
    }
}
