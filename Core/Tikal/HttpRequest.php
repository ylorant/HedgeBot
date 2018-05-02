<?php

namespace HedgeBot\Core\Tikal;

/**
 * Class HttpRequest
 * @package HedgeBot\Core\Tikal
 */
class HttpRequest
{
    private $clientId;

    private $headers = [];
    private $host;
    private $port;

    private $method;
    private $completeURL; // Requested path with the host appended
    private $requestURI; // Requested path from the query as-is
    private $contentType;
    private $keepAlive;

    private $data;
    private $raw;
    private $rawData;

    /**
     * HttpRequest constructor.
     * @param $clientId
     * @param null $data
     */
    public function __construct($clientId, $data = null)
    {
        $this->clientId = $clientId;

        if (!empty($data)) {
            $this->parse($data);
        }
    }

    /**
     * Getter that works for all properties. Basically they're read-only since there isn't any setter method.
     * @param  string $name Then ame of the property
     * @return mixed        The property value, or NULL if it doesn't exist.
     */
    public function __get($name)
    {
        if (isset($this->$name)) {
            return $this->$name;
        }

        return null;
    }

    /**
     * @param $url
     */
    public function setRequestURI($url)
    {
        $this->requestURI = $url;
        $this->completeURL = $this->host . $this->requestURI;
    }

    /**
     * Parses an HTTP request
     * @param  string $query The request to parse
     * @return HttpRequest Self-reference, for chaining.
     */
    public function parse($query)
    {
        $this->raw = $query;
        $this->headers = [];
        $query = explode("\r\n\r\n", $query);
        $metadata = explode("\r\n", $query[0]);

        // Parse metadata
        foreach ($metadata as $row) {
            $saveHeader = true;

            $row = explode(' ', $row, 2);
            switch ($row[0]) {
                case 'POST':
                    $this->rawData = $this->data = $query[1];
                case 'GET': //It's a GET request (main parameter)
                    $this->method = $row[0];
                    $uri = explode(' ', $row[1]);
                    $this->requestURI = $uri[0];
                    $saveHeader = false;
                    break;
                case 'Host:':
                    $host = explode(':', $row[1], 2);
                    $this->host = $host[0];
                    $this->port = isset($host[1]) ? $host[1] : null;
                    break;
                case 'Connection:':
                    if ($row[1] == 'keep-alive') {
                        $this->keepAlive = true;
                    }
                    break;
                case 'Content-Type:':
                    $this->contentType = $row[1];
                    break;
            }

            if ($saveHeader) {
                $this->headers[substr($row[0], 0, -1)] = $row[1];
            }
        }

        // Parse data if needed
        if (!empty($this->contentType) && $this->contentType == 'application/json') {
            $this->data = json_decode($this->data);
        }

        $this->completeURL = $this->host . $this->requestURI;
        return $this;
    }
}
