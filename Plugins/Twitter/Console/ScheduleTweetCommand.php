<?php

namespace HedgeBot\Plugins\Twitter\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use HedgeBot\Core\Console\PluginAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use DateTime;
use HedgeBot\Plugins\Twitter\Entity\ScheduledTweet;
use HedgeBot\Core\API\ServerList;

/**
 * Class ScheduleTweetCommand
 * @package HedgeBot\Plugins\Twitter\Console
 */
class ScheduleTweetCommand extends Command
{
    use PluginAwareTrait;

    /**
     * Configures the command.
     */
    public function configure()
    {
        $this->setName('twitter:schedule-tweet')
            ->setDescription('Schedules a tweet to be posted on Twitter.');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $plugin = $this->getPlugin();
        $service = $plugin->getService();
        $helper = $this->getHelper('question');
        $tweet = new ScheduledTweet();
        $accounts = $service->getAccessTokenAccounts();
        
        // Account selection
        $acctQuestion = new ChoiceQuestion("Select the account you want to tweet on: ", $accounts);
        $account = $helper->ask($input, $output, $acctQuestion);
        $tweet->setAccount($account);

        // Tweet content
        $tweetQuestion = new Question("Type the tweet: ");
        $content = $helper->ask($input, $output, $tweetQuestion);
        $tweet->setContent($content);

        // Medias
        $mediaUrl = null;
        $mediaQuestion = new Question("Select a media to embed into the tweet (up to 4, put an empty media to stop): ");
        $mediaCount = 0;

        do {
            $mediaUrl = $helper->ask($input, $output, $mediaQuestion);

            if(!empty($mediaUrl)) {
                $tweet->addMedia($mediaUrl);
                $mediaCount++;
            }
        } while(!empty($mediaUrl) && $mediaCount < 4);

        // Trigger type
        $triggers = [ScheduledTweet::TRIGGER_DATETIME, ScheduledTweet::TRIGGER_EVENT];
        $triggerQuestion = new ChoiceQuestion("Select what will trigger the tweet to be sent: ", $triggers);
        $trigger = $helper->ask($input, $output, $triggerQuestion);
        $tweet->setTrigger($trigger);

        // Details for each type
        switch($trigger) {
            case ScheduledTweet::TRIGGER_DATETIME:
                // Send date
                $dateQuestion = new Question("Type the date and time this tweet should be sent at (ISO-8601): ");
                $dateTime = $helper->ask($input, $output, $dateQuestion);
                $tweet->setSendTime(new DateTime($dateTime));

                // Channel
                $channelQuestion = new Question("Type the channel the tweet is bound to: ");
                $channel = $helper->ask($input, $output, $channelQuestion);
                $tweet->setChannel($channel);
                break;

            case ScheduledTweet::TRIGGER_EVENT:
                // Event
                $eventQuestion = new Question("Type the event name that will trigger the tweet sending (<listener>/<event>): ");
                $event = $helper->ask($input, $output, $eventQuestion);
                $tweet->setEvent($event);
                break;
        }

        // Constraints
        $stopType = "Stop adding constraints";
        $constraintTypes = [ScheduledTweet::CONSTRAINT_STORE, ScheduledTweet::CONSTRAINT_EVENT, $stopType];
        $constraintTypeQuestion = new ChoiceQuestion("Select a constraint to add on the tweet: ", $constraintTypes);
        $constraintLValueQuestion = new Question("Type the var on which you will be performing the check: ");
        $constraintRValueQuestion = new Question("Type the value the var should have: ");

        $constraintType = null;
        $constraintLVal = null;
        $constraintRVal = null;

        do {
            $constraintType = $helper->ask($input, $output, $constraintTypeQuestion);
            
            if($constraintType != $stopType) {
                $constraintLVal = $helper->ask($input, $output, $constraintLValueQuestion);
                $constraintRVal = $helper->ask($input, $output, $constraintRValueQuestion);
                
                $constraint = [
                    "type" => $constraintType,
                    "lval" => $constraintLVal,
                    "rval" => $constraintRVal
                ];

                $tweet->addConstraint($constraint);
            }
        } while($constraintType != $stopType);

        $plugin->scheduleTweet($tweet);
    }
}