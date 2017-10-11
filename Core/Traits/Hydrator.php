<?php
namespace HedgeBot\Core\Traits;

trait Hydrator
{
    public function hydrate($data)
    {
        foreach($this as $key => $val)
        {
            if(isset($data[$key]))
                $this->$key = $data[$key];
        }
    }
}
