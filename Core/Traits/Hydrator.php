<?php

namespace HedgeBot\Core\Traits;

/**
 * Trait Hydrator
 * @package HedgeBot\Core\Traits
 */
trait Hydrator
{
    /**
     * @param $data
     */
    public function hydrate($data)
    {
        foreach ($this as $key => $val) {
            if (isset($data[$key])) {
                $this->$key = $data[$key];
            }
        }
    }
}
