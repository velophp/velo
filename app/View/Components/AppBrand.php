<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class AppBrand extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return <<<'blade'
            <a href="{{ route('home') }}" wire:navigate>
                <!-- Hidden when collapsed -->
                <div {{ $attributes->class(["hidden-when-collapsed"]) }}>
                    <div class="flex items-center gap-2 w-fit">
                        <x-icon name="o-cube" class="w-6 -mb-1.5 text-blue-500" />
                        <span class="font-bold text-3xl me-3 bg-linear-to-r from-blue-500 to-blue-400 bg-clip-text text-transparent ">
                            {{ config('app.name') }}
                        </span>
                    </div>
                </div>

                <!-- Display when collapsed -->
                <div class="display-when-collapsed hidden mx-5 mt-5 mb-1 h-7">
                    <x-icon name="s-cube" class="w-6 -mb-1.5 text-blue-500" />
                </div>
            </a>
        blade;
    }
}
