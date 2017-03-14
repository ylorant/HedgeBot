<?php
namespace HedgeBot\Core\Tikal;

class CoreAPI
{
    /**
     * Returns true, great to test that the API is replying correctly.
     * @param  string  $data A string the method will return if given.
     * @return boolean       The string it was given, or else, true.
     */
    public function ping($data = null)
    {
        if(is_null($data))
            return true;
        else
            return (string) $data;
    }
}
