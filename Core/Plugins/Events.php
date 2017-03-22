<?php

/**
 * \file core/Events.class.php
 * \author Yohann Lorant <yohann.lorant@gmail.com>
 * \version 0.5
 * \brief Events class file.
 *
 * \section LICENSE
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * General Public License for more details at
 * http://www.gnu.org/copyleft/gpl.html
 *
 * \section DESCRIPTION
 *
 * This file hosts the Events class, handling all events.
 */

namespace HedgeBot\Core\Plugins;

use HedgeBot\Core\HedgeBot;
use ReflectionClass;

/**
 * \brief Events class for leelabot.
 *
 * \warning For server events and commands, the manager will only handle 1 callback by event at a time. It is done for simplicity purposes, both at plugin's side
 * and at manager's side (I've noticed that it is not necessary to have multiple callbacks for an unique event, unless you can think about getting your code clear)
 */
class Events
{
	protected $events = []; ///< Events storage
	protected $autoMethods = []; ///< Method prefixes for automatic event recognition

	/** Adds a custom event listener, with its auto-binding method prefix.
	 * This function adds a new event listener to the event system. It allows a plugin to create his own space of events, which it cans trigger after, allowing better
	 * and easier interaction between plugins.
	 *
	 * \param $name The name the listener will get.
	 * \param $autoMethodPrefix The prefix there will be used by other plugins for automatic method binding.
	 *
	 * \return TRUE if the listener was correctly created, FALSE otherwise.
	 */
	public function addEventListener($name, $autoMethodPrefix)
	{
		if(isset($this->events[$name]))
		{
			HedgeBot::message('Error : Already defined Event Listener: $0', $name, E_DEBUG);
			return FALSE;
		}

		$this->events[$name] = [];
		$this->autoMethods[$name] = $autoMethodPrefix;

		return TRUE;
	}

	/** Deletes an event listener.
	 * This functions deletes an event listener from the event system. The underlying events for this listener will be deleted as well.
	 *
	 * \param $name The listener's name
	 *
	 * \return TRUE if the listener has been deleted succesfully, FALSE otherwise.
	 */
	public function deleteEventListener($name)
	{
		if(!isset($this->events[$name]))
		{
			HedgeBot::message('Error : Undefined Event Listener: $0', $name, E_DEBUG);
			return FALSE;
		}

		unset($this->events[$name]);

		return TRUE;
	}

	/** Adds an event to an event listener.
	 * This function adds an event to an anlready defined event listener. The callback linked to the event will be later distinguished of the others by an identifier
	 * which must be unique in the same event.
	 *
	 * \param $listener The listener in which the event will be added. Must be defined when adding the event.
	 * \param $id The callback identifier. Must be unique in the same event, can be duplicated across events.
	 * \param $event The event to which the callback will be linked.
	 * \param $callback The callback that will be called when the event is triggered.
	 *
	 * \return TRUE if the event added correctly, FALSE otherwise.
	 */
	public function addEvent($listener, $id, $event, $callback)
	{
		// Normalize event name
		$event = $this->normalize($event);

		if(!isset($this->events[$listener]))
		{
			HedgeBot::message('Error: Undefined Event Listener: $0', $listener, E_DEBUG);
			return FALSE;
		}

		if(!isset($this->events[$listener][$event]))
			$this->events[$listener][$event] = [];

		if(isset($this->events[$listener][$event][$id]))
		{
			HedgeBot::message('Error: Already defined identifier: $0', $id, E_DEBUG);
			return FALSE;
		}

		if(!method_exists($callback[0], $callback[1])) //Check if method exists
		{
			HedgeBot::message('Error : Target method does not exists.', [], E_DEBUG);
			return FALSE;
		}

		$this->events[$listener][$event][$id] = $callback;

		return TRUE;
	}

