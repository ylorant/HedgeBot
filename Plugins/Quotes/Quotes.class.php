<?php
namespace HedgeBot\Plugins\Quotes;

use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\Plugins\Plugin as PluginBase;
use HedgeBot\Core\API\IRC;

/**
 * Quote manager plugin.
 * Handles a quote storage system in the bot, allowing users to call-out quotes saved to highlight
 * famous moments of the stream's history, or just to make fun of the streamer.
 */
class Quotes extends PluginBase
{
    private $quotes = []; // Quotes, by channel
    
    public function init()
    {
        if(!empty($this->data->quotes))
            $this->quotes = $this->data->quotes->toArray();
    }
    
    /**
     * Mod function: Adds a quote to the quote list
     */
    public function CommandAddquote($command, $args)
    {
        // Check rights
        if(!$command['moderator'])
            return;
        
        if(!count($args))
            return IRC::reply($command, "Insufficient parameters.");
        
        array_shift($args); // Remove the command name from the args to get the actual quote
        
        // Create channel if needed
        if(!isset($this->quotes[$command['channel']]))
            $this->quotes[$command['channel']] = [];
        
        $this->quotes[$command['channel']][] = join(' ', $args);
        $this->data->set('quotes', $this->quotes);
        
        $index = count($this->quotes[$command['channel']]); // Count = last index + 1, basically the quote index
        IRC::reply($command, 'Quote #'. $index. ' added.');
    }
    
    /**
     * Mod function: Edits a quote
     */
    public function CommandEditquote($command, $args)
    {
        if(!$command['moderator'])
            return;
        
        if(count($args) < 2)
            return IRC::reply($command, "Insufficient parameters.");
        
        array_shift($args); // Remove command name
        $quoteID = array_shift($args) - 1; // Get quote ID
        
        // Create channel if needed
        if(!isset($this->quotes[$command['channel']]))
            $this->quotes[$command['channel']] = [];
        
        if(!isset($this->quotes[$command['channel']][$quoteID]))
            return IRC::reply($command, "This quote doesn't exist");
        
        // Update the quote
        $this->quotes[$command['channel']][$quoteID] = join(' ', $args);
        $this->data->set('quotes', $this->quotes);
        
        IRC::reply($command, "Quote #". $quoteID. " updated.");
    }
    
    /**
     * Mod function: Deletes a quote
     */
    public function CommadnDelquote($command, $args)
    {
        if(!$command['moderator'])
            return;
        
        if(!count($args))
            return IRC::reply($command, "Insufficient parameters.");
        
        $quoteID = array_shift($args) - 1; // Get quote ID
        
        // Create channel if needed
        if(!isset($this->quotes[$command['channel']]))
            $this->quotes[$command['channel']] = [];
        
        if(!isset($this->quotes[$command['channel']][$quoteID]))
            return IRC::reply($command, "This quote doesn't exist");
        
        unset($this->quotes[$command['channel']][$quoteID]);
        $this->data->set('quotes', $this->quotes);
        
        IRC::reply($command, "Quote deleted.");
    }
	
    /**
     * Shows a quote. Quote ID can be given as parameter, or if not given, one will be chosen randomly.
     */
    public function CommandQuote($command, $args)
    {
        // If there isn't any quote for this channel, don't do anything
        if(empty($this->quotes[$command['channel']]))
            return;
        
        $quoteID = null;
        
        if(!empty($args[0]))
            $quoteID = intval($args[0]) - 1;
        else
            $quoteID = rand(0, count($this->quotes[$command['channel']]) - 1);
        
        if(!isset($this->quotes[$command['channel']][$quoteID]))
            return IRC::reply($command, "This quote doesn't exist.");
        
        IRC::reply($command, "[#". ($quoteID + 1). "] ". $this->quotes[$command['channel']][$quoteID]);
    }
}