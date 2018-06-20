<?php
namespace HedgeBot\Plugins\StreamControl;

use HedgeBot\Core\Plugins\Plugin;
use HedgeBot\Core\Events\CommandEvent;
use HedgeBot\Core\API\IRC;
use HedgeBot\Core\API\Twitch;

class StreamControl extends Plugin
{
    /**
     * Command: sets the stream title.
     */
    public function CommandSetTitle(CommandEvent $ev)
    {
        $args = $ev->arguments;
        if (count($args) < 2) {
            return IRC::reply($ev, "Insufficient parameters.");
        }

        $title = join(' ', $args);
        Twitch::getClient()->channels->update($ev->channel, ['status' => $title]);
    }

    /**
     * Sets the stream game.
     */
    public function CommandSetGame(CommandEvent $ev)
    {
        $args = $ev->arguments;
        if (count($args) < 2) {
            return IRC::reply($ev, "Insufficient parameters.");
        } 

        $game = join(' ', $args);
        Twitch::getClient()->channels->update($ev->channel, ['game' => $game]);
    }
}