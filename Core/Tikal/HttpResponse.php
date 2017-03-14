<?php
namespace HedgeBot\Core\Tikal;

class HttpResponse
{
    public $request;

    public $statusCode;
    public $headers = [];
    public $data;

    // Some common status codes
    const OK = 200;
    const BAD_REQUEST = 400;
    const UNAUTHORIZED = 401;
    const FORBIDDEN = 403;
    const NOT_FOUND = 404;
    const SERVER_ERROR = 500;

    // Status strings for these codes
    const STATUS_MESSAGES = [
        200 => "OK",
        400 => "Bad Request",
        401 => "Unauthorized",
        403 => "Forbidden",
        404 => "Not Found",
        500 => "Internal Server Error"
    ];

    public function __construct(HttpRequest $request)
    {
        $this->request = $request;
    }

    /**
     * Generates the response as a string.
     * @return string The HTTP response message string.
     */
    public function generate()
    {
        $response = "";
        $data = $this->data;

        if(!is_string($this->data) && (!empty($this->headers['Content-Type']) || !empty($this->request->headers['Accept'])))
        {
            $contentType = !empty($this->headers['Content-Type']) ? $this->headers['Content-Type'] : $this->request->headers['Accept'];
            $this->headers['Content-Type'] = $contentType;

            switch($contentType)
            {
                case 'application/json':
                    $data = json_encode($this->data);
                    break;
            }
        }

        if(empty($this->headers['Content-Type']))
            $this->contentType = "text/plain";

        if(empty($this->statusCode))
            $this->statusCode = 200;

        if(!empty($data))
            $this->headers['Content-Length'] = strlen($data);
        else
            $this->headers['Content-Length'] = 0;

        $response .= "HTTP/1.1 ". $this->statusCode. " ". self::STATUS_MESSAGES[$this->statusCode]. "\r\n";

        foreach($this->headers as $name => $value)
            $response .= $name. ": ". $value. "\r\n";

        if(!empty($data))
            $response .= "\r\n". $data;

        return $response;
    }
}
