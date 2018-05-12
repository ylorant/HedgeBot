<?php

namespace HedgeBot\Core\Service\Horaro;

use HedgeBot\Core\HedgeBot;

/**
 * Class Horaro
 * @package HedgeBot\Core\Service\Horaro
 */
class Horaro
{
    /** @var string Horaro host URL */
    const HORARO_HOST = "https://horaro.org";
    /** @var string Full Horaro API location URL, build with help from HORARO_HOST */
    const HORARO_BASEURL = self::HORARO_HOST . "/-/api/v1/";
    /** @var string The mimetype expected for return values. Will be used in the "Accept" header. */
    const RETURN_MIMETYPE = "application/json";

    /** @var Resource Multi curl resource, for async requests */
    protected $multiCurl;
    /** @var array List of the currently pending asynchronous requests. */
    protected $asyncRequests;
    /** @var callable The callback that will be called when an error occured (error handler). */
    protected $errorHandler;

    /** Constructor.
     * The constructor initializes the curl asynchronous handler.
     */
    public function __construct()
    {
        $this->asyncRequests = [];
        $this->multiCurl = curl_multi_init();
    }

    /**
     * Sets the error handler for the async requests.
     * This method will set the error handling callback that the async listener will call upon
     * failure of a request.
     * When called, the error handler will be given the following parameters:
     *   - The reply code from cURL
     *   - The cURL handler
     *   - The parameters that would've been given to the initial callback.
     *   - The reply data
     *
     * @param $errorHandler
     */
    public function setErrorHandler($errorHandler)
    {
        $this->errorHandler = $errorHandler;
    }

    /** Calls the error handler.
     * Calls the class' error handler with the given curl info, and the specified request.
     *
     * @param int $error The error code.
     * @param resource $handler The cURL handler if the errors comes from a specific request. Optional.
     * @param array $params The callback params. Optional.
     * @param object $reply The reply from the Horaro API, when the error comes from tha API itself. Optional.
     */
    protected function callErrorHandler($error, $handler = null, $params = null, $reply = null)
    {
        $handlerParams = [$error];

        // If the handler is present, that's a specific request that has failed.
        if (!empty($handler)) {
            $handlerParams[] = $handler;
        }

        // Give the callback parameters if given by the calling code
        if (!empty($params)) {
            $handlerParams[] = $params;
        }

        // If the reply comes from the API, embed its response into the handler params.
        if (!empty($reply)) {
            $handlerParams[] = $reply;
        }

        if (is_callable($this->errorHandler)) {
            call_user_func_array($this->errorHandler, $handlerParams);
        }
    }

    /**
     * Listens for cURL asynchronous replies.
     * This method listens for replies on the pending queries. Upon completion of one of them, it'll
     * execute the associated callback and remove it from the pending queries.
     *
     * @return bool return false only if no curl info returned
     */
    public function asyncListen()
    {
        $readPending = null;
        $execState = curl_multi_exec($this->multiCurl, $readPending);

        // If the has been an error handling the stack, call the error handler
        if ($execState != CURLM_OK) {
            return $this->callErrorHandler($execState);
        }

        $curlInfo = curl_multi_info_read($this->multiCurl);

        // If no curl info has been returned, that means that no request has finished, hence we return
        if (empty($curlInfo)) {
            return false;
        }

        // Cycle through the current handles to get the one that has completed
        $foundIndex = null;
        foreach ($this->asyncRequests as $index => $request) {
            if ($request['curl'] == $curlInfo['handle']) {
                $foundIndex = $index;

                // Handle errors by calling the error handler with the callback parameters
                if ($curlInfo['result'] != CURLE_OK) {
                    $this->callErrorHandler($curlInfo['result'], $curlInfo['handle'], $request['cbParams']);
                    break;
                }

                $reply = curl_multi_getcontent($curlInfo['handle']);
                $data = json_decode($reply);


                // If there's a status replied and it's not 200 (HTTP OK),
                //then it's an error and we trigger the error handler
                if (!empty($data->status) && $data->status != 200) {
                    $this->callErrorHandler($curlInfo['result'], $curlInfo['handle'], $request['cbParams'], $data);
                    break;
                }

                // If no data has been returned but there's still links,
                //we chain another async query for the referred URL
                if (!isset($data->data) && !empty($data->links)) {
                    $this->queryAsync(
                        self::HORARO_HOST . $data->links[0]->uri,
                        [],
                        $request['cb'],
                        $request['cbParams']
                    );
                    break;
                }

                $request['cbParams'][] = $data->data;

                // Call the callback only if it was provided to the first called method
                if (!empty($request['cb'])) {
                    call_user_func_array($request['cb'], $request['cbParams']);
                }

                break;
            }
        }

        // Finally, if the index has been found and handled,
        //we need to remove the request from the pending request list to avoid buildup.
        if (!is_null($foundIndex)) {
            unset($this->asyncRequests[$foundIndex]);
        } else {
            HedgeBot::message(
                "A request was completed but it looks like it doesn't belong to any pending query.",
                [],
                E_DEBUG
            );
        }
    }

    /**
     * Executes an asynchronous query on the Horaro API.
     * This method starts the execution of a delayed query on the Horaro API.
     * It's not usually meant to be used from the outside,
     * since the other methods provide calls so specific API methods.
     * This method will return directly and call the callback when the replay has been given by the server.
     *
     * @param string $url The endpoint to query. Auto-magically builds the correct path.
     * @param array $parameters The parameters to give to the query, as a key-value array. Optionnal.
     * @param callable $callback The callback to call when the request completes.
     * @param array $cbParameters
     */
    public function queryAsync($url, array $parameters, $callback, $cbParameters = [])
    {
        $curl = $this->buildQuery($url, $parameters);

        HedgeBot::message("Horaro async API Call: $0", [$url], E_DEBUG);

        // Store the async request for ulterior checks
        $this->asyncRequests[] = ['curl' => $curl, 'cb' => $callback, 'cbParams' => $cbParameters];

        curl_multi_add_handle($this->multiCurl, $curl);
    }

