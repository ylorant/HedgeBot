<?php
namespace HedgeBot\Core\Store\Formatter;

use HedgeBot\Core\Store\Store;

/**
 * Store traverse formatter.
 *
 * This class will perform path resolution for var names through the store data structure.
 * 
 * TODO: Add the store as prop.
 */
class TraverseFormatter implements FormatterInterface
{
    const PATH_SEPARATOR = ".";

    /**
     * {@inheritdoc}
     */
    public static function getName()
    {
        return "traverseFormatter";
    }

    /**
     * Walks the given path in the given data array, and returns the found value.
     *
     * @param string $path The path to walk. Go through recursive paths using the dot "." separator between key names.
     * @param array $data The data to walk through.
     * @param mixed $default The default value to return when a path is not found in the data. Defaults to null.
     *
     * @return mixed The found data, or the default value if the path has not been found.
     */
    public function traverse($path, $data, $default = null)
    {
        $pathParts = explode(self::PATH_SEPARATOR, $path);
        $currentData = $data;

        // Iterating over the path parts to walk down the initial data to the requested element
        foreach ($pathParts as $part) {
            // Before trying to check as an array, we try to see if it's a string readable as markdown, to generate a Markdown
            // parser from it.
            if (is_string($currentData)) {
                $markdownExpression = MarkdownExpression::readMarkdown($currentData);
                if($markdownExpression) {
                    $currentData = $markdownExpression;
                }
            }

            // If the part isn't present in the data, we stop walking and return the default value
            if (!isset($currentData[$part])) {
                return $default;
            }

            $currentData = $currentData[$part];
        }

        // We try to remove markdown markers from strings
        if (is_string($currentData) && strpos($currentData, '*') !== false) {
            $currentData = preg_replace("#\*{1,2}(.+)\*{1,2}#isU", '$1', $currentData);
        }

        return $currentData;
    }
}