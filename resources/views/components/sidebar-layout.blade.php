@props([
    'fullWidth' => false,
    'withNav' => false,
    'collapseText' => 'Collapse',
    'collapseIcon' => 'o-bars-3-bottom-right',
    'collapsible' => false,
    'sidebar' => null,
    'content' => null,
    'footer' => null,
])

@php
    $url = route('mary.toogle-sidebar', absolute: false);
@endphp

<main @class(['w-full mx-auto', 'max-w-screen-2xl' => !$fullWidth])>
    <div @class([
        'drawer lg:drawer-open',
        'drawer-end' => $sidebar?->attributes['right'] ?? false,
        'max-sm:drawer-end' => $sidebar?->attributes['right-mobile'] ?? false,
    ])>
        <input id="{{ $sidebar?->attributes['drawer'] ?? 'main-drawer' }}" type="checkbox" class="drawer-toggle" />

        {{-- CONTENT --}}
        <div
            {{ $content?->attributes?->class(['drawer-content w-full mx-auto p-5 lg:px-10 lg:py-5']) ?? 'class="drawer-content w-full mx-auto p-5 lg:px-10 lg:py-5"' }}>
            {{ $content }}
        </div>

        {{-- SIDEBAR --}}
        @if ($sidebar)
            <div x-data="{
                collapsed: {{ session('mary-sidebar-collapsed', 'false') }},
                collapseText: '{{ $collapseText }}',
                toggle() {
                    this.collapsed = !this.collapsed;
                    fetch('{{ $url }}?collapsed=' + this.collapsed);
                    this.$dispatch('sidebar-toggled', this.collapsed);
                }
            }" @menu-sub-clicked="if(collapsed) { toggle() }" @class([
                'drawer-side z-20 lg:z-auto',
                'top-0 lg:top-[65px] lg:h-[calc(100vh-65px)]' => $withNav,
            ])>
                <label for="{{ $sidebar?->attributes['drawer'] ?? 'main-drawer' }}" aria-label="close sidebar"
                    class="drawer-overlay"></label>

                <div :class="collapsed
                    ?
                    '!w-[62px] [&>*_summary::after]:!hidden [&_.mary-hideable]:!hidden [&_.display-when-collapsed]:!block [&_.hidden-when-collapsed]:!hidden' :
                    '!w-[270px] [&>*_summary::after]:!block [&_.mary-hideable]:!block [&_.hidden-when-collapsed]:!block [&_.display-when-collapsed]:!hidden'"
                    {{ $sidebar->attributes->class([
                        'flex flex-col !transition-all !duration-100 ease-out overflow-x-hidden overflow-y-auto h-screen',
                        'w-[62px] [&>*_summary::after]:hidden [&_.mary-hideable]:hidden [&_.display-when-collapsed]:block [&_.hidden-when-collapsed]:hidden' =>
                            session('mary-sidebar-collapsed') == 'true',
                        'w-[270px] [&>*_summary::after]:block [&_.mary-hideable]:block [&_.hidden-when-collapsed]:block [&_.display-when-collapsed]:hidden' =>
                            session('mary-sidebar-collapsed') != 'true',
                        'lg:h-[calc(100vh-65px)]' => $withNav,
                    ]) }}>
                    <div class="flex-1">
                        {{ $sidebar }}
                    </div>

                    @if ($sidebar->attributes['collapsible'])
                        <x-mary-menu class="hidden lg:block">
                            <x-mary-menu-item @click="toggle"
                                icon="{{ $sidebar->attributes['collapse-icon'] ?? $collapseIcon }}"
                                title="{{ $sidebar->attributes['collapse-text'] ?? $collapseText }}" />
                        </x-mary-menu>
                    @endif
                </div>
            </div>
        @endif
    </div>
</main>

{{-- FOOTER --}}
@if ($footer)
    <footer {{ $footer->attributes->class(['mx-auto w-full', 'max-w-screen-2xl' => !$fullWidth]) }}>
        {{ $footer }}
    </footer>
@endif
