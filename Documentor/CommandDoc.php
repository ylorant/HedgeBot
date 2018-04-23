<?php

namespace HedgeBot\Documentor;

use ReflectionMethod;

/**
 * Class CommandDoc
 * @package HedgeBot\Documentor
 */
class CommandDoc
{
    private $reflectionMethod;

    private $commandInfo;
    private $name;
    private $prototype;

    public function __construct(ReflectionMethod $reflection)
    {
        $this->reflectionMethod = $reflection;
        $this->readCommandInfo();
    }

    /**
     * Reads the command info from the doc comments of its method.
     */
    public function readCommandInfo()
    {
        // Getting info from comment
        $docComment = $this->reflectionMethod->getDocComment();
        $this->commandInfo = DocParser::parse($docComment);

        // Getting command name
        $methodName = $this->reflectionMethod->getName();
        preg_match('#Command([a-zA-Z0-9]+)#', $methodName, $matches);

        $this->name = "!" . lcfirst($matches[1]);

        // Generating prototype from name and arguments
        $this->prototype = $this->name . " ";

        if (!empty($this->commandInfo['parameter'])) {
            foreach ($this->commandInfo['parameter'] as $parameter) {
                list($parameterName, $parameterDesc) = explode(' ', $parameter, 2);
                $this->prototype .= "[" . $parameterName . "] ";
            }
        }

        $this->prototype = trim($this->prototype);
    }


    /**
     * Generates doc when converted to string.
     */
    public function __toString()
    {
        $doc = "";

        // Command title, 3rd level title
        $doc .= "### " . $this->name . "\n";

        if (!empty($this->commandInfo['access']) && $this->commandInfo['access'][0] != "user") {
            switch ($this->commandInfo['access'][0]) {
                case 'moderator':
                    $doc .= "*This command can only be used by moderators.*";
                    break;
                case 'streamer':
                    $doc .= "*This command can only be used by the streamer.*";
                    break;
            }

            $doc .= "\n";
        }

        // Command description
        if (!empty($this->commandInfo['description'])) {
            $doc .= join("\n\n", $this->commandInfo['description']) . "\n\n";
        } else {
            $doc .= "\n";
        }

        // Command prototype
        $doc .= "#### Usage\n";
        $doc .= "```\n" . $this->prototype . "\n```\n\n";

        // Command parameters
        if (!empty($this->commandInfo['parameter'])) {
            $doc .= "#### Parameters\n\n";

            foreach ($this->commandInfo['parameter'] as $commandParameter) {
                $commandParameter = explode(' ', $commandParameter, 2);
                $doc .= "##### " . $commandParameter[0] . "\n";
                $doc .= $commandParameter[1] . "\n\n";
            }
        }

        return $doc;
    }
}
