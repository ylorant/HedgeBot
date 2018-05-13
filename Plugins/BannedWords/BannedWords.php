<?php

namespace HedgeBot\Plugins\BannedWords;

use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\Plugins\Plugin as PluginBase;
use HedgeBot\Core\API\IRC;
use HedgeBot\Core\Traits\PropertyConfigMapping;
use HedgeBot\Core\Events\CoreEvent;
use HedgeBot\Core\Events\ServerEvent;

/**
 * Banned words plugin.
 * Allows to ban some words and other undesirable messages on the chat.
 *
 * Configuration variables :
 *
 * - bannedWords : Banned word list. A subsection for this parameter is usable, along with an array notation.
 * - timeoutDuration : The timeout duration for a person who sends a banned word. To only purge the message
 *                     without timing out the user, put 0 as value. Having a value defined as 0 for a channel
 *                     will override the global configuration.
 *
 * These config vars are definable in a global manner using the config namespace "plugin.BannedWords",
 * and per-channel, using the config namespaces "plugin.BannedWords.channel.<channel-name>". If one config parameter
 * misses from the per-channel config, then it is taken from the global config.
 * It is advised to define both, to avoid having situations where the default ones are used.
 *
 * For banned words, both channel and global configuration will be used : if a word isn't in the channel banlist but
 * is in the global banlist, then the message will be purged and the user timeout-ed (if necessary).
 */
class BannedWords extends PluginBase
{
    use PropertyConfigMapping;

    // Plugin configuration variables, per channel
    private $bannedWords = [];

    // Plugin configuration variables, global fallback
    private $globalBannedWords;
    private $globalTimeoutDuration;

    const DEFAULT_TIMEOUT_DURATION = 0;

    /** Plugin initialization */
    public function init()
    {
        $this->reloadConfig();
    }

    /**
     * System event: configuration change handling
     *
     * @param CoreEvent $ev
     */
    public function SystemEventConfigUpdate(CoreEvent $ev)
    {
        $this->config = HedgeBot::getInstance()->config->get('plugin.Currency');
        $this->reloadConfig();
    }

    /**
     * Handles chat messages, checks that any of the banned words for the channel or in the global word banlist
     * isn't in the message, and timeouts the user as a consequence.
     *
     * @param ServerEvent $ev
     */
    public function ServerPrivmsg(ServerEvent $ev)
    {
        // Moderators are exempted from being timeout'd
        if ($ev->moderator) {
            return;
        }

        $message = $this->normalize($ev->message);
        $timeoutDuration = $this->getConfigParameter($ev->channel, 'timeoutDuration');

        // Simple purge is a timeout of 1 second
        if ($timeoutDuration == 0) {
            $timeoutDuration = 1;
        }

        // Check against global banned words
        foreach ($this->globalBannedWords as $bannedWord) {
            // Match against a banned word, we timeout
            if (strpos($message, $bannedWord) !== false) {
                IRC::message($ev->channel, ".timeout ". $ev->nick. " ". $timeoutDuration);
            }
        }

        // Check against channel banned words
        if (!empty($this->bannedWords[$ev->channel])) {
            foreach ($this->bannedWords[$ev->channel] as $bannedWord) {
                // Match against a banned word, we timeout (duh)
                if (strpos($message, $bannedWord) !== false) {
                    IRC::message($ev->channel, ".timeout ". $ev->nick. " ". $timeoutDuration);
                }
            }
        }
    }

    /**
     * Reloads the config using property mapping
     */
    public function reloadConfig()
    {
        $parameters = [
            'bannedWords',
            'timeoutDuration'
        ];

        $this->globalBannedWords = [];
        $this->globalTimeoutDuration = self::DEFAULT_TIMEOUT_DURATION;

        $this->mapConfig($this->config, $parameters);

        // Normalize all loaded words
        foreach ($this->globalBannedWords as &$word) {
            $word = $this->normalize($word);
        }

        foreach ($this->bannedWords as &$channel) {
            foreach ($channel as &$word) {
                $word = $this->normalize($word);
            }
        }
    }

    /**
     * Normalizes text input by notably removing accents in it.
     * Taken from http://www.weirdog.com/blog/php/supprimer-les-accents-des-caracteres-accentues.html
     *
     * @param string $str     The string to normalize.
     * @param string $charset The charset to use. Defaults to UTF-8.
     * @return null|string|string[]
     */
    public function normalize($str, $charset = 'utf-8')
    {
        $str = htmlentities($str, ENT_NOQUOTES, $charset);

        $str = preg_replace('#&([A-za-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);#', '\1', $str);
        $str = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $str); // pour les ligatures e.g. '&oelig;'

        return $str;
    }
}
