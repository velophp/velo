<?php

use Livewire\Component;

new class extends Component {
    public $projects;

    public function mount()
    {
        $this->projects = \App\Domain\Project\Models\Project::take(67)->get();
    }
};

?>

<x-menu activate-by-route>

    @if ($user = auth()->user())
        <x-menu-separator/>

        <x-list-item :item="$user" value="name" sub-value="email" no-separator no-hover class="-mx-2 -my-2! rounded">
            <x-slot:actions>
                <x-button icon="o-power" class="btn-circle btn-ghost btn-xs" tooltip-left="Log Out" no-wire-navigate
                          link="{{ route('logout') }}"/>
            </x-slot:actions>
        </x-list-item>

        <x-menu-separator/>
    @endif

    <x-menu-item title="Search..." icon="o-magnifying-glass" class="text-gray-500"
                 x-on:click.stop="$dispatch('mary-search-open')"/>

    <x-menu-separator/>

    @foreach ($projects as $project)
        <x-menu-sub :title="$project->name" icon="o-circle-stack" active-by-route>
            @foreach ($project->collections()->oldest()->get() as $c)
                <x-menu-item :title="$c->name" :icon="$c->icon"
                             link="{{ route('collections', ['collection' => $c]) }}"/>
            @endforeach
        </x-menu-sub>
    @endforeach

    <x-menu-sub title="System" icon="o-cog-6-tooth" activate-by-route>
        <x-menu-item title="superusers" icon="o-archive-box" link="{{ route('system.superusers') }}"/>
        <x-menu-item title="authSessions" icon="o-archive-box" link="{{ route('system.sessions') }}"/>
        <x-menu-item title="realtimeConnections" icon="o-archive-box" link="{{ route('system.realtime') }}"/>
        <x-menu-item title="otps" icon="o-archive-box" link="{{ route('system.otps') }}"/>
    </x-menu-sub>

    <x-menu-separator/>

    <x-menu-item title="Create Collection" icon="o-plus" x-on:click="$dispatch('create-collection')"/>

    <x-menu-separator/>

    <x-menu-item icon="lucide.chart-line" title="Logs" link="{{ route('system.logs') }}" activate-by-route/>
    <x-menu-item icon="lucide.settings-2" title="Settings" link="{{ route('system.settings') }}" activate-by-route/>

    <x-menu-separator/>

    @persist('sidebar')
    <div class="px-4 py-2">
        <x-theme-toggle darkTheme="dark" lightTheme="light"/>
    </div>
    @endpersist

</x-menu>
