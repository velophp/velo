<?php

namespace App\Delivery\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class AppBrand extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct()
    {
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|\Closure|string
    {
        return <<<'blade'
            <a href="{{ route('home') }}" wire:navigate>
                <!-- Hidden when collapsed -->
                <div {{ $attributes->class(["hidden-when-collapsed"]) }}>
                    <div class="flex items-center gap-2 w-fit">
                        <img src="{{ asset('assets/velo.svg') }}" class="w-4 -mb-1.5 " />
                    </div>
                </div>

                <!-- Display when collapsed -->
                <div class="display-when-collapsed hidden mx-5 mt-5 mb-1 h-7">
                    <img src="{{ asset('assets/velo.svg') }}" class="w-6 -mb-1.5 " />
                </div>
            </a>
        blade;
    }
}
