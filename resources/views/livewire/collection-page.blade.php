@assets
    <script src="https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/umd/photoswipe.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/umd/photoswipe-lightbox.umd.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/photoswipe.min.css" rel="stylesheet">
@endassets

<div class="relative">
    
    <div class="flex justify-between flex-wrap">
        <div class="flex items-center gap-4">
            <x-breadcrumbs :items="$breadcrumbs" />
            <div class="flex items-center gap-2">
                <x-button icon="o-cog-6-tooth" tooltip-bottom="Configure Collection" class="btn-circle btn-ghost" x-on:click="$wire.openConfigureCollectionDrawer()" />
                <x-button icon="o-arrow-path" tooltip-bottom="Refresh" class="btn-circle btn-ghost" wire:click="$refresh" />
            </div>
        </div>
        <div class="flex items-center gap-2">
            <x-button label="New Record" class="btn-primary" icon="o-plus" wire:click="openRecordDrawer" />
        </div>
    </div>

    <div class="my-8"></div>

    <x-input wire:model.live.debounce.250ms="filter" placeholder="Search term or filter using rules..." icon="o-magnifying-glass" clearable />

    <div class="my-8"></div>

    <x-table 
        :headers="$this->tableHeaders"
        :rows="$this->tableRows"
        {{-- @row-click="$wire.show($event.detail.id)" --}}
        wire:model.live.debounce.250ms="selected"
        selectable
        striped 
        with-pagination
        per-page="perPage"
        :per-page-values="[10, 15, 25, 50, 100, 250, 500]"
        :sort-by="$sortBy"
        >
        <x-slot:empty>
            <div class="flex flex-col items-center my-4">
                <p class="text-gray-500 text-center mb-4">No results found.</p>
                <x-button label="New Record" class="btn-primary btn-soft btn-sm" icon="o-plus" wire:click="openRecordDrawer" />
            </div>
        </x-slot:empty>

        @scope('cell_id', $row)
            <div class="badge badge-soft badge-sm flex items-center gap-2 py-3.5">
                <p>{{ $row->id }}</p>
                <x-copy-button :text="$row->id" />
            </div>
        @endscope

        @scope('cell_created', $row)
            @if(isset($row->created) && $row->created)
                <div class="flex flex-col w-20">
                    <p>{{ Carbon\Carbon::parse($row->created)->format('Y-m-d') }}</p>
                    <p class="text-xs opacity-80">{{ Carbon\Carbon::parse($row->created)->format('H:i:s') }}</p>
                </div>
            @else
                <p>-</p>
            @endif
        @endscope

        @scope('cell_updated', $row)
            @if(isset($row->updated) && $row->updated)
                <div class="flex flex-col w-20">
                    <p>{{ Carbon\Carbon::parse($row->updated)->format('Y-m-d') }}</p>
                    <p class="text-xs opacity-80">{{ Carbon\Carbon::parse($row->updated)->format('H:i:s') }}</p>
                </div>
            @else
                <p>-</p>
            @endif
        @endscope

        @foreach ($fields as $field)
            @if ($field->type === App\Enums\FieldType::File)
                @cscope('cell_' . $field->name, $row, $field)
                    @php
                        $files = isset($row->{$field->name}) ?  $row->{$field->name} : [];
                    @endphp
                    @if (!empty($files))
                        <div
                            x-data="{
                                init() {
                                    const lightbox = new PhotoSwipeLightbox({
                                        gallery: '#gallery-{{ str($row->id . '-' . $field->name)->slug() }}',
                                        children: 'a',
                                        pswpModule: PhotoSwipe
                                    });

                                    lightbox.init();
                                }
                            }"
                        >
                            <div id="gallery-{{ str($row->id . '-' . $field->name)->slug() }}" class="pswp-gallery pswp-gallery--single-column carousel">
                                @foreach(array_slice($files, 0, 3) as $file)
                                    <a class="carousel-item" href="{{ $file->url }}" @if(!$file->is_previewable) x-on:click.prevent="window.open('{{ $file->url }}')" @endif target="_blank">
                                        @if ($file->is_previewable)
                                            <img
                                                src="{{ $file->url }}"
                                                class="object-cover hover:opacity-70 transition max-w-12 w-full aspect-square rounded me-2"
                                                onload="this.parentNode.setAttribute('data-pswp-width', this.naturalWidth); this.parentNode.setAttribute('data-pswp-height', this.naturalHeight)"
                                            />
                                        @else
                                            <div class="w-12 h-12 rounded hover:opacity-70 me-2 border flex justify-center items-center">
                                                <x-icon name="o-document" />
                                            </div>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        -
                    @endif
                @endscope
            @endif
        @endforeach

        @scope('actions', $row)
            <x-button icon="o-arrow-right" wire:click="show('{{ $row->id }}')" spinner class="btn-sm" />
        @endscope
    </x-table>

    <div class="fixed bottom-0 left-0 right-0" x-show="$wire.selected.length > 0" x-transition x-cloak>
        <div class="flex justify-center m-8">
            <x-card>
                <div class="flex flex-row items-center gap-4">
                    <p>Selected <span class="font-bold">{{ count($this->selected) }}</span> {{ str('record')->plural(count($this->selected)) }}</p>
                    <x-button label="Reset" x-on:click="$wire.selected = []" class="btn-soft" />
                    <x-button label="Delete Selected" wire:click="promptDelete('{{ implode(',', $selected) }}')" class="btn-error btn-soft" />
                </div>
            </x-card>
        </div>
    </div>

    {{-- MODALS --}}

    <x-drawer wire:model="showRecordDetailDrawer" class="w-11/12 lg:w-1/3" right>
        <div class="flex justify-between">
            <div class="flex items-center gap-2">
                <x-button icon="o-x-mark" class="btn-circle btn-ghost" x-on:click="$wire.showRecordDetailDrawer = false" />
                <p class="text-sm">{{ $form['id'] ? 'Update' : 'New' }} <span class="font-bold">{{ $collection->name }}</span> record</p>
            </div>
            <x-dropdown right>
                <x-slot:trigger>
                    <x-button icon="o-bars-2" class="btn-circle btn-ghost" :hidden="empty($form['id'])" />
                </x-slot:trigger>
            
                <x-menu-item title="Copy raw JSON" icon="o-document-text" x-data="{
                    copyJson() {
                        const data = Object.fromEntries(Object.entries($wire.form).filter(([key]) => key !== 'id_old'));
                        const json = JSON.stringify(data, null, 2);
                        window.copyText(json);
                        $wire.dispatchSelf('toast', {message: 'Copied raw JSON to your clipboard.'});
                    }
                }" x-on:click="copyJson" />
                <x-menu-item title="Duplicate" icon="o-document-duplicate" x-on:click="$wire.duplicate($wire.form.id_old)" />

                <x-menu-separator />

                <x-menu-item title="Delete" icon="o-trash" class="text-error" x-on:click="$wire.promptDelete($wire.form.id_old)" />
            </x-dropdown>
        </div>
        
        <div class="my-4"></div>

        <x-form wire:submit="save">
            @foreach($fields as $field)
                @if ($field->name === 'id')
                    <x-input type="text" wire:model="form.id_old" class="hidden" />
                    <x-input :label="$field->name" type="text" wire:model="form.id" icon="o-key" placeholder="Leave blank to auto generate..." />
                    @continue
                @elseif ($field->name === 'created' || $field->name === 'updated')
                    <x-input :label="$field->name" type="datetime" wire:model="form.{{ $field->name }}" icon="o-calendar-days" readonly />
                    @continue
                @elseif ($field->name === 'password' && $field->collection->type === App\Enums\CollectionType::Auth)
                    <x-password :label="$field->name" wire:model="form.{{ $field->name }}" password-icon="o-lock-closed" placeholder="Fill to change password..." />
                    @continue
                @endif

                @switch($field->type)
                    @case(\App\Enums\FieldType::Bool)
                        <x-toggle :label="$field->name" wire:model="form.{{ $field->name }}" id="form-{{ $field->name }}" />
                        @break
                    @case(\App\Enums\FieldType::Email)
                        <x-input :label="$field->name" type="email" wire:model="form.{{ $field->name }}" icon="o-envelope" :required="$field->required == true" />
                        @break
                    @case(\App\Enums\FieldType::Number)
                        <x-input :label="$field->name" type="number" wire:model="form.{{ $field->name }}" icon="o-hashtag" :required="$field->required == true" />
                        @break
                    @case(\App\Enums\FieldType::Datetime)
                        <x-input :label="$field->name" type="datetime" wire:model="form.{{ $field->name }}" icon="o-calendar-days" :required="$field->required == true" />
                        @break
                    @case(\App\Enums\FieldType::File)
                            <x-file-library :label="$field->name" wire:model="files.{{ $field->name }}" wire:library="library.{{ $field->name }}" :preview="data_get($library, $field->name, collect([]))" hint="rule" accept="*" />
                        @break
                    @default
                        <x-input :label="$field->name" wire:model="form.{{ $field->name }}" icon="lucide.text-cursor" :required="$field->required == true" />
                @endswitch
            @endforeach
        
            <x-slot:actions>
                <x-button label="Cancel" x-on:click="$wire.showRecordDetailDrawer = false" />
                <x-button label="Save" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-form>

    </x-drawer>

    <x-drawer wire:model="showConfigureCollectionDrawer" class="w-11/12 lg:w-1/3" right>
        <div class="flex justify-between">
            <div class="flex items-center gap-2">
                <x-button icon="o-x-mark" class="btn-circle btn-ghost" x-on:click="$wire.showConfigureCollectionDrawer = false" />
                <p class="text-sm">Configure <span class="font-bold">{{ $collection->name }} collection</span></p>
            </div>
        </div>
        
        <div class="my-4"></div>

        <x-form wire:submit="saveCollectionConfiguration">
            <x-input label="Name" wire:model="collectionForm.name" suffix="Type: {{ $collection->type }}" required />
        
            <div class="my-2"></div>

            <x-tabs
                wire:model="tabSelected"
                active-class="bg-primary rounded !text-white"
                label-class="font-semibold w-full p-2"
                label-div-class="bg-primary/5 flex rounded"
            >
                <x-tab name="fields-tab" label="Fields">
                    <div class="space-y-2 px-0.5" 
                        x-data="{
                            sortableInstance: null,
                            initSortable() {
                                const el = document.getElementById('sortable-fields-list');
                                if (window.Sortable && el) {
                                    if (this.sortableInstance) {
                                        this.sortableInstance.destroy();
                                        this.sortableInstance = null;
                                    }
                                    
                                    this.sortableInstance = new Sortable(el, {
                                        animation: 150,
                                        handle: '.drag-handle',
                                        ghostClass: 'bg-primary/10',
                                        dragClass: 'opacity-50',
                                        onEnd: (evt) => {
                                            const items = Array.from(el.children).map(item => item.dataset.fieldId);
                                            $wire.updateFieldOrder(items);
                                        }
                                    });
                                }
                            },
                            destroySortable() {
                                if (this.sortableInstance) {
                                    this.sortableInstance.destroy();
                                    this.sortableInstance = null;
                                }
                            }
                        }"
                        x-init="initSortable()"
                        @fields-updated.window="destroySortable(); $nextTick(() => initSortable())"
                    >
                        <div id="sortable-fields-list">
                            @foreach($collectionForm['fields'] as $index => $field)
                                @php($field = new App\Models\CollectionField($field))
                                @php($isDeleted = isset($collectionForm['fields'][$index]['_deleted']) && $collectionForm['fields'][$index]['_deleted'])
                                @php($fieldId = $collectionForm['fields'][$index]['id'] ?? $collectionForm['fields'][$index]['name'] ?? $index)

                                <div class="flex items-center gap-2 mb-4 group relative" data-field-id="{{ $fieldId }}" wire:key="field-{{ $fieldId }}">
                                    <x-icon name="o-bars-3" class="w-4 h-4 drag-handle cursor-move text-gray-400 hover:text-gray-600 opacity-0 group-hover:opacity-100 absolute left-0 -translate-x-6" />
                                    <x-collapse separator :class="$isDeleted ? 'opacity-50 bg-error/5' : ''" class="w-full">
                                        <x-slot:heading>
                                            <div class="flex items-center gap-2 w-full">
                                                <x-icon name="{{ $field->getIcon() }}" class="w-4 h-4" />
                                                <span class="font-semibold" class="{{ $isDeleted ? 'line-through' : '' }}">{{ $field->name }}</span>
                                                <x-badge value="{{ $field->type->value }}" class="badge-sm badge-ghost" />
                                                @if($isDeleted)
                                                    <x-badge value="Marked for Deletion" class="badge-sm badge-error" />
                                                @endif
                                            </div>
                                        </x-slot:heading>
                                        <x-slot:content>
                                            @if($isDeleted)
                                                <div class="flex items-center justify-between p-4 bg-error/10 rounded-lg">
                                                    <div>
                                                        <p class="font-semibold text-error">This field will be deleted when you save.</p>
                                                        <p class="text-sm text-gray-600 mt-1">Click restore to undo this action.</p>
                                                    </div>
                                                    <x-button label="Restore" icon="o-arrow-uturn-left" wire:click="restoreField({{ $index }})" class="btn-sm btn-primary" />
                                                </div>
                                            @else
                                            <div class="space-y-3 pt-2">
                                                <div class="grid grid-cols-2 gap-4">
                                                        <x-input label="Name" wire:model.blur="collectionForm.fields.{{ $index }}.name" :disabled="$field->locked == true" />
                                                        <x-select label="Type" wire:model.live="collectionForm.fields.{{ $index }}.type" :options="App\Enums\FieldType::toArray()" :icon="$field->getIcon()" :disabled="$field->locked == true" />
                                                        @if ($field->type === App\Enums\FieldType::Text && $field->locked != true)
                                                            <x-input label="Min Length" type="number" wire:model="collectionForm.fields.{{ $index }}.min_length" placeholder="Default to 0" min="0" :disabled="$field->locked == true" />
                                                            <x-input label="Max Length" type="number" wire:model="collectionForm.fields.{{ $index }}.max_length" placeholder="Default to 5000" min="0" :disabled="$field->locked == true" />
                                                        @endif
                                                </div>
                                                <div class="flex items-baseline justify-between gap-6">
                                                    <div class="flex items-center gap-4">
                                                        <x-toggle label="Nonempty" hint="Value cannot be empty" wire:model="collectionForm.fields.{{ $index }}.required" :disabled="$field->locked == true" id="required-{{ $fieldId }}" />
                                                        <x-toggle label="Hidden" hint="Hide field from API response" wire:model="collectionForm.fields.{{ $index }}.hidden" :disabled="$field->locked == true" id="hidden-{{ $fieldId }}" />
                                                    </div>
                                                    <x-dropdown top left>
                                                        <x-slot:trigger>
                                                            <x-button icon="o-bars-3" class="btn-circle btn-ghost" />
                                                        </x-slot:trigger>
                                                    
                                                        <x-menu-item title="Duplicate" icon="o-document-duplicate" x-on:click="$wire.duplicateField({{ $index }})" />
                                                        @if(!$field->locked)
                                                            <x-menu-item title="Delete" icon="o-trash" class="text-error" x-on:click="$wire.deleteField({{ $index }})" />
                                                        @endif
                                                    </x-dropdown>
                                                </div>
                                                @if($field->rules)
                                                    <div>
                                                        <label class="text-xs font-semibold text-gray-600">Validation Rules</label>
                                                        <p class="text-sm font-mono bg-base-200 p-2 rounded">{{ is_array($field->rules) ? implode(', ', $field->rules) : $field->rules }}</p>
                                                    </div>
                                                @endif
                                            </div>
                                            @endif
                                        </x-slot:content>
                                    </x-collapse>
                                </div>
                            @endforeach
                        </div>

                        <x-button label="New Field" icon="o-plus" class="w-full btn-outline btn-primary" wire:click="addNewField" />
                    </div>
                </x-tab>
                <x-tab name="api-rules-tab" label="API Rules">
                    <div>Api Rules</div>
                </x-tab>
                <x-tab name="options-tab" label="Options">
                    <div>Options</div>
                </x-tab>
            </x-tabs>

            <x-slot:actions>
                <x-button label="Cancel" x-on:click="$wire.showConfigureCollectionDrawer = false" />
                <x-button label="Save" class="btn-primary" type="submit" spinner="saveCollectionConfiguration" />
            </x-slot:actions>
        </x-form>

    </x-drawer>

    <x-modal wire:model="showConfirmDeleteDialog" title="Confirm Delete">
        Are you sure you want to delete {{ count($recordToDelete) > 1 ? count($recordToDelete) : 'this' }} {{ str('record')->plural(count($recordToDelete)) }}? This action cannot be undone.
    
        <x-slot:actions>
            <x-button label="Cancel" x-on:click="$wire.showConfirmDeleteDialog = false" />
            <x-button class="btn-error" label="Delete" wire:click="confirmDelete" spinner="confirmDelete" />
        </x-slot:actions>
    </x-modal>

</div>