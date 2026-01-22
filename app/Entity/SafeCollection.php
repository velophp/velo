<?php

namespace App\Entity;

use Illuminate\Support\Collection;

class SafeCollection extends Collection
{
    public function __get($key)
    {
        $value = $this->get($key);

        // Only convert to collection if accessed and is an array
        if (is_array($value)) {
            $value = new static($value);
            $this->put($key, $value); // Cache it so it's only converted once
        }

        return $value;
    }

    public function toArray()
    {
        return parent::toArray();
    }
}
