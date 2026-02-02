<?php

namespace App\Delivery\Entity;

use Illuminate\Support\Collection;
use Livewire\Wireable;

class SafeCollection extends Collection implements Wireable
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

    public function toLivewire(): array
    {
        return $this->toArray();
    }

    public static function fromLivewire($value): static
    {
        return new static($value);
    }
}
