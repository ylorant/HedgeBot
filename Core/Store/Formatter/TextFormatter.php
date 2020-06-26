<?php
namespace HedgeBot\Core\Store\Formatter;

use HedgeBot\Core\Store\Store;

/**
 * Store text formatter.
 *
 * This class helps format text according to store data.
 * It will replace tokens that are in the form of $path.to.var.in.store
 * by the subsequent value of the token. See doc for the format() method for more info.
 */
class TextFormatter extends TraverseFormatter implements FormatterInterface
{
    /** @var Store The store that htis formatter will take its data from */
    protected $store;
    
    /**
     * Constructor. Creates a new formatter, using the given store to provide the values to fill.
     *
     * @param Store $store The store to use to get the data.
     */
    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    /**
     * Formats a text with available store data.
     * To insert a token (variable) that will be replaced by store data, prefix its path in the
     * store by the dollar "$" character. To traverse arrays, just use the dot "." character.
     * To escape a text that could be
     * interpreted as a token, just prefix it with a backslash "\".
     *
     * Some token examples:
     * - Basic token: $nickname
     * - Nested token: $Horaro.schedule.title
     * - Escaped text: \$notParsed
     *
     * @param string $text The text to format.
     * @param string $channel The channel to which restrict/specialize the dataset.
     *                        This will probably affect wether the tokens will be able
     *                        to be replaced or not.
     * @param string $root Set a root for token path walking.
     *                     Useful to restrict tokens to a specific item, or to allow having shorter tokens
     *                     without redundant path parts.
     *
     * @return string The formatted text.
     *                Tokens that haven't been found (or strictly equal to null) in the store will not be replaced.
     */
    public function format($text, $channel = null, $root = "")
    {
        // Getting the list of tokens in the text, and checking that there is some, check class doc to see why this regexp is here
        $simpleMatches = null;
        $compoundMatches = null;

        $hasSimpleMatches = preg_match_all(
            "#(?<!\\\\)\\$((?:(?:[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)|\\.)+)#",
            $text,
            $simpleMatches
        );

        $hasCompoundMatches = preg_match_all(
            "#(?<!\\\\)\\$\{((?:(?:[a-zA-Z_\x7f-\xff][ a-zA-Z0-9_\x7f-\xff]*)|\.)+)\}#isU",
            $text,
            $compoundMatches
        );

        // No match, we return the verbatim text
        if (!$hasSimpleMatches && !$hasCompoundMatches) {
            return $text;
        }

        $matches = array_merge($simpleMatches[0], $compoundMatches[0]);

        // Remove duplicate tokens, since once searched for, we will replace all occurences in the text at once
        $tokenList = array_unique($matches);
        $storeData = $this->store->getData($channel);

        // Restricting the data to its subset for the given root if needed
        if (!empty($root)) {
            $storeData = $this->traverse($root, $storeData);
            // The root is empty, no replacement is done
            if ($storeData === null) {
                return $text;
            }
        }

        // Find token values and replace them in the text
        foreach ($tokenList as $token) {
            $value = $this->traverse($token, $storeData);
            $text = str_replace($token, $value, $text);
        }

        return $text;
    }
}
