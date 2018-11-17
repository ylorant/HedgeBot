<?php

namespace HedgeBot\Core\Store\Formatter;

use ArrayAccess;
use JsonSerializable;

/**
 * Markdown expression manager. Handles a string that contains Markdown in it, allows to extract parts of links and remove
 * markup, through the use of modifiers. As of now, it only supports links and bold/italic text.
 * 
 * The modifiers can be applied through method calls as well as array virtual elements, to be able to be used in a TextFormatter.
 * To get the transformed string, you can call the getModifiedText() method or just cast the object to a string.
 * 
 * Available modifiers :
 * - noMarkup: Removes all markup. If there are links still present,
 */
class MarkdownExpression implements ArrayAccess, JsonSerializable
{
    /** @var string The source text, with the markdown still in it */

    /** @var array The modifiers that will be applied to the expression */
    protected $modifiers;
    /** @var array The different tokens in the text. */
    protected $tokens;

    const MODIFIER_STRIP = "strip";
    const MODIFIER_LINK_TITLE = "title";
    const MODIFIER_LINK_LINK = "link";

    const TOKEN_TYPE_LINK = "link";
    const TOKEN_TYPE_MARKUP = "markup";

    /**
     * Constructor.
     * 
     * @param string $sourceText The source text for the expression.
     * @param string $linkMatches The different token matches for links. 
     * @param string $markupMatches The token matches for various token matches.
     */
    public function __construct($sourceText, $linkMatches, $markupMatches)
    {
        $this->modifiers = [];
        $this->sourceText = $sourceText;

        foreach ($linkMatches as $match) {
            $this->tokens[] = array_merge(["type" => self::TOKEN_TYPE_LINK], $match);
        }

        foreach ($markupMatches as $match) {
            $this->tokens[] = array_merge(["type" => self::TOKEN_TYPE_MARKUP], $match);
        }
    }

    /**
     * Handles JSON Serialization. It will create one level virtual properties for each modifier.
     */
    public function jsonSerialize()
    {
        return [
            self::MODIFIER_STRIP => $this->applyModifier($this->sourceText, self::MODIFIER_STRIP),
            self::MODIFIER_LINK_LINK => $this->applyModifier($this->sourceText, self::MODIFIER_LINK_LINK),
            self::MODIFIER_LINK_TITLE => $this->applyModifier($this->sourceText, self::MODIFIER_LINK_TITLE)
        ];
    }

    /**
     * Handles markdown expression conversion to string. It will apply all set modifiers and return the result as a string.
     * 
     * @return string The input text with the applied modifiers.
     */
    public function __toString()
    {
        return $this->getModifiedText();
    }

    /**
     * Checks if an offset exists.
     * It will in fact check that the specified key exists as a modifier.
     * 
     * @param string $offset The offset/modifier to check the existence of.
     */
    public function offsetExists($offset)
    {
        return in_array($offset, self::getModifiers());
    }

    /**
     * Called when trying to access a part of the markdown expression as an array.
     * In reality, this will add the specified key as a modifier on said expression.
     * 
     * @param string $offset The offset to get / modifier to apply.
     */
    public function offsetGet($offset)
    {
        if($this->offsetExists($offset)) {
            $this->addModifier($offset); 
        }

        return $this;
    }

    /**
     * Tries to set the offset value.
     * This will not do anything since the Markdown expression is read-only.1
     * 
     * @param string $offset The offset to set the value of.
     * @param mixed $value The value to set.
     */
    public function offsetSet($offset, $value)
    {
        return;
    }

    /**
     * Unsets a value at the specified offset. It will remove the modifier from the modifier list.
     * 
     * @param string $offset The offset / modifier to remove. 
     */
    public function offsetUnset($offset)
    {
        if($this->offsetExists($offset)) {
            $this->removeModifier($offset);
        }
    }

    /**
     * Adds a modifier to the modifier list.
     * 
     * @param string $modifier The modifier to add.
     * 
     * @return self
     */
    public function addModifier($modifier)
    {
        if(!in_array($modifier, $this->modifiers)) {
            $this->modifiers[] = $modifier;
        }
        
        return $this;
    }

    /**
     * Removes a modifier from the modifier list.
     * 
     * @param string $modifier The modifier to remove.
     * 
     * @return self
     */
    public function removeModifier($modifier)
    {
        $key = array_search($modifier, $this->modifiers);
        if($key !== false) {
            unset($this->modifiers[$key]);
        }

        return $this;
    }

    /**
     * Applies all enabled modifiers to the source text.
     * 
     * @return string The source text with the modifiers applied.
     */
    public function getModifiedText()
    {
        $text = $this->sourceText;
        foreach($this->modifiers as $modifier) {
            $text = $this->applyModifier($text, $modifier);
        }

        return $text;
    }

    /**
     * Gets all the available modifiers.
     * 
     * @return array The list of all available modifiers.
     */
    public static function getModifiers()
    {
        return [
            self::MODIFIER_STRIP,
            self::MODIFIER_LINK_LINK,
            self::MODIFIER_LINK_TITLE
        ];
    }

    /**
     * Applies a specific modifier to a text.
     * 
     * @param string $text The text to apply the modifier to.
     * @param string $modifier The modifier to apply.
     */
    public function applyModifier($text, $modifier)
    {
        $tokenType = null;
        $replaceKey = [];

        // Set replace parameters depending on the modifier
        switch($modifier) {
            case self::MODIFIER_STRIP:
                $replaceKey = ["title", "content"];
                break;

            case self::MODIFIER_LINK_LINK:
                $tokenType = self::TOKEN_TYPE_LINK;
                $replaceKey = ["link"];
                break;

            case self::MODIFIER_LINK_TITLE:
                $tokenType = self::TOKEN_TYPE_LINK;
                $replaceKey = ["title"];
                break;
        }

        // Apply replacement parameters
        foreach($this->tokens as $token) {
            if(!empty($tokenType) && $token["type"] != $tokenType) {
                continue;
            }
            
            foreach($replaceKey as $key) {
                if(!empty($token[$key])) {
                    $text = str_replace($token[0], $token[$key], $text);
                    break;
                }
            }
        }

        return $text;
    }

    /**
     * Tries to read Markdown from a given text.
     * 
     * @param string $text The text to try to read.
     * 
     * @return MarkdownExpression|null The MarkdownExpression instance if formatting has been found, null if not.
     */
    public static function readMarkdown($text)
    {
        $markupText = $text;
        $matchesCount = preg_match_all('#\[(?P<title>.+)\]\((?P<link>.+)\)#isU', $markupText, $linkMatches, PREG_SET_ORDER); // Links

        // Remove all links from the text before checking for other markups if there are links present
        // This is done because, if there are underscores in the links' titles or URLs, they may be mistaken for markup 
        if($matchesCount > 0) {
            $links = array_column($linkMatches, 0);
            $linkTitles = array_column($linkMatches, 'title');
            $markupText = str_replace($links, $linkTitles, $markupText);
        }
        
        $matchesCount += preg_match_all('#(?P<markup>[*_]{1,2})(?P<content>[^*_]+)\1#isU', $markupText, $markupMatches, PREG_SET_ORDER); // Bold/Italics

        if($matchesCount == 0)
            return null;
        
        return new MarkdownExpression($text, $linkMatches, $markupMatches);
    }
}