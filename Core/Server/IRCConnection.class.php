<?php
namespace HedgeBot\Core\Server;

use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\API\Server;

class IRCConnection
{
	private $_socket;
	private $_channels = array();
	private $_users = array();
	private $_buffer = array();
	private $_lastTimed = 0;
	private $_lastSend = 0;
	private $_data;
	private $_floodLimit = false;
	private $_lastCommand = 0;

	public function connect($addr, $port)
	{
		$this->_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		socket_connect($this->_socket, $addr, $port);
		socket_set_nonblock($this->_socket);
		$this->_lastSend = 0;
	}

	public function setFloodLimit($value)
	{
		$this->_floodLimit = $value == true;
	}

	public function disconnect()
	{
		fclose($this->_socket);
	}

	public function read()
	{
		$data = socket_read($this->_socket, 1024);

		if(substr($data, -2) == "\r\n")
		{
			$commands = explode("\r\n", $this->_data.$data);
			if(empty($commands[count($commands)-1]))
				array_pop($commands);

			$this->_data = "";

			return $commands;
		}
		else
			$this->_data .= $data;

		return array();
	}

	public function joinChannels($channels)
	{
		$chans = $channels;
		if(is_array($channels))
			$chans = join(',', $channels);
		else
			$channels = explode(',', str_replace(' ', '', $channels));

		$this->_channels = array_merge($this->_channels, $channels);

		str_replace(' ', '', $chans);
		$this->send('JOIN #'.$chans);
	}

	public function joinChannel($chan)
	{
		$this->_channels = array_merge($this->_channels, array($chan));
		$this->send('JOIN #'.$chan);
	}

	public function setNick($nick, $user)
	{
		$this->send('NICK '.$nick);
		$this->send('USER '.$user.' '.$user.' '.$user.' '.$user);
	}

	public function setPassword($password)
	{
		$this->send('PASS '. $password);
	}

	public function message($to, $message)
	{
		$this->send('PRIVMSG #'.$to.' :'.$message);
	}

	public function timedMessage($to, $message, $time)
	{
		$this->send('PRIVMSG #'.$to.' :'.$message, $time);
	}

	public function notice($to, $message)
	{
		$this->send('NOTICE #'.$to.' :'.$message);
	}

	public function kick($from, $who, $reason = '')
	{
		$this->send('KICK #'.$from.' '.$who.' :'.$reason);
	}

	public function action($channel, $message)
	{
		$this->send("PRIVMSG #$channel :ACTION $message");
	}

	public function ping($hostname = NULL)
	{
		$hostname = $hostname ? $hostname : gethostname();
		$this->send("PING :". $hostname);
	}

	public function getChannels()
	{
		return array_keys($this->_channels);
	}

	public function getChannelUsers($channel)
	{
		if(isset($this->_users[$channel]))
			return array_keys($this->_users[$channel]);
		else
			return array();
	}

	public function getChannelRights($channel)
	{
		if(!isset($this->_users[$channel]))
			$this->_users[$channel] = array();

		$return = array('users' => array(), 'voice' => array(), 'operator' => array());
		foreach($this->_users[$channel] as $user => $level)
		{
			switch($level)
			{
				case 'v':
					$return['voice'][] = $user;
					break;
				case 'o':
					$return['operator'][] = $user;
					break;
				default:
					$return['users'][] = $user;
					break;
			}
		}

		return $return;
	}

	public function userMode($user, $mode, $channel = 'all')
	{
		if($channel == 'all')
		{
			foreach($this->_channels as $chan)
			{
				if(in_array($user, $this->getChannelUsers($chan)))
					$this->send('MODE '.$chan.' '.$mode.' '.$user);
			}
		}
		else
			$this->send('MODE #'.$channel.' '.$mode.' '.$user);
	}

	public function giveVoice($user, $channel = 'all')
	{
		$this->userMode($user, '+v', $channel);
	}

	public function takeVoice($user, $channel = 'all')
	{
		$this->userMode($user, '-v', $channel);
	}

	public function giveOp($user, $channel = 'all')
	{
		$this->userMode($user, '+o', $channel);
	}

	public function takeOp($user, $channel = 'all')
	{
		$this->userMode($user, '-o', $channel);
	}

	public function setChannelUsers($channel, $users)
	{
		$list = array();
		foreach($users as $user)
		{
			$nick = substr($user, 1);
			switch($user[0])
			{
				case '@':
					$list[$nick] = 'o';
					break;
				case '+':
					$list[$nick] = 'v';
					break;
				default:
					$list[$user] = '';
			}
		}

		$this->_users[$channel] = $list;
	}

	public function waitPing()
	{
		$continue = true;
		HedgeBot::message("Waiting ping from server...");
		while($continue)
		{
			$this->processBuffer();
			$buffer = $this->read();
			foreach($buffer as $cmd)
			{
				echo '['.Server::getName().'] '.$cmd."\n";
				$cmd = explode(':', $cmd);
				if(trim($cmd[0]) == 'PING')
				{
					$this->send('PONG :'.$cmd[1]);
					$continue = FALSE;
				}
			}
			usleep(5000);
		}
	}

