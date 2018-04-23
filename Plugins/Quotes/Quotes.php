<?php

namespace HedgeBot\Plugins\Quotes;

use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\Plugins\Plugin as PluginBase;
use HedgeBot\Core\API\IRC;
use HedgeBot\Core\Events\CommandEvent;

/**
 * Class Quotes
 *
 * Quote manager plugin.
 * Handles a quote storage system in the bot, allowing users to call-out quotes saved to highlight
 * famous moments of the stream's history, or just to make fun of the streamer.
 *
 * @package HedgeBot\Plugins\Quotes
 */
class Quotes extends PluginBase
{
    private $quotes = []; // Quotes, by channel

    /**
     * @return bool|void
     */
    public function init()
    {
        if (!empty($this->data->quotes)) {
            $this->quotes = $this->data->quotes->toArray();
        }
    }

    /**
     * Adds a quote to the quote list
     *
     * @param CommandEvent $ev
     * @return mixed
     */
    public function CommandAddquote(CommandEvent $ev)
    {
        $args = $ev->arguments;
        if (!count($args)) {
            return IRC::reply($ev, "Insufficient parameters.");
        }

        array_shift($args); // Remove the command name from the arguments to get the actual quote

        // Create channel if needed
        if (!isset($this->quotes[$ev->channel])) {
            $this->quotes[$ev->channel] = [];
        }

        $this->quotes[$ev->channel][] = join(' ', $args);
        $this->data->set('quotes', $this->quotes);

        $index = count($this->quotes[$ev->channel]); // Count = last index + 1, basically the quote index
        IRC::reply($ev, 'Quote #' . $index . ' added.');
    }

    /**
     * Edits a quote
     *
     * @param CommandEvent $ev
     * @return mixed
     */
    public function CommandEditquote(CommandEvent $ev)
    {
        $args = $ev->arguments;
        if (count($args) < 2) {
            return IRC::reply($ev, "Insufficient parameters.");
        }

        array_shift($args); // Remove command name
        $quoteID = array_shift($args) - 1; // Get quote ID

        // Create channel if needed
        if (!isset($this->quotes[$ev->channel])) {
            $this->quotes[$ev->channel] = [];
        }

        if (!isset($this->quotes[$ev->channel][$quoteID])) {
            return IRC::reply($ev, "This quote doesn't exist");
        }

        // Update the quote
        $this->quotes[$ev->channel][$quoteID] = join(' ', $args);
        $this->data->set('quotes', $this->quotes);

        IRC::reply($ev, "Quote #" . $quoteID . " updated.");
    }

    /**
     * Deletes a quote
     *
     * @param CommandEvent $ev
     * @return mixed
     */
    public function CommandDelquote(CommandEvent $ev)
    {
        $args = $ev->arguments;
        if (!count($args)) {
            return IRC::reply($ev, "Insufficient parameters.");
        }

        $quoteID = array_shift($args) - 1; // Get quote ID

        // Create channel if needed
        if (!isset($this->quotes[$ev->channel])) {
            $this->quotes[$ev->channel] = [];
        }

        if (!isset($this->quotes[$ev->channel][$quoteID])) {
            return IRC::reply($ev, "This quote doesn't exist");
        }

        unset($this->quotes[$ev->channel][$quoteID]);
        $this->data->set('quotes', $this->quotes);

        IRC::reply($ev, "Quote deleted.");
    }

    /**
     * Shows a quote
     * Quote ID can be given as parameter, or if not given, one will be chosen randomly
     *
     * @param CommandEvent $ev
     */
    public function CommandQuote(CommandEvent $ev)
    {
        // If there isn't any quote for this channel, don't do anything
        if (empty($this->quotes[$ev->channel])) {
            return;
        }

        $quoteID = null;
        $args = $ev->arguments;

        if (!empty($args[0])) {
            $quoteID = intval($args[0]) - 1;
        } else {
            $quoteID = rand(0, count($this->quotes[$ev->channel]) - 1);
        }

        if (!isset($this->quotes[$ev->channel][$quoteID])) {
            return IRC::reply($ev, "This quote doesn't exist.");
        }

        IRC::reply($ev, "[#" . ($quoteID + 1) . "] " . $this->quotes[$ev->channel][$quoteID]);
    }
}
