<x-menu activate-by-route>

    {{-- User --}}
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

    <x-menu-item title="Find collections..." icon="o-magnifying-glass" class="text-gray-500" />

    @foreach (\App\Models\Project::get() as $project)
        <x-menu-sub :title="$project->name" icon="o-circle-stack" :open="$loop->first">
            @foreach ($project->collections as $collection)
                <x-menu-item :title="$collection->name" icon="o-archive-box" link="####" />
            @endforeach
        </x-menu-sub>
    @endforeach

    <x-menu-separator />

    <x-menu-sub title="System" icon="o-cog-6-tooth">
        <x-menu-item title="superusers" icon="o-archive-box" link="####" />
    </x-menu-sub>

    <x-menu-separator />

    <x-menu-item title="Create Collection" icon="o-plus" link="####" />

</x-menu>