	/** Deletes an event from the event listener.
	 * This function deletes an already defined callback (bound to an event) from the event listener, so it will not be called by event triggering again.
	 *
	 * \param $listener The event listener from which the callback will be deleted.
	 * \param $event The event name (from which the callback is triggered).
	 * \param $id The callback's ID.
	 *
	 * \return TRUE if the event deleted correctly, FALSE otherwise.
	 */
	public function deleteEvent($listener, $event, $id)
	{
		if(!isset($this->events[$listener]))
		{
			HedgeBot::message('Error: Undefined Event Listener: $0', $listener, E_DEBUG);
			return FALSE;
		}

		if(!isset($this->events[$listener][$event]))
		{
			HedgeBot::message('Error: Undefined Event: $0', $id, E_DEBUG);
			return FALSE;
		}

		if(!isset($this->events[$listener][$event][$id]))
		{
			HedgeBot::message('Error: Already defined identifier: $0', $id, E_DEBUG);
			return FALSE;
		}

		unset($this->events[$listener][$event][$id]);

		return TRUE;
	}

	/** Returns the list of defined events on a listener.
	 * This function returns all the events defined for an event listener.
	 *
	 * \param $listener The listener where we'll get the events.
	 *
	 * \return The list of the events from the listener.
	 */
	public function getEvents($listener)
	{
		return array_keys($this->events[$listener]);
	}

	/** Calls an event for an event listener.
	 * This function calls the callbacks for the given event name. It will execute the callbacks by giving them for parameters the additionnal parameter given to this
	 * method.
	 * For example, calling callEvent like that : $this->callEvent('somelistener', 'connect', 1, 'hi'); will call the callbacks bound to the event "connect" with 2
	 * parameters : 1 and 'hi'.
	 *
	 * \param $listener The listener from which the event will be called.
	 * \param $event The event to call.
	 *
	 * \return TRUE if event has been called correctly, FALSE otherwise.
	 */
	public function callEvent($listener, $event, ...$params)
	{
		if(!isset($this->events[$listener]))
		{
			HedgeBot::message('Error: Undefined Event Listener: $0', $listener, E_DEBUG);
			return FALSE;
		}

		// Normalize event name
		$event = $this->normalize($event);

		if(!isset($this->events[$listener][$event]) || !$this->events[$listener][$event])
			return FALSE;
		
		//Calling back
		foreach($this->events[$listener][$event] as $id => $callback)
			call_user_func_array($callback, $params);

		return TRUE;
	}

	/** Tries to automatically register events.
	 * This methods tries to register events automatically depending on their methods names, using a defined
	 * prefix common for the event listener (defined when the listener was created).
	 *
	 * \param $object The object to load events from.
	 *
	 * \return TRUE.
	 */
	public function autoload($object)
	{
		$reflectionClass = new ReflectionClass($object);
		$methods = $reflectionClass->getMethods(); //Get all class methods for plugin

		//Analyse all class methods
		foreach($methods as $method)
		{
			$methodName = $method->getName();
			//Checks for plugin-defined events
			foreach($this->autoMethods as $listener => $prefix)
			{
				if(preg_match('#^'.$prefix.'#', $methodName))
				{
					$event = $this->normalize(preg_replace('#'.$prefix.'(.+)#', '$1', $methodName));
					HedgeBot::message('Binding method $0::$1 on event $2/$3', [$reflectionClass->getShortName(), $methodName, $listener, $event], E_DEBUG);
					$this->addEvent($listener, $reflectionClass->getName(), $event, [$object, $methodName]);
				}
			}
		}

		return TRUE;
	}

	/** Deletes events by their callee ID.
	 * This method deletes all events with a given callee function ID, regardless of its
	 * listener.
	 *
	 * \param $id The id to search and delete from.
	 *
	 * \return NULL.
	 */
	public function deleteEventsById($id)
	{
		$callbacksToDelete = [];
		foreach($this->events as $listenerName => $events)
		{
			foreach($events as $eventName => $callbacks)
			{
				foreach($callbacks as $callbackId => $callback)
				{
					if($id == $callbackId)
						$callbacksToDelete[] = array($listenerName, $eventName);
				}
			}
		}

		// Delete the events' callbacks
		foreach($callbacksToDelete as $callback)
			unset($this->events[$callback[0]][$callback[1]][$id]);

		return null;
	}

	/**
	 * Normalizes an event name. For now, it only returns it in full lower case.
	 * \param  string  $name The event name to normalize
	 * \return string        The normalized event name.
	 */
	public function normalize($name)
	{
		return strtolower($name);
	}
}