	public function userModeAdd($channel, $user, $level)
	{
		if(empty($this->_users[$channel]))
			$this->_users[$channel] = array();

		if(empty($this->_users[$channel][$user]))
			$this->_users[$channel][$user] = "";

		$this->_users[$channel][$user] .= $level;
	}

	public function userModeRemove($channel, $user, $level)
	{
		if(empty($this->_users[$channel]))
			$this->_users[$channel] = array();

		if(empty($this->_users[$channel][$user]))
			$this->_users[$channel][$user] = "";

		$this->_users[$channel][$user] = str_replace($level, '', $this->_users[$channel][$user]);
	}

	public function userJoin($channel, $user)
	{
		if(!isset($this->_users[$channel][$user]))
			$this->_users[$channel][$user] = '';
	}

	public function userPart($channel, $user)
	{
		if(isset($this->_users[$channel][$user]))
			unset($this->_users[$channel][$user]);
	}

	public function capabilityRequest($capability)
	{
		$this->send("CAP REQ :". $capability);
	}

	public function send($data, $time = FALSE)
	{
		if(!$time)
			$this->_buffer[] = $data;
		else
			$this->_buffer['time:'.$time] = $data;
	}

	public function processBuffer()
	{
		foreach($this->_buffer as $time => $data)
		{
			if(substr($time, 0, 5) == 'time:')
			{
				if(substr($time, 5) == time())
				{
					if(HedgeBot::$verbose >= 2)
						echo '->['.Server::getName().'] '.$data."\n";

					socket_write($this->_socket, $data."\r\n");
					unset($this->_buffer[$time]);
				}
			}
			elseif($this->_lastSend + 2 <= time() || !$this->_floodLimit)
			{
				if(HedgeBot::$verbose >= 2)
					echo '->['.Server::getName().'] '.$data."\n";

				socket_write($this->_socket, $data."\r\n");
				unset($this->_buffer[$time]);
				$this->_lastSend = time();
			}
		}
	}

	public function emptyBufferMessages($channel)
	{
		foreach($this->_buffer as $k => $v)
		{
			HedgeBot::message($v);
			$v = explode(' ', $v);
			if(($v[0] == 'PRIVMSG' || $v[0] == 'NOTICE') && isset($v[1]) && $v[1] == $channel)
				unset($this->_buffer[$k]);
		}
	}

	public function getLastBufferTime()
	{
		$last = '';
		$b = array_keys($this->_buffer);
		do
		{
			$last = array_pop($b);
		} while(substr($last, 0, 4) != 'time');

		return substr($last, 5);
	}

	public function parseMsg($message)
	{
		$raw = $message;
		$msg = '';
		$channel = "";
		$nick = "";
		$moderator = false;
		$command = explode(':', trim($message), 3);

		if(trim($command[0]) == 'PING')
			return array('command' => 'PING', 'additionnal' => $command[1]);


		$membership = array();

		if(!empty($command[0]) && $command[0][0] == "@")
		{
			$membershipData = explode(';', substr($command[0], 1));

			foreach ($membershipData as $data)
			{
				$data = explode('=', trim($data));
				$membership[$data[0]] = $data[1];
			}

			if(!empty($membership['subscriber']))
			{
				if($membership['subscriber'] == '1')
					$membership['subscriber'] = true;
				else
					$membership['subscriber'] = false;
			}

			if(!empty($membership['turbo']))
			{
				if($membership['turbo'] == '1')
					$membership['turbo'] = true;
				else
					$membership['turbo'] = false;
			}
		}

		if(isset($command[2]))
			$msg = $command[2];

		$cmd = explode(' ', $command[1], 4);
		$user = explode('!', $cmd[0]);
		if(isset($user[1]))
		{
			$nick = $user[0];
			$user = $user[1];
		}
		else
			$nick = $user = $user[0];

		$command = $cmd[1];
		if(isset($cmd[2]))
			$channel = substr($cmd[2], 1); // Remove the # from the channel name


		// If the user has the same name as the channel, that means he's the broadcaster
		if($nick == $channel)
		{
			$membership['user-type'] = "mod";
			$membership['broadcaster'] = true;
		}
		else
			$membership['broadcaster'] = false;

		if(!empty($membership['user-type']))
			$moderator = true;

		if(isset($cmd[3]))
			$additionnal_parameters = explode(' ', $cmd[3]);
		else
			$additionnal_parameters = array();

		$return = array('membership' => $membership,
						'nick' => $nick,
						'user' => $user,
						'command' => $command,
						'channel' => $channel,
						'additionnal' => $additionnal_parameters,
						'message' => $msg,
						'raw' => $raw,
						'moderator' => $moderator);

		return $return;
	}
}
