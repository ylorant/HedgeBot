<?php
namespace HedgeBot\Core\Service\Horaro;

use HedgeBot\Core\HedgeBot;

class Horaro
{
    const HORARO_HOST = "https://horaro.org";
    const HORARO_BASEURL = self::HORARO_HOST. "/-/api/v1/";
    const RETURN_MIMETYPE = "application/json";

    /** Executes a query on the Horaro API.
     * This method executes a direct query on the horaro API. It's not usually meant to be used from the outside,
     * since the other methods provide calls to specific API methods.
     *
     * @param string $url        The endpoint to query. Auto-magically builds the correct path.
     * @param array  $parameters The parameters to give to the query, as a key-value array. Optionnal.
     * @return object            The API response, as an object translated from the JSON.
     */
    public function query($url, array $parameters = [])
    {
        // For GET queries, append parameters to url as query parameters
        if(!empty($parameters))
        {
            if(strpos($url, '?') === false)
                $url .= '?';

            $url .= http_build_query($parameters);
        }

        // Build the URL if the host ain't there
        if(strpos($url, self::HORARO_HOST) === FALSE) {
            $url = self::HORARO_BASEURL. trim($url, '/');
        }

        $curl = curl_init($url);

        HedgeBot::message("Horaro API Call: $0", [$url], E_DEBUG);

        // Set base common options
        curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => [
                    "Accept: ". self::RETURN_MIMETYPE
                ]
            ]
        );

        $reply = curl_exec($curl);
        $data = json_decode($reply);

        // If there's a status replied and it's not 200 (HTTP OK), then it's an error and we return false
        if(!empty($data->status) && $data->status != 200)
            return false;
        
        // If no data has been returned but there's still links, then we're redirected to the ID based event.
        if(!isset($data->data) && !empty($data->links))
            return $this->query(self::HORARO_HOST. $data->links[0]->uri);
        
        return $data->data;
    }

    /** Fetches events.
     * This method fetches the events available on the Horaro API. By default
     * 
     * @param string $name       Filter the events by name.
     * @param array  $parameters Additional filters, for example for pagination.
     * 
     * @return array The events corresponding to the filter.
     */
    public function getEvents($name = null, array $parameters = [])
    {
        if(!empty($name))
            $parameters['name'] = $name;

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
        $reply = $this->query("/events/". $id);

        return $reply->data;
    }

    /** Gets the schedule list for an event.
     * This method gets the available schedules for a defined event ID.
     * 
     * @param string $eventId    The event ID.
     * @param array  $parameters Additional filters, for example for pagination.
     * 
     * @return array A list of the schedules for the given event.
     */
    public function getSchedules($eventId, array $parameters = [])
    {
        // Get the event ID beforehand
        $reply = $this->query("/events/". $eventId. "/schedules", $parameters);
        
        return $reply->data;
    }

    /** Gets a single schedule for an event.
     * This method retrieves details for a single schedule. The event ID can be provided to ensure that the schedule
     * belongs to the right event.
     * 
     * @param string $scheduleId The schedule ID.
     * @param string $eventId    The event ID. Using it or not will change the called method.
     * @param string $hiddenKey  Key to get the hidden columns.
     * 
     * @return object The schedule.
     */
    public function getSchedule($scheduleId, $eventId = null, $hiddenKey = null)
    {
        if(!empty($eventId))
            return $this->query("/events/". $eventId. "/schedules/". $scheduleId);
        else
            return $this->query("/schedules/". $scheduleId);
    }

    /** Gets the ticker for a schedule.
     * This method gets the ticker for a particular schedule. A ticker is the current state of a running schedule.
     * It contains info about the current run, the previous run and the upcoming run.
     * 
     * @param string $scheduleId The schedule ID.
     * @param string $eventId    The event ID. Using it or not will change the called method.
     * 
     * @return object The ticker.
     */
    public function getTicker($scheduleId, $eventId = null)
    {
        if(!empty($eventId))
            return $this->query("/events/". $eventId. "/schedules/". $scheduleId. "/ticker");
        else
            return $this->query("/schedules/". $scheduleId. "/ticker");
    }
}