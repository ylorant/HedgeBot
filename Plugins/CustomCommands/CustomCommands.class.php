<?php
class PluginCustomCommands extends Plugin
{
	private $commands = array('Commands' => array());
	
	public function init()
	{
		$this->loadCommands();
	}
	
	public function ServerPrivmsg($cmd)
	{
		$message = $cmd['message'];
		if($message[0] == '!')
		{
			$message = explode(' ', $message);
			$command = substr($message[0], 1);
			
			if(isset($this->commands['Commands'][$command]))
				return IRC::message($cmd['channel'], $this->commands['Commands'][$command]);
		}
	}
	
	public function CommandAdd($param, $args)
	{
		if(!$param['moderator'])
			return;
		
		if(count($args) < 2)
			return IRC::message($param['channel'], "Insufficient parameters.");
		
		$newcommand = array_shift($args);
		$newcommand = $newcommand[0] == '!' ? substr($newcommand, 1) : $newcommand;
		$message = join(' ', $args);
		
		if(!empty($this->commands['Commands'][$newcommand]))
			return IRC::message($param['channel'], "A command with this name already exists. Try again.");
		
		$this->commands['Commands'][$newcommand] = $message;
		$this->saveCommands();
		return IRC::message($param['channel'], "New message for command !". $newcommand. " registered.");
	}
	
	public function CommandDelete($param, $args)
	{
		if(!$param['moderator'])
			return;
		
		if(count($args) == 0)
			return IRC::message($param['channel'], "Insufficient parameters.");
		
		$newcommand = array_shift($args);
		$newcommand = $newcommand[0] == '!' ? substr($newcommand, 1) : $newcommand;
		
		if(empty($this->commands['Commands'][$newcommand]))
			return IRC::message($param['channel'], "This command does not exist. Try again.");
		
		unset($this->commands['Commands'][$newcommand]);
		$this->saveCommands();
		return IRC::message($param['channel'], "Command deleted.");
	}
	

	public function loadCommands()
	{
		$this->commands = HedgeBot::parseINIStringRecursive(file_get_contents($this->config['File']));
	}
	
	public function saveCommands()
	{
		file_put_contents($this->config['File'], HedgeBot::generateINIStringRecursive($this->commands));
	}
}

$this->addPluginData(array(
'name' => 'customcommands',
'className' => 'PluginCustomCommands',
'display' => 'Twitch chat Custom commands plugin',
'dependencies' => array(),
'autoload' => TRUE));
