<?php

use App\Domain\Collection\Enums\CollectionType;
use App\Domain\Field\Enums\FieldType;
use App\Domain\Project\Exceptions\InvalidRecordException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component {
    use WithPagination, Toast;

    public \App\Domain\Collection\Models\Collection $collection;
    public Illuminate\Database\Eloquent\Collection $fields;

    public array $breadcrumbs = [];

    public bool $showConfirmDeleteDialog = false;
    public int $perPage = 15;

    public string $filter = '';

    public array $sortBy = ['column' => 'created', 'direction' => 'desc'];

    public array $selected = [];

    public array $fieldsVisibility = [];

    public function mount(): void
    {
        $this->fields = $this->collection->fields->sortBy('order')->values();
        $this->breadcrumbs = [['link' => route('home'), 'icon' => 's-home'], ['label' => ucfirst(request()->route()->getName())], ['label' => $this->collection->name]];
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function toggleField(string $field): void
    {
        if (!array_key_exists($field, $this->fieldsVisibility)) {
            return;
        }

        $this->fieldsVisibility[$field] = !$this->fieldsVisibility[$field];
    }

    #[Computed]
    public function tableHeaders(): array
    {
        $this->fillFieldsVisibility();

        return $this->fields
            ->filter(fn($f) => isset($this->fieldsVisibility[$f->name]) && $this->fieldsVisibility[$f->name])
            ->sortBy('order')
            ->map(function ($f) {
                $headers = ['key' => $f->name, 'label' => $f->name];

                if ($f->type == FieldType::Datetime) {
                    $headers['format'] = ['date', 'Y-m-d H:i:s'];
                } elseif ($f->type == FieldType::Bool) {
                    $headers['format'] = fn($row, $field) => $field ? 'Yes' : 'No';
                } elseif ($f->type == FieldType::File) {
                    $headers['format'] = fn($row, $field) => json_encode($field);
                } else {
                    $headers['format'] = fn($row, $field) => $field ?: '-';
                }

                return $headers;
            })
            ->toArray();
    }

    #[Computed]
    public function tableRows()
    {
        $compiler = $this->collection->records();

        if (!empty($this->sortBy['column'])) {
            $compiler->sort($this->sortBy['column'], $this->sortBy['direction']);
        }

        if (!empty($this->filter)) {
            $compiler->filterFromString($this->filter);
        }

        $data = $compiler->paginate($this->perPage);

        $data->getCollection()->transform(function ($recordData) {
            if ($this->collection->type === CollectionType::Auth && $this->fields->firstWhere('name', 'password')) {
                $recordData->password = Str::repeat('*', 12);
            }

            return $recordData;
        });

        return $data;
    }

    #[On('collection-updated')]
    public function collectionUpdated(): void
    {
        $this->collection->fresh();
        $this->fields = $this->collection->fields->sortBy('order')->values();
        $this->updateTable();
    }

    #[On('update-table')]
    public function updateTable(): void
    {
        unset($this->tableRows);
    }

    public function fillFieldsVisibility(): void
    {
        foreach ($this->fields as $i => $field) {
            // Only initialize if not already set (preserves user toggles)
            if (!array_key_exists($field->name, $this->fieldsVisibility)) {
                $this->fieldsVisibility[$field->name] = true;

                if ($this->collection->type === CollectionType::Auth) {
                    if ($field->name === 'password') {
                        $this->fieldsVisibility['password'] = false;
                    }
                }
            }
        }
    }

    #[On('delete-record')]
    public function promptDeleteRecord(): void
    {
        if (empty($this->selected)) {
            return;
        }
        $this->showConfirmDeleteDialog = true;
    }

    public function confirmDeleteRecord(): void
    {
        try {
            $count = count($this->selected);

            $this->collection->records()->whereIn('id', $this->selected)->buildQuery()->delete();

            $this->showConfirmDeleteDialog = false;
            $this->selected = [];

            unset($this->tableRows);

            $this->success(title: 'Success!', description: "Deleted $count {$this->collection->name} " . str('record')->plural($count) . '.', position: 'toast-bottom toast-end', timeout: 2000);
        } catch (InvalidRecordException $e) {
            $this->error($e->getMessage());
        } finally {
            $this->dispatch('close-record-drawer');
        }
    }
};
?>

@assets
<script src="https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/umd/photoswipe.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/umd/photoswipe-lightbox.umd.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/photoswipe.min.css" rel="stylesheet">
@endassets

<div>

    <livewire:dialogs.collection :$collection/>
    <livewire:dialogs.record :$collection/>

    <div class="flex justify-between flex-wrap">
        <div class="flex items-center gap-4">
            <x-breadcrumbs :items="$breadcrumbs"/>
            <div class="flex items-center gap-2">
                <x-button icon="o-cog-6-tooth" tooltip-bottom="Configure Collection" class="btn-circle btn-ghost"
                          wire:click="$dispatch('show-collection', { id: '{{ $collection->id }}' })"/>
                <x-button icon="o-arrow-path" tooltip-bottom="Refresh" class="btn-circle btn-ghost"
                          wire:click="$refresh"/>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <x-button label="New Record" class="btn-primary" icon="o-plus"
                      wire:click="$dispatch('open-record-drawer')"/>
        </div>
    </div>

    <div class="my-8"></div>

    <x-input wire:model.live.debounce.250ms="filter" placeholder="Search term or filter using rules..."
             icon="o-magnifying-glass" clearable/>

    <div class="my-4"></div>

    <div class="flex justify-end">
        <x-dropdown>
            <x-slot:trigger>
                <x-button icon="o-table-cells" class="btn-sm"/>
            </x-slot:trigger>

            <x-menu-item title="Toggle Fields" disabled/>

            @foreach ($fields as $field)
                <x-menu-item :wire:key="$field->name" x-on:click.stop="$wire.toggleField('{{ $field->name }}')">
                    <x-toggle :label="$field->name"
                              :checked="isset($fieldsVisibility[$field->name]) && $fieldsVisibility[$field->name] == true"/>
                </x-menu-item>
            @endforeach
        </x-dropdown>
    </div>

    <div class="my-4"></div>

    <x-table :headers="$this->tableHeaders" :rows="$this->tableRows"
             @row-click="$dispatch('show-record', { id: $event.detail.id })"
             wire:model.live.debounce.250ms="selected" selectable striped with-pagination per-page="perPage"
             :per-page-values="[10, 15, 25, 50, 100, 250, 500]" :sort-by="$sortBy">
        <x-slot:empty>
            <div class="flex flex-col items-center my-4">
                <p class="text-gray-500 text-center mb-4">No results found.</p>
                <x-button label="New Record" class="btn-primary btn-soft btn-sm" icon="o-plus"
                          x-on:click="$dispatch('open-record-drawer')"/>
            </div>
        </x-slot:empty>

        @foreach ($fields as $field)
            @cscope('header_' . $field->name, $header, $field)
            <x-icon name="{{ $field->getIcon() }}" class="w-3 opacity-80"/> {{ $header['label'] }}
            @endcscope
        @endforeach

        @scope('cell_id', $row)
        <div class="badge badge-soft badge-sm flex items-center gap-2 py-3.5" x-on:click.stop="">
            <p>{{ str($row->id)->limit(16) }}</p>
            <x-copy-button :text="$row->id"/>
        </div>
        @endscope

        @scope('cell_created', $row)
        @if (isset($row->created) && $row->created)
            <div class="flex flex-col w-20">
                <p>{{ Carbon\Carbon::parse($row->created)->format('Y-m-d') }}</p>
                <p class="text-xs opacity-80">{{ Carbon\Carbon::parse($row->created)->format('H:i:s') }}</p>
            </div>
        @else
            <p>-</p>
        @endif
        @endscope

        @scope('cell_updated', $row)
        @if (isset($row->updated) && $row->updated)
            <div class="flex flex-col w-20">
                <p>{{ Carbon\Carbon::parse($row->updated)->format('Y-m-d') }}</p>
                <p class="text-xs opacity-80">{{ Carbon\Carbon::parse($row->updated)->format('H:i:s') }}</p>
            </div>
        @else
            <p>-</p>
        @endif
        @endscope

        @foreach ($fields as $field)

            @if ($field->type === \App\Domain\Field\Enums\FieldType::Bool)
                @cscope('cell_' . $field->name, $row, $field)
                <x-badge :wire:key="$field->name . $row->id" :value="$row->{$field->name} ? 'True' : 'False'"
                         class="{{ $row->{$field->name} ? 'badge-success' : '' }} badge-soft "/>
                @endcscope
                @continue
            @endif


            @if ($field->type === \App\Domain\Field\Enums\FieldType::Relation)
                @cscope('cell_' . $field->name, $row, $field)
                @php
                    $relations = is_array($row->{$field->name}) ? $row->{$field->name} : [$row->{$field->name}];
                    $relations = array_filter($relations);
                    $relatedCollections = \App\Domain\Collection\Models\Collection::find($field->options->collection);
                @endphp
                @if (!empty($relations))
                    <div class="flex flex-wrap gap-2">
                        @foreach (array_slice($relations, 0, 3) as $id)
                            @php
                                $record = !$relatedCollections
                                    ? null
                                    : $relatedCollections->records()->filter('id', '=', $id)->buildQuery()->first();
                            @endphp
                            <div class="badge badge-soft badge-sm flex items-center gap-2 py-3.5">
                                <p>{{ str($record?->data['name'] ?? ($record?->data['email'] ?? $id))->limit(16) }}</p>
                                <x-button class="btn-xs btn-ghost btn-circle"
                                          link="{{ route('collections', ['collection' => $relatedCollections?->name, 'recordId' => $id]) }}"
                                          external>
                                    <x-icon name="lucide.external-link" class="w-5 h-5"/>
                                </x-button>
                            </div>
                        @endforeach
                    </div>
                @else
                    -
                @endif
                @endcscope
                @continue
            @endif

            @if ($field->type === \App\Domain\Field\Enums\FieldType::File)
                @cscope('cell_' . $field->name, $row, $field)
                @php
                    $files = is_array($row->{$field->name}) ? $row->{$field->name} : [$row->{$field->name}];
                    $files = array_filter($files);
                @endphp
                @if (!empty($files))
                    <div x-on:click.stop="" wire:ignore x-data="{
                            init() {
                                const lightbox = new PhotoSwipeLightbox({
                                    gallery: '#gallery-{{ str($row->id . '-' . $field->name)->slug() }}',
                                    children: 'a',
                                    pswpModule: PhotoSwipe
                                });

                                lightbox.init();
                            }
                        }">
                        <div id="gallery-{{ str($row->id . '-' . $field->name)->slug() }}"
                             class="pswp-gallery pswp-gallery--single-column carousel">
                            @foreach ($files as $file)
                                <a wire:key="{{ $file->uuid }}"
                                   class="carousel-item"
                                   data-pswp-src="{{ url($file->url) }}"
                                   href="{{ url($file->url) }}"
                                   @if (!$file->is_previewable) x-on:click.stop.prevent="window.open('{{ url($file->url) }}')"
                                   @endif
                                   target="_blank">
                                    @if ($file->is_previewable)
                                        <img src="{{ url($file->url) }}?w=100" alt=""
                                             class="object-cover hover:opacity-70 transition max-w-12 w-full aspect-square rounded me-2"
                                             onload="
                                                const full = new Image();
                                                full.src = this.parentNode.dataset.pswpSrc || this.parentNode.href;
                                                full.onload = () => {
                                                  this.parentNode.dataset.pswpWidth = full.naturalWidth;
                                                  this.parentNode.dataset.pswpHeight = full.naturalHeight;
                                                };
                                             "
                                        />
                                    @else
                                        <div
                                            class="w-12 h-12 rounded hover:opacity-70 me-2 border flex justify-center items-center">
                                            <x-icon name="o-document"/>
                                        </div>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @else
                    -
                @endif
                @endcscope
                @continue
            @endif

        @endforeach

        @scope('actions', $row)
        <x-button icon="o-arrow-right" x-on:click="$dispatch('show-record', { id: '{{ $row->id }}' })"
                  class="btn-sm"/>
        @endscope
    </x-table>

    <div class="fixed bottom-0 left-0 right-0" x-show="$wire.selected.length > 0" x-transition x-cloak>
        <div class="flex justify-center m-8">
            <x-card>
                <div class="flex flex-row items-center gap-4">
                    <p>Selected <span class="font-bold">{{ count($this->selected) }}</span>
                        {{ str('record')->plural(count($this->selected)) }}</p>
                    <x-button label="Reset" x-on:click="$wire.selected = []" class="btn-soft"/>
                    <x-button label="Delete Selected" wire:click="promptDeleteRecord" class="btn-error btn-soft"/>
                </div>
            </x-card>
        </div>
    </div>

    <x-modal wire:model="showConfirmDeleteDialog" title="Confirm Delete">
        Are you sure you want to delete {{ count($selected) > 1 ? count($selected) : 'this' }}
        {{ str('record')->plural(count($selected)) }}? This action cannot be undone.

        <x-slot:actions>
            <x-button label="Cancel" x-on:click="$wire.showConfirmDeleteDialog = false"/>
            <x-button class="btn-error" label="Delete" wire:click="confirmDeleteRecord" spinner="confirmDeleteRecord"/>
        </x-slot:actions>
    </x-modal>

</div>