    /**
     * Executes a synchronous query on the Horaro API.
     * This method executes a direct synchronous query on the horaro API.
     * It's not usually meant to be used from the outside,
     * since the other methods provide calls to specific API methods.
     * This method will block until the server has replied something.
     *
     * @param string $url The endpoint to query. Auto-magically builds the correct path.
     * @param array $parameters The parameters to give to the query, as a key-value array. Optionnal.
     * @return object|bool The API response, as an object translated from the JSON.
     */
    public function query($url, array $parameters = [])
    {
        $curl = $this->buildQuery($url, $parameters);

        HedgeBot::message("Horaro API Call: $0", [$url], E_DEBUG);

        $reply = curl_exec($curl);
        $data = json_decode($reply);

        // If there's a status replied and it's not 200 (HTTP OK), then it's an error and we return false
        if (!empty($data->status) && $data->status != 200) {
            return false;
        }

        // If no data has been returned but there's still links, then we're redirected to the ID based event.
        if (!isset($data->data) && !empty($data->links)) {
            return $this->query(self::HORARO_HOST . $data->links[0]->uri);
        }

        return $data->data;
    }

    /** Builds an HTTP query for the Horaro API.
     * This method builds a cURL query directed to the Horaro API and then returns the cURL resource.
     *
     * @param string $url The endpoint to query. Auto-magically builds the correct path.
     * @param array $parameters The parameters to give to the query, as a key-value array. Optionnal.
     *
     * @return Resource The cURL resource representing the query.
     */
    protected function buildQuery($url, array $parameters = [])
    {
        // For GET queries, append parameters to url as query parameters
        if (!empty($parameters)) {
            if (strpos($url, '?') === false) {
                $url .= '?';
            }

            $url .= http_build_query($parameters);
        }

        // Build the URL if the host ain't there
        if (strpos($url, self::HORARO_HOST) === false) {
            $url = self::HORARO_BASEURL . trim($url, '/');
        }

        $curl = curl_init($url);

        // Set base common options
        curl_setopt_array(
            $curl,
            [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => [
                    "Accept: " . self::RETURN_MIMETYPE
                ]
            ]
        );

        return $curl;
    }

    /** Fetches events.
     * This method fetches the events available on the Horaro API. By default
     *
     * @param string $name Filter the events by name.
     * @param array $parameters Additional filters, for example for pagination.
     *
     * @return array The events corresponding to the filter.
     */
    public function getEvents($name = null, array $parameters = [])
    {
        if (!empty($name)) {
            $parameters['name'] = $name;
        }

        $result = $this->query("/events", $parameters);

        return $result->data;
    }

    /** Gets a single event.
     * This method retrieves details about a single event.
     *
     * @param string $id The ID of the event.
     *
     * @return object The event.
     */
    public function getEvent($id)
    {
        $reply = $this->query("/events/" . $id);

        return $reply->data;
    }

    /** Gets the schedule list for an event.
     * This method gets the available schedules for a defined event ID.
     *
     * @param string $eventId The event ID.
     * @param array $parameters Additional filters, for example for pagination.
     *
     * @return array A list of the schedules for the given event.
     */
    public function getSchedules($eventId, array $parameters = [])
    {
        // Get the event ID beforehand
        $reply = $this->query("/events/" . $eventId . "/schedules", $parameters);

        return $reply->data;
    }

    /** Gets a single schedule for an event.
     * This method retrieves details for a single schedule. The event ID can be provided to ensure that the schedule
     * belongs to the right event.
     *
     * @param string $scheduleId The schedule ID.
     * @param string $eventId The event ID. Using it or not will change the called method.
     *
     * @return object The schedule.
     */
    public function getSchedule($scheduleId, $eventId = null)
    {
        if (!empty($eventId)) {
            return $this->query("/events/" . $eventId . "/schedules/" . $scheduleId);
        } else {
            return $this->query("/schedules/" . $scheduleId);
        }
    }

    /** Gets the ticker for a schedule.
     * This method gets the ticker for a particular schedule. A ticker is the current state of a running schedule.
     * It contains info about the current run, the previous run and the upcoming run.
     *
     * @param string $scheduleId The schedule ID.
     * @param string $eventId The event ID. Using it or not will change the called method.
     *
     * @return object The ticker.
     */
    public function getTicker($scheduleId, $eventId = null)
    {
        if (!empty($eventId)) {
            return $this->query("/events/" . $eventId . "/schedules/" . $scheduleId . "/ticker");
        } else {
            return $this->query("/schedules/" . $scheduleId . "/ticker");
        }
    }

    /** Gets a single schedule for an event, asynchronously.
     * This method starts a query to the Horaro API to get a schedule, and will call the given
     * callback upon its completion.
     *
     * @param string $scheduleId The schedule ID.
     * @param string $eventId The event ID. Using it or not will change the called method.
     * @param string $hiddenKey Key to get the hidden columns.
     * @param callable $callback The callback to call when the request completes.
     */
    public function getScheduleAsync($scheduleId, $eventId = null, $hiddenKey = null, $callback = null)
    {
        if (!empty($eventId)) {
            $this->queryAsync("/events/" . $eventId . "/schedules/" . $scheduleId, [], $callback,
                [$scheduleId, $eventId]);
        } else {
            $this->queryAsync("/schedules/" . $scheduleId, [], $callback, [$scheduleId, $eventId]);
        }
    }
}