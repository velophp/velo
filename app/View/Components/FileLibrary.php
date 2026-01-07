<?php
# Source: MaryUI Image Library

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FileLibrary extends Component
{
    public string $uuid;

    public string $mimes = '*';

    public function __construct(
        public ?int $maxFiles = 1,
        public ?string $id = null,
        public ?string $label = null,
        public ?string $hint = null,
        public ?bool $hideErrors = false,
        public ?bool $hideProgress = false,
        public ?string $changeText = "Change",
        public ?string $removeText = "Remove",
        public ?string $addFilesText = "Add file",
        public Collection $preview = new Collection(),

    ) {
        $this->uuid = "mary" . md5(serialize($this)) . $id;
    }

    public function modelName(): ?string
    {
        return $this->attributes->wire('model')?->value();
    }

    public function libraryName(): ?string
    {
        return $this->attributes->wire('library')?->value();
    }

    public function validationMessage(string $message): string
    {
        return $message;
        // Remove common prefixes to make error messages cleaner
        return str($message)
            ->after('field')
            ->after('files.')
            ->after('library.')
            ->toString();
    }

    public function render(): View|Closure|string
    {
        return <<<'BLADE'
             <div
                x-data="{
                    progress: 0,
                    indeterminate: false,
                    init () {
                        this.$watch('progress', value => {
                            this.indeterminate = value > 99
                        })
                    },
                    get processing () {
                        return this.progress > 0 && this.progress < 100
                    },
                    change() {
                        if (this.processing) {
                            return
                        }

                        this.$refs.files.click()
                    },
                    removeMedia(uuid, url){
                        this.indeterminate = true
                        $wire.removeMedia(uuid, '{{ $modelName() }}', '{{ $libraryName() }}', url).then(() => this.indeterminate = false)
                    },
                    refreshMediaOrder(order){
                        $wire.refreshMediaOrder(order, '{{ $libraryName() }}')
                    },
                    refreshMediaSources(){
                        this.indeterminate = true
                        $wire.refreshMediaSources('{{ $modelName() }}', '{{ $libraryName() }}').then(() => this.indeterminate = false)
                    }
                 }"

                x-on:livewire-upload-start="progress = 1"
                x-on:livewire-upload-progress="progress = $event.detail.progress"
                x-on:livewire-upload-finish="refreshMediaSources()"
                x-on:livewire-upload-error="progress = 0"


                {{ $attributes->whereStartsWith('class') }}
            >
                <fieldset class="fieldset py-0">
                    {{-- STANDARD LABEL --}}
                    @if($label)
                        <legend class="fieldset-legend mb-0.5">
                            {{ $label }}

                            @if($attributes->get('required'))
                                <span class="text-error">*</span>
                            @endif
                        </legend>
                    @endif

                    {{-- PREVIEW AREA --}}
                    <div
                        :class="(processing || indeterminate) && 'opacity-50 pointer-events-none'"
                        @class(["relative", "mb-2" => $preview->count() > 0, "hidden" => $preview->count() == 0])
                    >
                        <div
                            x-data="{ sortable: null }"
                            x-init="sortable = new Sortable($el, { animation: 150, ghostClass: 'bg-base-300', filter: '.ignore-drag', onEnd: (ev) => refreshMediaOrder(sortable.toArray()) })"
                            class="border-(length:--border) border-base-content/10 border-dotted rounded-lg"
                        >
                            @foreach($preview as $key => $image)
                                <div class="relative border-b-base-content/10 border-b-(length:--border) border-dotted last:border-none cursor-move hover:bg-base-200" data-id="{{ $image['uuid'] }}">
                                    <div wire:key="preview-{{ $image['uuid'] }}" class="py-2 ps-16 pe-10 tooltip" data-tip="{{ $changeText }}">
                                        @php
                                            $isPreviewable = $image['is_previewable'] ?? null;
                                            $isImage = $isPreviewable === true || ($isPreviewable === null && Str::of($image['url'] ?? '')->lower()->match('/\.(jpe?g|png|gif|webp|svg)$/'));
                                        @endphp

                                        @if($isImage)
                                            <img
                                                src="{{ $image['url'] }}"
                                                class="h-24 cursor-pointer border-2 border-base-content/10 rounded-lg hover:scale-105 transition-all ease-in-out"
                                                @click="document.getElementById('file-{{ $uuid}}-{{ $key }}').click()"
                                                id="image-{{ $modelName().'.'.$key  }}-{{ $uuid }}" />
                                        @else
                                            <div class="h-24 flex max-w-64 items-center gap-2 border-2 border-base-content/10 rounded-lg px-3 bg-base-200/60 cursor-pointer" @click="document.getElementById('file-{{ $uuid}}-{{ $key }}').click()">
                                                <x-icon name="o-document" class="w-8 h-8" />
                                                <div class="text-sm text-wrap">
                                                    {{ $image['uuid'] . '.' . $image['extension'] }}
                                                </div>
                                            </div>
                                        @endif

                                        {{-- VALIDATION --}}
                                         @error($modelName().'.'.$key)
                                            <div class="text-error label-text-alt p-1">{{ $validationMessage($message) }}</div>
                                         @enderror

                                        {{-- HIDDEN FILE INPUT --}}
                                        <input
                                            type="file"
                                            id="file-{{ $uuid}}-{{ $key }}"
                                            wire:model="{{ $modelName().'.'.$key  }}"
                                            accept="{{ $attributes->get('accept') ?? $mimes }}"
                                            class="hidden"
                                            @change="progress = 1"
                                            />
                                    </div>

                                    {{-- ACTIONS --}}
                                    <div class="absolute flex flex-col gap-2 top-3 start-3 cursor-pointer p-2 rounded-lg ignore-drag">
                                        <x-button @click="removeMedia('{{ $image['uuid'] }}', '{{ $image['url'] }}')" @touchend.prevent="removeMedia('{{ $image['uuid'] }}', '{{ $image['url'] }}')" icon="o-x-circle" :tooltip="$removeText" class="btn-sm btn-ghost btn-circle" />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- PROGRESS BAR  --}}
                    @if(! $hideProgress && $slot->isEmpty())
                        <div class="-mt-2 h-1">
                            <progress
                                x-cloak
                                :class="!processing && 'hidden'"
                                :value="progress"
                                max="100"
                                class="progress progress-primary h-1 w-full"></progress>

                            <progress
                                x-cloak
                                :class="!indeterminate && 'hidden'"
                                class="progress progress-primary h-1 w-full"></progress>
                        </div>
                    @endif

                    {{-- ADD FILES --}}
                    <div @click="$refs.files.click()" class="btn btn-block btn-sm" :class="(processing || indeterminate) && 'opacity-50 pointer-events-none'">
                        <x-icon name="o-plus-circle" class="w-5 h-5" />
                        <span>{{ $addFilesText }}</span>
                    </div>

                    {{-- MAIN FILE INPUT --}}
                    <input
                        id="{{ $uuid }}"
                        type="file"
                        x-ref="files"
                        class="file-input file-input-border file-input-primary hidden"
                        wire:model="{{ $modelName() }}.*"
                        accept="{{ $attributes->get('accept') ?? $mimes }}"
                        @change="progress = 1"
                        multiple />

                    {{-- ERROR --}}
                    @if (! $hideErrors)
                        @error($modelName())
                            <div class="text-error label-text-alt mt-1 px-1">{{ $validationMessage($message) }}</div>
                        @enderror
                        
                        @error($modelName().'.*')
                            <div class="text-error label-text-alt mt-1 px-1">{{ $validationMessage($message) }}</div>
                        @enderror
                        
                        @error($libraryName())
                            <div class="text-error label-text-alt mt-1 px-1">{{ $validationMessage($message) }}</div>
                        @enderror
                    @endif

                    {{-- HINT --}}
                    @if($hint)
                        <div class="fieldset-label">{{ $hint }}</div>
                    @endif
               </fieldset>
            </div>
            BLADE;
    }
}
