<?php
namespace HedgeBot\Plugins\Twitter;

use HedgeBot\Core\Plugins\Plugin as PluginBase;
use HedgeBot\Core\API\Plugin;
use HedgeBot\Core\HedgeBot;
use HedgeBot\Core\Events\CoreEvent;
use HedgeBot\Plugins\Twitter\Entity\ScheduledTweet;
use DateTime;
use DateInterval;
use HedgeBot\Core\API\Store;
use HedgeBot\Core\Store\Formatter\TraverseFormatter;
use HedgeBot\Core\Events\Event;

class Twitter extends PluginBase
{
    /** @var array Tweets that are scheduled for sending */
    protected $scheduledTweets = [];
    /** @var array Events that the plugin is listening on, generated from the scheduled tweets data */
    protected $listenedEvents = [];
    /** @var TwitterService the Twitter communication service */
    protected $service;

    /**
     * Plugin initialization.
     */
    public function init()
    {
        $this->service = new TwitterService(
            $this->config['consumerApiKey'], 
            $this->config['consumerSecretKey'],
            $this->config['oauthCallbackUrl'],
            $this->data
        );

        $this->loadData();
        Plugin::getManager()->addRoutine($this, "RoutineSendTweets", 10);
    }

    /**
     * Routine: sends the scheduled tweets.
     */
    public function RoutineSendTweets()
    {
        $now = new DateTime();
        $tweetsToRemove = [];

        foreach($this->scheduledTweets as &$tweet) {
            if($tweet->getTrigger() != ScheduledTweet::TRIGGER_DATETIME) {
                continue;
            }

            if($tweet->getSendTime() < $now && !$tweet->isSent()) {
                if($this->checkConstraints($tweet)) {
                    $this->sendTweet($tweet);
                }

                continue;
            }

            // Reset the sent status if needed
            if($tweet->isSent() && $tweet->getTrigger() != ScheduledTweet::TRIGGER_DATETIME) {
                $sentTime = clone $tweet->getSentTime();
                $sentTime->add(new DateInterval('PT' . $this->config['resetSentDelay'] . 'S'));

                if($sentTime <= $now) {
                    $tweet->setSent(false);
                    $this->saveData();
                }
            }
        }
    }

    /**
     * Core event: data has been updated from elsewhere
     */
    public function CoreEventDataUpdate()
    {
        $this->loadData();
    }

    /**
     * Core Event: Called when any event is called.
     */
    public function CoreEventEvent(CoreEvent $event)
    {
        $eventFQN = $event->event->getType(). "/". $event->event->name;

        if(!in_array($eventFQN, $this->listenedEvents)) {
            return;
        }

        // Iterate through the tweets to get the one(s) to be sent
        foreach($this->scheduledTweets as $tweet) {
            if($tweet->getTrigger() != ScheduledTweet::TRIGGER_EVENT) {
                continue;
            }

            if($tweet->getEvent() == $eventFQN && !$tweet->isSent()) {
                HedgeBot::message("Catched event $0 for tweet $1", [$eventFQN, $tweet->getId()], E_DEBUG);
                if($this->checkConstraints($tweet, $event->event)) {
                    $this->sendTweet($tweet);
                }
            }
        }
    }

    /**
     * Checks if a scheduled tweet satisfies its sending constraints.
     * 
     * @param ScheduledTweet $tweet The scheduled tweet to check the constraints of.
     * @param Event $event The source event that allows the method to get the channel for the store. Optional.
     *                     If not given (or there is no channel info in the event), the method will try to get the 
     *                     channel from the scheduled tweet itself. If both the scheduled tweet and the event have
     *                     channel info, the scheduled tweet will be prioritary.
     * 
     * @return bool True if the tweet satisfies its sending constraints, False if it doesn't. 
     */
    public function checkConstraints(ScheduledTweet $tweet, Event $event = null)
    {
        $constraints = $tweet->getConstraints();
        $store = null;
        $traverseFormatter = null;
        $channel = null;

        if(!empty($tweet->getChannel())) {
            $channel = $tweet->getChannel();
        } elseif(!empty($event->channel)) {
            $channel = $event->channel;
        }

        foreach($constraints as $constraint) {
            switch($constraint['type']) {
                case ScheduledTweet::CONSTRAINT_STORE:
                    if(empty($store)) {
                        $store = Store::getData($channel);
                        /** @var TraverseFormatter $traverseFormatter */
                        $traverseFormatter = Store::getFormatter(TraverseFormatter::getName());
                    }

                    $storeElementValue = $traverseFormatter->traverse($constraint['lval'], $store);
                    if($storeElementValue != $constraint['rval']) {
                        HedgeBot::message("Tweet constraint fail: $0 -> $1 != $2", [$constraint['lval'], $storeElementValue, $constraint['rval']], E_DEBUG);
                        return false;
                    }
                    break;
            }
        }

        HedgeBot::message("Tweet constraints pass", E_DEBUG);

        return true;
    }

