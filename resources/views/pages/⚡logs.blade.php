<?php

use Livewire\Component;

new class extends Component {
    public $selectedTab = 'pulse-tab';

    public $breadcrumbs = [];

    public function mount(): void
    {
        $this->breadcrumbs = [
            ['link' => route('home'), 'icon' => 's-home'],
            ['label' => 'System'],
            ['label' => 'Logs'],
        ];
    }
};
?>

<div>
    <div class="flex justify-between flex-wrap">
        <div class="flex items-center gap-4">
            <x-breadcrumbs :items="$breadcrumbs" />
        </div>
    </div>

    <div class="my-8"></div>

    <x-tabs wire:model="selectedTab">
        <x-tab name="requests-tab" label="Requests" icon="o-newspaper">
            <div>Requests (coming soon)</div>
        </x-tab>
        <x-tab name="pulse-tab" label="Laravel Pulse">
            <div class="mockup-browser border-base-300 border w-full">
                <div class="mockup-browser-toolbar">
                    <div class="input">{{ url(config('pulse.path')) }}</div>
                </div>
                <iframe src="{{ url(config('pulse.path')) }}" width="100%" frameborder="0" class="rounded h-[calc(100vh-275px)]"></iframe>
            </div>
        </x-tab>
    </x-tabs>
</div>
