<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<x-custom-main :fullWidth="true">
    <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100 lg:bg-inherit">

        <x-app-brand class="px-5 pt-4" />

        <livewire:sidebar-menu />
    </x-slot:sidebar>

    <x-slot:content>
        {{ $slot }}
    </x-slot:content>
</x-custom-main>