    /**
     * Sends a scheduled tweet
     * 
     * @param ScheduledTweet $tweet The scheduled tweet to send.
     */
    public function sendTweet(ScheduledTweet $tweet)
    {
        $this->service->setCurrentAccount($tweet->getAccount());
        $mediaIds = [];
        
        // Upload the media before sending the tweet
        $tweetMedias = $tweet->getMedia();
        foreach($tweetMedias as $mediaUrl) {
            $mediaIds[] = $this->service->uploadMedia($mediaUrl);
        }

        // Tweet
        $this->service->tweet($tweet->getContent(), $mediaIds);
        
        // Delete the tweet or just mark it as sent
        if($this->config['deleteSentTweets'] == "true") {
            $this->deleteScheduledTweet($tweet->getId());
        } else {
            $tweet->setSent(true);
            $tweet->setSentTime(new DateTime());
        }
        
        $this->saveData();

        HedgeBot::message("Tweeted scheduled tweet $0", [$tweet->getId()], E_DEBUG);
    }

    /**
     * Schedules a tweet for future sending.
     * 
     * @param string $account The account that will send the tweet.
     * @param DateTime $sendTime The time at which to send the tweet.
     * @param string $content The tweet.
     * @param array $media An array of media paths to include in the tweet (max. 4).
     * 
     * @return string The new tweet ID. Uses uniqid().
     */
    public function scheduleTweet(ScheduledTweet $tweet)
    {
        if(!$this->service->hasToken($tweet->getAccount())) {
            return false;
        }

        // Initialize the ID if not given
        if(empty($tweet->getId())) {
            $tweet->setId(uniqid());
        }

        $this->scheduledTweets[$tweet->getId()] = $tweet;

        $this->saveData();
        $this->reloadTweetsEvents();

        return $tweet->getId();
    }

    /**
     * Deletes a scheduled tweet.
     * 
     * @param string $id The ID of the tweet to delete.
     * 
     * @return bool True if the scheduled tweet has been deleted, false if not.
     */
    public function deleteScheduledTweet($id)
    {
        if(!isset($this->scheduledTweets[$id])) {
            return false;
        }

        unset($this->scheduledTweets[$id]);

        $this->saveData();
        $this->reloadTweetsEvents();

        return true;
    }

    /**
     * Gets the scheduled tweets saved in the list.
     * TODO: allow to filter by account ?
     * 
     * @return array an associative array of all the scheduled tweets, with their ID as key.
     */
    public function getScheduledTweets()
    {
        return $this->scheduledTweets;
    }

    /**
     * Gets the Twitter communication service, to perform direct tasks on Twitter.
     * 
     * @return TwitterService The Twitter communication service.
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * Saves the plugin's data to the storage.
     * 
     * @return void
     */
    public function saveData()
    {
        $scheduledTweets = [];

        // Process and convert to array the tweets
        foreach($this->scheduledTweets as $tweet) {
            $scheduledTweets[] = $tweet->toArray();
        }

        $this->data->scheduledTweets = $scheduledTweets;
        $this->service->saveTokens();
    }

    /**
     * Loads the plugin's data from the storage.
     * 
     * @return void
     */
    public function loadData()
    {
        $this->scheduledTweets = [];
        $this->service->reloadTokens();

        $scheduledTweets = $this->data->scheduledTweets->toArray();

        // Hydrate and restore format of each tweet
        if(is_array($scheduledTweets)) {
            foreach($scheduledTweets as $scheduledTweet) {
                $this->scheduledTweets[] = ScheduledTweet::fromArray($scheduledTweet);
            }
        }
        
        $this->reloadTweetsEvents();
    }

    /**
     * Loads the events in each scheduled tweet trigger settings to shortcut them into an array for easier access
     * on the event callback method.
     * 
     * @return void
     */
    protected function reloadTweetsEvents()
    {
        $this->listenedEvents = [];
        
        /** @var ScheduledTweet $tweet */
        foreach($this->scheduledTweets as $tweet) {
            if($tweet->getTrigger() == ScheduledTweet::TRIGGER_EVENT) {
                $this->listenedEvents[] = $tweet->getEvent();
            }
        }

        $this->listenedEvents = array_unique($this->listenedEvents);
    }
}