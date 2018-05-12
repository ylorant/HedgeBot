<?php

namespace HedgeBot\Documentor;

/**
 * Class DocParser
 * @package HedgeBot\Documentor
 */
class DocParser
{
    const DOC_COMMAND_CHAR = "@";

    /**
     * @param $text
     * @return array
     */
    public static function parse($text)
    {
        $parsedTokens = [
            "description" => []
        ];

        // Remove base comments
        $text = str_replace(['/**', '*/'], '', $text);

        // Init current description token, for command-less
        $currentDescToken = "";

        // Parse the comment
        $lines = explode("\n", $text);
        for ($i = 0; $i < count($lines); $i++) {
            $line = &$lines[$i];

            // Remove leading asterisks from each line
            $line = self::trimLine($line);

            // Line is empty, flush the current description token and loop
            if (empty($line)) {
                if (!empty($currentDescToken)) {
                    $parsedTokens["description"][] = trim($currentDescToken);
                    $currentDescToken = "";
                }

                continue;
            }

            // Commands have to have the command char as the leading character of the line
            if ($line[0] == self::DOC_COMMAND_CHAR) {
                // Get command name and parameters
                $command = explode(' ', $line, 2);
                $command[0] = substr($command[0], strlen(self::DOC_COMMAND_CHAR));

                if (!isset($parsedTokens[$command[0]])) {
                    $parsedTokens[$command[0]] = [];
                }

                $commandContent = $command[1];

                // Parse the next line as the command content until we encounter an empty line
                // or until the next command
                while (!empty($lines[$i + 1])) {
                    $i++;
                    $line = self::trimLine($lines[$i]);

                    if (empty($line) || $line[0] == self::DOC_COMMAND_CHAR) {
                        $i--;
                        break;
                    }

                    $commandContent .= "\n" . $line;
                }

                $parsedTokens[$command[0]][] = $commandContent;
            } else // If it's not recognized as a command, add it as a description token
            {
                $currentDescToken .= $line . " ";
            }
        }

        return $parsedTokens;
    }

    /**
     * @param $line
     * @return null|string|string[]
     */
    private static function trimLine($line)
    {
        $line = trim($line, "* \n\t\r\0\x0B");
        $line = preg_replace('#\s+#', ' ', $line);

        return $line;
    }
}
