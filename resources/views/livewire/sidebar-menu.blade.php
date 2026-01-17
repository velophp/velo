<?php

use function Livewire\Volt\{state, mount};
use App\Enums\CollectionType;

state(['projects' => []]);

mount(fn() => $this->projects = \App\Models\Project::get());

function getIcon($type)
{
    return match ($type) {
        CollectionType::Auth => 'o-users',
        CollectionType::View => 'o-table-cells',
        default => 'o-archive-box',
    };
}

?>

<x-menu activate-by-route>

    @if($user = auth()->user())
        <x-menu-separator />

        <x-list-item :item="$user" value="name" sub-value="email" no-separator no-hover class="-mx-2 -my-2! rounded">
            <x-slot:actions>
                <x-button icon="o-power" class="btn-circle btn-ghost btn-xs" tooltip-left="Log Out" no-wire-navigate
                    link="{{ route('logout') }}" />
            </x-slot:actions>
        </x-list-item>

        <x-menu-separator />
    @endif

    <x-menu-item title="Search..." icon="o-magnifying-glass" class="text-gray-500" x-on:click.stop="$dispatch('mary-search-open')" />

    <x-menu-separator />

    @foreach ($projects as $project)
        <x-menu-sub :title="$project->name" icon="o-circle-stack" :open="$loop->first" active-by-route>
            @foreach ($project->collections()->oldest()->get() as $c)
                <x-menu-item :title="$c->name" :icon="getIcon($c->type)" link="{{ route('collections', ['collection' => $c]) }}" />
            @endforeach
        </x-menu-sub>
    @endforeach

    <x-menu-sub title="System" icon="o-cog-6-tooth" activate-by-route>
        <x-menu-item title="superusers" icon="o-archive-box" link="{{ route('collections.superusers') }}" />
    </x-menu-sub>

    <x-menu-separator />

    <x-menu-item title="Create Collection" icon="o-plus" x-on:click="$dispatch('create-collection')" />

</x-menu>
