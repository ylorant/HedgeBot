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
use HedgeBot\Core\API\Tikal;

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

        if(!empty($this->config['clientTimeout'])) {
            $this->service->setTimeout($this->config['clientTimeout']);
        }

        $this->loadData();
        Plugin::getManager()->addRoutine($this, "RoutineSendTweets", 10);

        // Don't load the API endpoint if we're not on the main environment
        if (ENV == "main") {
            Tikal::addEndpoint('/plugin/twitter', new TwitterEndpoint($this));
        }
    }

    /**
     * Routine: sends the scheduled tweets.
     */
    public function RoutineSendTweets()
    {
        $now = new DateTime();

        // Anonymous function to check if the tweet is in a sendable state
        $tweetCanBeSent = function(ScheduledTweet $tweet) use($now) {
            if($tweet->getTrigger() != ScheduledTweet::TRIGGER_DATETIME) {
                return false;
            }
    
            if($tweet->getStatus() != ScheduledTweet::STATUS_SCHEDULED) {
                return false;
            }
    
            if($tweet->getSendTime() > $now) {
                return false;
            }

            return true;
        };

        /** @var ScheduledTweet $tweet */
        foreach($this->scheduledTweets as &$tweet) {
            if($tweetCanBeSent($tweet) && $this->checkConstraints($tweet)) {
                $this->sendTweet($tweet);
                continue;
            }
            
            // Reset the status or delete the tweets after their expiration time
            if(in_array($tweet->getStatus(), [ScheduledTweet::STATUS_SENT, ScheduledTweet::STATUS_ERROR])) {
                $sentTime = clone $tweet->getSentTime();
                $sentTime->add(new DateInterval('PT' . $this->config['cleanupDelay'] . 'S'));

                // Expiration rules: 
                // - Only delete time based tweets if the delete config option is enabled
                // - Event-basaed tweets that expire the cleanup delay are put back into the queue
                if($sentTime <= $now) {
                    if(filter_var($this->config['deleteSentTweets'], FILTER_VALIDATE_BOOLEAN)) {
                        $deleted = $this->deleteScheduledTweet($tweet->getId());

                        if($deleted) {
                            HedgeBot::message("Deleted tweet $0.", [$tweet->getId()], E_DEBUG);
                        } else {
                            HedgeBot::message("Failed deleting tweet $0.", [$tweet->getId()], E_WARNING);
                        }
                    } elseif($tweet->getTrigger() == ScheduledTweet::TRIGGER_EVENT) {
                        $tweet->setStatus(ScheduledTweet::STATUS_SCHEDULED);
                        $tweet->setSentTime(null);
                        $this->saveData();

                        HedgeBot::message("Reset tweet $0 in the scheduled queue.", [$tweet->getId()], E_DEBUG);
                    }
                }
            }
        }
    }

    /**
     * Config has been updated externally.
     */
    public function CoreEventConfigUpdate()
    {
        // TODO: Find a way to avoid to re-find the configuration manually
        $this->config = HedgeBot::getInstance()->config->get('plugin.Twitter');
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
        $eventFQN = strtolower($event->event->getType(). "/". $event->event->name);

        if(!in_array($eventFQN, $this->listenedEvents)) {
            return;
        }

        // Iterate through the tweets to get the one(s) to be sent
        foreach($this->scheduledTweets as $tweet) {
            if($tweet->getTrigger() != ScheduledTweet::TRIGGER_EVENT) {
                continue;
            }

            if($tweet->getEvent() == $eventFQN && $tweet->getStatus() == ScheduledTweet::STATUS_SCHEDULED) {
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
     * 
     * TODO: Clean up code duplications here
     */
    public function sendTweet(ScheduledTweet $tweet)
    {
        $this->service->setCurrentAccount($tweet->getAccount());
        $mediaIds = [];
        $failedUploadingMedia = false;
        $simulatedLastError = null;
        $isDryRun = filter_var($this->config['dryRun'] ?? null, FILTER_VALIDATE_BOOLEAN);
        
        // Upload the media before sending the tweet, only if not in a dry run
        if(!$isDryRun) {
            $tweetMedias = $tweet->getMedia();
            foreach($tweetMedias as $mediaUrl) {
                $mediaId = $this->service->uploadMedia($mediaUrl);

                if(!empty($mediaId)) {
                    $mediaIds[] = $mediaId;
                } else {
                    $failedUploadingMedia = true;
                }
            }

            if($failedUploadingMedia) {
                HedgeBot::message("Failed uploading media when sending tweet $0", [$tweet->getId()], E_ERROR);
                $tweet->setStatus(ScheduledTweet::STATUS_ERROR);
                $tweet->setSentTime(new DateTime());
                $tweet->setError($simulatedLastError ?? $this->service->getLastError()); 
                $this->saveData();
                return false;
            }
            
            // Tweet
            $sent = $this->service->tweet($tweet->getContent(), $mediaIds);
        } else {
            // We're in a dry run, we simulate the reply if needed
            $sent = filter_var($this->config['dryRunSuccess'], FILTER_VALIDATE_BOOLEAN);
            if(!$sent) {
                $simulatedLastError = "(XXX) Simulated tweet error";
            }
        }
        
        // Log the error if the tweet sending failed
        if(!$sent) {
            HedgeBot::message("Failed sending tweet $0", [$tweet->getId()], E_ERROR);
            if(!empty($this->service->getLastError())) {
                HedgeBot::message('Error $code: $message', $this->service->getLastError(), E_ERROR);
            }
            $tweet->setStatus(ScheduledTweet::STATUS_ERROR);
            $tweet->setError($simulatedLastError ?? $this->service->getLastError());
            $tweet->setSentTime(new DateTime());
            $this->saveData();

            return false;
        }

        // Delete the tweet or just mark it as sent
        $tweet->setStatus(ScheduledTweet::STATUS_SENT);
        $tweet->setSentTime(new DateTime());

        $this->saveData();
        HedgeBot::message("Tweeted scheduled tweet $0", [$tweet->getId()], E_DEBUG);

        return true;
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
    public function saveScheduledTweet(ScheduledTweet $tweet)
    {
        if(!$this->service->hasAccessToken($tweet->getAccount())) {
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
        foreach($this->scheduledTweets as $scheduledTweet) {
            if($scheduledTweet->getId() == $id) {
                unset($this->scheduledTweets[$id]);
        
                $this->saveData();
                $this->reloadTweetsEvents();

                return true;
            }
        }

        return false;
    }

    /**
     * Gets the scheduled tweets saved in the list.
     * 
     * @param array $filters Allows to filter tweets with, by key:
     *                       - account: The account that will tweet
     *                       - channel: The bound channel
     *                       - status: The tweet status(es) 
     * 
     * @return array an associative array of all the scheduled tweets, with their ID as key.
     */
    public function getScheduledTweets(array $filters = [])
    {
        $output = $this->scheduledTweets;

        if(!empty($filters)) {
            /** @var ScheduledTweet $tweet */
            $output = array_filter($output, function($tweet) use($filters) {
                $filterMatch = true;
                
                if(!empty($filters['account'])) {
                    $filterMatch = $filterMatch && $tweet->getAccount() == $filters['account'];
                }

                if(!empty($filters['channel'])) {
                    $filterMatch = $filterMatch && $tweet->getChannel() == $filters['channel'];
                }

                if(!empty($filters['status'])) {
                    $filterMatch = $filterMatch && in_array($tweet->getStatus(), (array) $filters['status']);
                }

                return $filterMatch;
            });
        }

        return $output;
    }

    /**
     * Gets a scheduled tweet by its ID.
     * 
     * @param string $id The tweet ID.
     * 
     * @return ScheduledTweet|null The tweet or null if not found.
     */
    public function getScheduledTweet($id)
    {
        foreach($this->scheduledTweets as $scheduledTweet) {
            if($scheduledTweet->getId() == $id) {
                return $scheduledTweet;
            }
        }
        return null;
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
     * Gets the consumer key from the plugin's configuration.
     * 
     * @return array The consumer and secret keys, in an associative array with "consumer" and "secret" for their 
     *               respective keys.
     */
    public function getConsumerKey()
    {
        return [
            "consumer" => $this->config['consumerApiKey'],
            "secret" => $this->config['consumerSecretKey']
        ];
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
        $this->service->saveAccessTokens();
    }

    /**
     * Loads the plugin's data from the storage.
     * 
     * @return void
     */
    public function loadData()
    {
        $this->scheduledTweets = [];
        $this->service->reloadAccessTokens();

        $scheduledTweets = $this->data->scheduledTweets->toArray();

        // Hydrate and restore format of each tweet
        if(is_array($scheduledTweets)) {
            foreach($scheduledTweets as $scheduledTweet) {
                $scheduledTweet = ScheduledTweet::fromArray($scheduledTweet);
                $this->scheduledTweets[$scheduledTweet->getId()] = $scheduledTweet;
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
                $this->listenedEvents[] = strtolower($tweet->getEvent());
            }
        }

        $this->listenedEvents = array_unique($this->listenedEvents);
    }
}