@assets
    <script src="https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/umd/photoswipe.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/umd/photoswipe-lightbox.umd.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/photoswipe.min.css" rel="stylesheet">
    <script src="https://cdn.tiny.cloud/1/{{ env('TINYMCE_KEY') }}/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
@endassets

<div>

    <div class="flex justify-between flex-wrap">
        <div class="flex items-center gap-4">
            <x-breadcrumbs :items="$breadcrumbs" />
            <div class="flex items-center gap-2">
                <x-button icon="o-cog-6-tooth" tooltip-bottom="Configure Collection" class="btn-circle btn-ghost"
                    x-on:click="$wire.showConfigureCollectionDrawer = true; $wire.fillCollectionForm()" />
                <x-button icon="o-arrow-path" tooltip-bottom="Refresh" class="btn-circle btn-ghost"
                    wire:click="$refresh" />
            </div>
        </div>
        <div class="flex items-center gap-2">
            <x-button label="New Record" class="btn-primary" icon="o-plus"
                x-on:click="$wire.showRecordDrawer = true; $wire.fillRecordForm()" />
        </div>
    </div>

    <div class="my-8"></div>

    <x-input wire:model.live.debounce.250ms="filter" placeholder="Search term or filter using rules..."
        icon="o-magnifying-glass" clearable />

    <div class="my-4"></div>

    <div class="flex justify-end">
        <x-dropdown>
            <x-slot:trigger>
                <x-button icon="o-table-cells" class="btn-sm" />
            </x-slot:trigger>

            <x-menu-item title="Toggle Fields" disabled />

            @foreach ($fields as $field)
                <x-menu-item :wire:key="$field->name" x-on:click.stop="$wire.toggleField('{{ $field->name }}')">
                    <x-toggle :label="$field->name" :checked="isset($fieldsVisibility[$field->name]) && $fieldsVisibility[$field->name] == true" />
                </x-menu-item>
            @endforeach
        </x-dropdown>
    </div>

    <div class="my-4"></div>

    <x-table :headers="$this->tableHeaders" :rows="$this->tableRows" {{-- @row-click="$wire.show($event.detail.id)" --}} wire:model.live.debounce.250ms="selected"
        selectable striped with-pagination per-page="perPage" :per-page-values="[10, 15, 25, 50, 100, 250, 500]" :sort-by="$sortBy">
        <x-slot:empty>
            <div class="flex flex-col items-center my-4">
                <p class="text-gray-500 text-center mb-4">No results found.</p>
                <x-button label="New Record" class="btn-primary btn-soft btn-sm" icon="o-plus"
                    x-on:click="$wire.showRecordDrawer = true; $wire.fillRecordForm()" />
            </div>
        </x-slot:empty>

        @foreach ($fields as $field)
            @cscope('header_' . $field->name, $header, $field)
                <x-icon name="{{ $field->getIcon() }}" class="w-3 opacity-80" /> {{ $header['label'] }}
            @endcscope
        @endforeach

        @scope('cell_id', $row)
            <div class="badge badge-soft badge-sm flex items-center gap-2 py-3.5">
                <p>{{ str($row->id)->limit(16) }}</p>
                <x-copy-button :text="$row->id" />
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

            @if ($field->type === App\Enums\FieldType::Bool)
                @cscope('cell_' . $field->name, $row, $field)
                    <x-badge :wire:key="$field->name . $row->id" :value="$row->{$field->name} ? 'True' : 'False'"
                        class="{{ $row->{$field->name} ? 'badge-success' : '' }} badge-soft " />
                @endcscope
                @continue
            @endif

            
            @if ($field->type === App\Enums\FieldType::Relation)
                @cscope('cell_' . $field->name, $row, $field)
                    @php
                        $relations = isset($row->{$field->name}) ? $row->{$field->name} : [];
                        $relatedCollections = App\Models\Collection::find($field->options->collection)?->name;
                    @endphp
                    @if (!empty($relations))
                        <div class="flex flex-wrap gap-2">
                            @foreach (array_slice($relations, 0, 3) as $id)
                                <div class="badge badge-soft badge-sm flex items-center gap-2 py-3.5">
                                    <p>{{ str($id)->limit(16) }}</p>
                                    <x-button class="btn-xs btn-ghost btn-circle" link="{{ route('collections', ['collection' => $relatedCollections, 'recordId' => $id]) }}" external>
                                        <x-icon name="lucide.external-link" class="w-5 h-5" />
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

            @if ($field->type === App\Enums\FieldType::File)
                @cscope('cell_' . $field->name, $row, $field)
                    @php
                        $files = isset($row->{$field->name}) ? $row->{$field->name} : [];
                    @endphp
                    @if (!empty($files))
                        <div x-data="{
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
                                @foreach (array_slice($files, 0, 3) as $file)
                                    <a wire:key="{{ $file->uuid }}" class="carousel-item" href="{{ $file->url }}"
                                        @if (!$file->is_previewable) x-on:click.prevent="window.open('{{ $file->url }}')" @endif
                                        target="_blank">
                                        @if ($file->is_previewable)
                                            <img src="{{ $file->url }}"
                                                class="object-cover hover:opacity-70 transition max-w-12 w-full aspect-square rounded me-2"
                                                onload="this.parentNode.setAttribute('data-pswp-width', this.naturalWidth); this.parentNode.setAttribute('data-pswp-height', this.naturalHeight)" />
                                        @else
                                            <div
                                                class="w-12 h-12 rounded hover:opacity-70 me-2 border flex justify-center items-center">
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
                @endcscope
                @continue
            @endif

        @endforeach

        @scope('actions', $row)
            <x-button icon="o-arrow-right" x-on:click="$wire.showRecord('{{ $row->id }}')" spinner="showRecord('{{ $row->id }}')" class="btn-sm" />
        @endscope
    </x-table>

    <div class="fixed bottom-0 left-0 right-0" x-show="$wire.selected.length > 0" x-transition x-cloak>
        <div class="flex justify-center m-8">
            <x-card>
                <div class="flex flex-row items-center gap-4">
                    <p>Selected <span class="font-bold">{{ count($this->selected) }}</span>
                        {{ str('record')->plural(count($this->selected)) }}</p>
                    <x-button label="Reset" x-on:click="$wire.selected = []" class="btn-soft" />
                    <x-button label="Delete Selected" wire:click="promptDeleteRecord('{{ implode(',', $selected) }}')"
                        class="btn-error btn-soft" />
                </div>
            </x-card>
        </div>
    </div>

    {{-- MODALS --}}

    <x-drawer wire:model="showRecordDrawer" class="w-full lg:w-2/5" right without-trap-focus>
        <div class="flex justify-between">
            <div class="flex items-center gap-2">
                <x-button icon="o-x-mark" class="btn-circle btn-ghost" x-on:click="$wire.showRecordDrawer = false" />
                <p class="text-sm">{{ isset($form['id']) && $form['id'] ? 'Update' : 'New' }} <span
                        class="font-bold">{{ $collection->name }}</span> record</p>
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
                        $wire.dispatchSelf('toast', { message: 'Copied raw JSON to your clipboard.' });
                    }
                }"
                    x-on:click="copyJson" />
                <x-menu-item title="Duplicate" icon="o-document-duplicate"
                    x-on:click="$wire.duplicateRecord($wire.form.id_old)" />

                <x-menu-separator />

                <x-menu-item title="Delete" icon="o-trash" class="text-error"
                    x-on:click="$wire.promptDeleteRecord($wire.form.id_old)" />
            </x-dropdown>
        </div>

        <div class="my-4"></div>

        <x-form wire:submit="saveRecord">
            @foreach ($fields as $field)
                @if ($field->name === 'id')
                    <x-input type="text" wire:model="form.id_old" class="hidden" />
                    <x-input :wire:key="$field->name" :label="$field->name" type="text" wire:model="form.id"
                        :icon="$field->getIcon()" placeholder="Leave blank to auto generate..." wire:loading.attr="disabled"
                        wire:target="fillRecordForm" />
                    @continue
                @elseif ($field->name === 'created' || $field->name === 'updated')
                    <x-input :wire:key="$field->name" :label="$field->name" type="datetime"
                        wire:model="form.{{ $field->name }}" :icon="$field->getIcon()" readonly
                        wire:loading.attr="disabled" wire:target="fillRecordForm" />
                    @continue
                @elseif ($field->collection->type === App\Enums\CollectionType::Auth && $field->name === 'password' && $field->collection->type === \App\Enums\CollectionType::Auth)
                    @if (empty($form['password']))
                        <x-password :wire:key="$field->name" :label="$field->name" wire:model="form.{{ $field->name }}"
                            :password-icon="$field->getIcon()" placeholder=""
                            autocomplete="new-password" wire:loading.attr="disabled" wire:target="fillRecordForm" :required="true" />
                    @else
                        <x-input type="hidden" wire:model="form.{{ $field->name }}" class="hidden" />
                        <x-password :wire:key="$field->name" :label="$field->name" wire:model="form.{{ $field->name }}_new"
                            :password-icon="$field->getIcon()" placeholder="Fill to change password..."
                            autocomplete="new-password" wire:loading.attr="disabled" wire:target="fillRecordForm" />
                    @endif
                    @continue
                @endif

                @switch($field->type)
                    @case(\App\Enums\FieldType::Bool)
                        <x-toggle :wire:key="$field->name" :label="$field->name" wire:model="form.{{ $field->name }}"
                            id="form-{{ $field->name }}" wire:loading.attr="disabled" wire:target="fillRecordForm" />
                    @break

                    @case(\App\Enums\FieldType::Email)
                        <x-input :wire:key="$field->name" :label="$field->name" type="email"
                            wire:model="form.{{ $field->name }}" :icon="$field->getIcon()" :required="$field->required == true" autocomplete="email"
                            wire:loading.attr="disabled" wire:target="fillRecordForm" />
                    @break

                    @case(\App\Enums\FieldType::Number)
                        <x-input :wire:key="$field->name" :label="$field->name" type="number"
                            wire:model="form.{{ $field->name }}" :icon="$field->getIcon()" :required="$field->required == true"
                            wire:loading.attr="disabled" wire:target="fillRecordForm" />
                    @break

                    @case(\App\Enums\FieldType::Datetime)
                        <x-input :wire:key="$field->name" :label="$field->name" type="datetime"
                            wire:model="form.{{ $field->name }}" :icon="$field->getIcon()" :required="$field->required == true"
                            wire:loading.attr="disabled" wire:target="fillRecordForm" />
                    @break

                    @case(\App\Enums\FieldType::File)
                        <x-file-library :wire:key="$field->name" :label="$field->name" wire:model="files.{{ $field->name }}"
                            wire:library="library.{{ $field->name }}" :preview="data_get($library, $field->name, collect([]))" hint="rule" accept="*"
                            wire:loading.attr="disabled" wire:target="fillRecordForm" :required="$field->required == true" />
                    @break

                    @case(\App\Enums\FieldType::RichText)
                        <x-editor :wire:key="$field->name" :label="$field->name" wire:model="form.{{ $field->name }}"
                            :icon="$field->getIcon()" :required="$field->required == true" wire:loading.attr="disabled"
                            :config="$tinyMceConfig" wire:target="fillRecordForm" />
                    @break

                    @case(\App\Enums\FieldType::Relation)
                        <div wire:key="{{ $field->name }}">
                            <fieldset class="fieldset py-0">
                                <legend class="fieldset-legend mb-0.5">
                                    {{ $field->name }}
                                    @if($field->required)
                                        <span class="text-error">*</span>
                                    @endif
                                </legend>

                                <div 
                                    class="input w-full h-auto min-h-10 py-2 flex flex-wrap gap-2 items-center {{ $errors->has("form.{$field->name}") || $errors->has("form.{$field->name}.*") ? 'input-error' : '' }}"
                                >
                                    @php
                                        $selectedIds = $form[$field->name] ?? [];
                                        $relatedCollection = \App\Models\Collection::find($field->options->collection);
                                        $priority = config('larabase.relation_display_fields');
                                        $displayField = $relatedCollection->fields->whereIn('name', $priority)->sortBy(fn($field) => array_search($field->name, $priority))->first()?->name;
                                        if (!$displayField) {
                                            $displayField = 'id';
                                        }
                                    @endphp
                                    
                                    @if(!empty($selectedIds) && $relatedCollection)
                                        @foreach($selectedIds as $recordId)
                                            @php
                                                $record = $relatedCollection->records()->filter('id', '=', $recordId)->firstRaw();
                                            @endphp
                                            @if($record)
                                                <div class="badge badge-soft badge-sm flex items-center gap-2 py-3.5">
                                                    <p>{{ $record->data[$displayField] }}</p>
                                                </div>
                                            @endif
                                        @endforeach
                                    @else
                                        <span class="opacity-40 select-none">No records selected</span>
                                    @endif
                                </div>

                                <x-button 
                                    label="Open Relation Picker" 
                                    icon="lucide.pen-tool" 
                                    class="btn-sm btn-soft mt-2 w-full" 
                                    x-on:click="$wire.openRelationPicker('{{ $field->name }}')" 
                                    wire:loading.attr="disabled" 
                                    wire:target="fillRecordForm" 
                                />

                                @foreach($errors->get("form.{$field->name}") as $message)
                                    <div class="text-error text-sm mt-1">{{ json_encode($message) }}</div>
                                @endforeach
                                @foreach($errors->get("form.{$field->name}.*") as $message)
                                    <div class="text-error text-sm mt-1">{{ json_encode($message) }}</div>
                                @endforeach
                            </fieldset>
                        </div>
                    @break

                    @default
                        <x-input :wire:key="$field->name" :label="$field->name" wire:model="form.{{ $field->name }}"
                            :icon="$field->getIcon()" :required="$field->required == true" wire:loading.attr="disabled"
                            wire:target="fillRecordForm" />
                @endswitch
            @endforeach

            <x-slot:actions>
                <x-button label="Cancel" x-on:click="$wire.showRecordDrawer = false" />
                <x-button label="Save" class="btn-primary" type="submit" spinner="saveRecord" />
            </x-slot:actions>
        </x-form>

    </x-drawer>

    <x-drawer wire:model="showConfigureCollectionDrawer" class="w-full lg:w-2/5" right>
        <div class="flex justify-between">
            <div class="flex items-center gap-2">
                <x-button icon="o-x-mark" class="btn-circle btn-ghost"
                    x-on:click="$wire.showConfigureCollectionDrawer = false" />
                <p class="text-sm">Configure <span class="font-bold">{{ $collection->name }} collection</span></p>
            </div>
        </div>

        <div class="my-4"></div>

        <x-form wire:submit.prevent="saveCollection">
            <x-input label="Name" wire:model="collectionForm.name" suffix="Type: {{ $collection->type }}"
                wire:loading.attr="disabled" wire:target="fillCollectionForm" required />

            <div class="my-2"></div>

            <x-tabs wire:model="tabSelected" active-class="bg-primary rounded !text-white"
                label-class="font-semibold w-full p-2" label-div-class="bg-primary/5 flex rounded">
                <x-tab name="fields-tab" label="Fields">
                    <x-accordion wire:model="fieldOpen">
                        <div class="space-y-2 px-0.5">
                            <div id="sortable-fields-list" wire:sortable="updateFieldOrder" wire:sortable.options="{ animation: 150, ghostClass: 'bg-primary/10', dragClass: 'opacity-50', }">
                                @foreach ($collectionForm['fields'] as $index => $field)
                                    <div class="flex items-center gap-2 mb-4 group relative" 
                                        wire:key="field-{{ $field['id'] }}" 
                                        wire:sortable.item="{{ $field['id'] }}">
                                        @php
                                            $fieldId = $field['id'];
                                            $field = new App\Models\CollectionField($field);
                                            $isDeleted = isset($collectionForm['fields'][$index]['_deleted']) && $collectionForm['fields'][$index]['_deleted'];
                                        @endphp
                                        
                                        <x-icon name="o-bars-3" 
                                                wire:sortable.handle
                                                class="w-4 h-4 drag-handle cursor-move text-gray-400 hover:text-gray-600 opacity-0 group-hover:opacity-100 absolute left-0 -translate-x-6" />

                                        <x-collapse separator 
                                                    class="w-full rounded"
                                                    name="collapse_{{ $fieldId }}" 
                                                    wire:loading.class="opacity-50"
                                                    wire:target="duplicateField({{ $index }}), deleteField({{ $index }})">
                                            <x-slot:heading>
                                                <div class="flex flex-col md:flex-row justify-between gap-2 w-full">
                                                    <div class="flex items-center gap-2">
                                                        <x-icon name="{{ $field->getIcon() }}" class="w-4 h-4" />
                                                        <span class="font-semibold"
                                                            class="{{ $isDeleted ? 'line-through' : '' }}">{{ $field->name }}</span>
                                                    </div>
                                                    <div class="flex md:flex-row-reverse items-center flex-wrap gap-2">
                                                        <x-badge value="{{ $field->type->value }}"
                                                            class="badge-sm badge-ghost" />
                                                        @if ($isDeleted)
                                                            <x-badge value="Deleted" class="badge-sm badge-error" />
                                                        @endif
                                                        @if ($field->required)
                                                            <x-badge value="Nonempty" class="badge-sm badge-info badge-soft" />
                                                        @endif
                                                        @if ($field->hidden)
                                                            <x-badge value="Hidden" class="badge-sm badge-error badge-soft" />
                                                        @endif
                                                    </div>
                                                </div>
                                            </x-slot:heading>
                                            <x-slot:content>
                                                @if ($isDeleted)
                                                    <div
                                                        class="flex items-center justify-between p-4 bg-error/10 rounded-lg">
                                                        <div>
                                                            <p class="font-semibold text-error">This field will be deleted
                                                                when you save.</p>
                                                        </div>
                                                        <x-button label="Restore" icon="o-arrow-uturn-left"
                                                            wire:click="restoreField({{ $fieldId }})"
                                                            class="btn-sm btn-primary" />
                                                    </div>
                                                @else
                                                    <div class="space-y-3 pt-2">
                                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                            <x-input label="Name"
                                                                wire:model.blur="collectionForm.fields.{{ $index }}.name"
                                                                :disabled="$field->locked == true" />
                                                            <x-select label="Type"
                                                                wire:model.live="collectionForm.fields.{{ $index }}.type"
                                                                :options="App\Enums\FieldType::toArray()" :icon="$field->getIcon()" :disabled="$field->locked == true" />
                                                            @switch($field->type)
                                                                    @case(App\Enums\FieldType::Relation)
                                                                        <div class="col-span-1 md:col-span-2">
                                                                            <x-select label="Reference Collection" wire:model="collectionForm.fields.{{ $index }}.options.collection" :options="$availableCollections" icon="o-share" />
                                                                        </div>
                                                                        
                                                                        @if ($collectionForm['fields'][$index]['options']['multiple'] == true)
                                                                            <x-input type="number" label="Min Select" wire:model="collectionForm.fields.{{ $index }}.options.minSelect" placeholder="No min select" min="0" />
                                                                            <x-input type="number" label="Min Select" wire:model="collectionForm.fields.{{ $index }}.options.maxSelect" placeholder="No max select" min="0" />
                                                                        @endif
                                                                    @break

                                                                    @case(App\Enums\FieldType::Text)
                                                                        <x-input label="Min Length" type="number"
                                                                            wire:model="collectionForm.fields.{{ $index }}.options.minLength"
                                                                            placeholder="No minimum" min="0" :disabled="$field->name === 'password' && $field->collection->type === \App\Enums\CollectionType::Auth" />
                                                                        <x-input label="Max Length" type="number"
                                                                            wire:model="collectionForm.fields.{{ $index }}.options.maxLength"
                                                                            placeholder="No maximum" min="1" :disabled="$field->name === 'password' && $field->collection->type === \App\Enums\CollectionType::Auth" />
                                                                        <x-input label="Pattern (Regex)"
                                                                            wire:model="collectionForm.fields.{{ $index }}.options.pattern"
                                                                            placeholder="e.g., /^[A-Z]/" :disabled="$field->name === 'password' && $field->collection->type === \App\Enums\CollectionType::Auth" />
                                                                        <x-input label="Auto Generate Pattern (Regex)"
                                                                            wire:model="collectionForm.fields.{{ $index }}.options.autoGeneratePattern"
                                                                            placeholder="e.g., INV-[0-9]{5}" :disabled="$field->name === 'password' && $field->collection->type === \App\Enums\CollectionType::Auth" />
                                                                    @break

                                                                    @case(App\Enums\FieldType::Email)
                                                                        <x-tags label="Allowed Domains"
                                                                            wire:model="collectionForm.fields.{{ $index }}.options.allowedDomains"
                                                                            icon="o-globe-asia-australia"
                                                                            hint="Separate each domain with a comma" clearable />
                                                                        <x-tags label="Blocked Domains"
                                                                            wire:model="collectionForm.fields.{{ $index }}.options.blockedDomains"
                                                                            icon="o-globe-asia-australia"
                                                                            hint="Separate each domain with a comma" clearable />
                                                                    @break

                                                                    @case(App\Enums\FieldType::Number)
                                                                        <x-input label="Min" type="number"
                                                                            wire:model="collectionForm.fields.{{ $index }}.options.min"
                                                                            placeholder="No minimum" step="any" />
                                                                        <x-input label="Max" type="number"
                                                                            wire:model="collectionForm.fields.{{ $index }}.options.max"
                                                                            placeholder="No maximum" step="any" />
                                                                        <x-toggle label="Allow Decimals"
                                                                            wire:model="collectionForm.fields.{{ $index }}.options.allowDecimals" />
                                                                    @break

                                                                    @case(App\Enums\FieldType::Bool)
                                                                        {{-- // --}}
                                                                    @break

                                                                    @case(App\Enums\FieldType::Datetime)
                                                                        <x-input label="Min Date"
                                                                            wire:model="collectionForm.fields.{{ $index }}.options.minDate"
                                                                            placeholder="2024-01-01 or 'now'" :disabled="in_array($field->name, ['created', 'updated'])" />
                                                                        <x-input label="Max Date"
                                                                            wire:model="collectionForm.fields.{{ $index }}.options.maxDate"
                                                                            placeholder="2024-12-31 or 'now'" :disabled="in_array($field->name, ['created', 'updated'])" />
                                                                    @break

                                                                    @case(App\Enums\FieldType::File)
                                                                        <div class="col-span-1 md:col-span-2">
                                                                            <x-choices-offline label="Allowed Mime Types" wire:model="collectionForm.fields.{{ $index }}.options.allowedMimeTypes" :options="$mimeTypes" placeholder="Search..." multiple searchable />
                                                                            <div class="my-2"></div>
                                                                            <x-dropdown>
                                                                                <x-slot:trigger>
                                                                                    <x-button label="Use Presets" class="btn-sm btn-soft" icon-right="o-chevron-down" />
                                                                                </x-slot:trigger>

                                                                                @foreach ($mimeTypePresets as $presets => $mimes)
                                                                                    <x-menu-item :title="$presets" x-on:click="$wire.applyFilePresets({{ $index }}, '{{ $presets }}')" />
                                                                                @endforeach
                                                                            </x-dropdown>
                                                                        </div>
                                                                        <x-input label="Min Size (bytes)" type="number"
                                                                            wire:model="collectionForm.fields.{{ $index }}.options.minSize"
                                                                            placeholder="No Limit" min="0" />
                                                                        <x-input label="Max Size (bytes)" type="number"
                                                                            wire:model="collectionForm.fields.{{ $index }}.options.maxSize"
                                                                            placeholder="No Limit" min="0" />
                                                                        @if ($collectionForm['fields'][$index]['options']['multiple'])
                                                                            <x-input label="Max Files" type="number"
                                                                                wire:model="collectionForm.fields.{{ $index }}.options.maxFiles"
                                                                                placeholder="Unlimited" min="1" />
                                                                        @endif
                                                                        {{-- @TODO: Add thumbnail option for future --}}
                                                                    @break
                                                                @endswitch
                                                        </div>
                                                        <div class="flex items-baseline justify-between gap-6">
                                                            <div class="grid grid-cols-1 md:grid-cols-2 gx-4">
                                                                @if ($field->type == App\Enums\FieldType::File)
                                                                    <x-toggle id="toggle-multiple-{{ $index }}" label="Allow Multiple"
                                                                            wire:model.live="collectionForm.fields.{{ $index }}.options.multiple"
                                                                            hint="Allow multiple file uploads" />
                                                                @endif
                                                                @if ($field->type == App\Enums\FieldType::Relation)
                                                                    <x-toggle id="toggle-multiple-{{ $index }}" label="Allow Multiple" 
                                                                            wire:model.live="collectionForm.fields.{{ $index }}.options.multiple" 
                                                                            hint="Allow multiple relations" />
                                                                    <x-toggle id="toggle-cascadeDelete-{{ $index }}" label="Cascade Delete" 
                                                                            wire:model.live="collectionForm.fields.{{ $index }}.options.cascadeDelete"
                                                                            hint="Delete records if relation is deleted" />
                                                                @endif
                                                                <x-toggle id="toggle-required-{{ $index }}"
                                                                    label="Nonempty" hint="Value cannot be empty"
                                                                    wire:model="collectionForm.fields.{{ $index }}.required"
                                                                    :disabled="$field->locked == true" />
                                                                <x-toggle id="toggle-hidden-{{ $index }}"
                                                                    label="Hidden" hint="Hide field from API response"
                                                                    wire:model="collectionForm.fields.{{ $index }}.hidden"
                                                                    :disabled="$field->locked == true" />
                                                            </div>
                                                            <x-dropdown top left>
                                                                <x-slot:trigger>
                                                                    <x-button icon="o-bars-3" class="btn-circle btn-ghost" />
                                                                </x-slot:trigger>

                                                                <x-menu-item title="Duplicate" icon="o-document-duplicate"
                                                                    x-on:click="$wire.duplicateField({{ $fieldId }})" />
                                                                <x-menu-item title="Delete" icon="o-trash"
                                                                    class="text-error" :hidden="$field->locked == true"
                                                                    x-on:click="$wire.deleteField({{ $fieldId }})" />
                                                            </x-dropdown>
                                                        </div>
                                                    </div>
                                                @endif
                                            </x-slot:content>
                                        </x-collapse>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </x-accordion>

                    <x-button label="New Field" icon="o-plus" class="w-full btn-outline btn-primary"
                        wire:click="addNewField" spinner />

                    <div class="divider my-2"></div>

                    <p class="text-base-content text-sm mb-1">Unique constraints and indexes ({{ count($collectionIndexes) }})</p>
                    <div class="flex items-center flex-wrap gap-2">
                        @foreach ($collectionIndexes as $index)
                            <x-button label="{{ (str_starts_with($index->index_name, 'uq_') ? 'Unique: ' : '') . implode(', ', $index->field_names) }}" class="btn-soft btn-sm" wire:click="showIndex({{ $index->id }})" spinner="showIndex({{ $index->id }})" />
                        @endforeach
                        <x-button label="New Index" icon="o-plus" class="btn-sm btn-soft"
                        wire:click="addNewIndex" spinner="addNewIndex" />
                    </div>

                </x-tab>
                <x-tab name="api-rules-tab" label="API Rules">
                    <div class="space-y-4 px-0.5">
                        @foreach ($collectionForm['api_rules'] as $apiRule => $value)
                            @continue($apiRule == 'authenticate')
                            <x-textarea wire:model="collectionForm.api_rules.{{ $apiRule }}" label="{{ ucfirst($apiRule) }} Rule" placeholder="{{ ucfirst($apiRule) }} Rule. Leave blank to grant everyone access." inline  />
                        @endforeach

                        @if ($this->collection->type === App\Enums\CollectionType::Auth)
                            <div class="divider my-2"></div>

                            <x-collapse separator>
                                <x-slot:heading>
                                    <p class="text-base-content text-sm">Additional Auth Rules</p>
                                </x-slot:heading>
                                <x-slot:content>
                                    <div class="space-y-4">
                                        <x-textarea wire:model="collectionForm.api_rules.authenticate" label="Authentication Rule" placeholder="Authentication Rule" hint="This rule is executed every time before authentication allowing you to restrict who can authenticate. For example, to allow only verified users you can set it to verified = true. Leave it empty to allow anyone with an account to authenticate. To disable authentication entirely you can change it to 'Set superusers only'" inline  />
                                        <x-textarea wire:model="collectionForm.api_rules.manage" label="Manage Rule" placeholder="Manage Rule" inline hint="This rule is executed in addition to the create and update API rules. It enables superuser-like permissions to allow fully managing the auth record(s), eg. changing the password without requiring to enter the old one, directly updating the verified state or email, etc." />
                                    </div>
                                </x-slot:content>
                            </x-collapse>                            
                        @endif
                    </div>
                </x-tab>
                
                @if ($this->collection->type === App\Enums\CollectionType::Auth)
                    <x-tab name="options-tab" label="Options">
                        <div class="space-y-4 px-0.5">
                            <x-accordion wire:model="optionOpen">
                                {{-- Auth Methods --}}
                                <x-collapse name="auth_methods">
                                    <x-slot:heading>Auth Methods</x-slot:heading>
                                    <x-slot:content>
                                        <div class="space-y-4">
                                            {{-- Standard --}}
                                            <div class="p-4 rounded-lg bg-base-100">
                                                <div class="flex items-center justify-between mb-4">
                                                    <div class="font-bold text-lg">Standard (Email/Password)</div>
                                                    <x-toggle wire:model="collectionForm.options.auth_methods.standard.enabled" />
                                                </div>
                                                <div class="ml-2">
                                                    <x-choices-offline 
                                                        label="Fields" 
                                                        wire:model="collectionForm.options.auth_methods.standard.fields" 
                                                        :options="$this->fields->map(fn($f) => ['id' => $f->name, 'name' => $f->name])->toArray()" 
                                                        hint="Email is required" 
                                                        searchable 
                                                        multiple 
                                                    />
                                                </div>
                                            </div>
                                            
                                            {{-- OAuth2 --}}
                                            <div class="p-4 rounded-lg bg-base-100">
                                                <div class="flex items-center justify-between mb-4">
                                                    <div class="font-bold text-lg">OAuth2</div>
                                                    <x-toggle wire:model="collectionForm.options.auth_methods.oauth2.enabled" />
                                                </div>
                                                
                                                <div class="space-y-4">
                                                    <div>
                                                        <label class="label">Providers</label>
                                                        {{-- @TODO: Implement OAuth2 Providers UI --}}
                                                        <div class="alert alert-warning text-sm">
                                                            OAuth2 providers configuration is coming soon.
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- OTP --}}
                                            <div class="p-4 rounded-lg bg-base-100">
                                                <div class="flex items-center justify-between mb-4">
                                                    <div class="font-bold text-lg">OTP (One-Time Password)</div>
                                                    <x-toggle wire:model="collectionForm.options.auth_methods.otp.enabled" />
                                                </div>
                                                
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <x-input label="Duration (seconds)" type="number" wire:model="collectionForm.options.auth_methods.otp.config.duration_s" />
                                                    <x-input label="Length" type="number" wire:model="collectionForm.options.auth_methods.otp.config.generate_password_length" />
                                                </div>
                                            </div>
                                        </div>
                                    </x-slot:content>
                                </x-collapse>

                                {{-- Mail Templates --}}
                                <x-collapse name="mail_templates">
                                    <x-slot:heading>Mail Templates</x-slot:heading>
                                    <x-slot:content>
                                        <div class="space-y-4">
                                            @foreach ([
                                                'verification' => 'Verification Email',
                                                'password_reset' => 'Password Reset Email',
                                                'confirm_email_change' => 'Confirm Email Change',
                                                'otp_email' => 'OTP Email',
                                                'login_alert' => 'Login Alert'
                                            ] as $key => $label)
                                                <div class="p-4 rounded-lg bg-base-100">
                                                    <div class="font-bold mb-4">{{ $label }}</div>
                                                    <div class="space-y-4">
                                                        <x-input label="Subject" wire:model="collectionForm.options.mail_templates.{{ $key }}.subject" />
                                                        {{-- <x-editor label="Body" wire:model="collectionForm.options.mail_templates.{{ $key }}.body" :config="$tinyMceConfig" /> --}}
                                                        <x-textarea label="Body (HTML supported)" wire:model="collectionForm.options.mail_templates.{{ $key }}.body" rows="4" />
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </x-slot:content>
                                </x-collapse>

                                {{-- Token Options --}}
                                <x-collapse name="token_options">
                                    <x-slot:heading>Token Options</x-slot:heading>
                                    <x-slot:content>
                                        <div class="space-y-4">
                                            @foreach ([
                                                'auth_duration' => 'Auth Token Duration',
                                                'email_verification' => 'Email Verification Token Duration',
                                                'password_reset_duration' => 'Password Reset Token Duration',
                                                'email_change_duration' => 'Email Change Token Duration',
                                                'protected_file_access_duration' => 'Protected File Access Token Duration'
                                            ] as $key => $label)
                                                <div class="p-4 rounded-lg bg-base-100">
                                                    <div class="font-bold mb-4">{{ $label }}</div>
                                                    <div class="grid grid-cols-1 gap-4">
                                                        <x-input label="Duration (seconds)" type="number" wire:model="collectionForm.options.other.tokens_options.{{ $key }}.value" />
                                                        <x-toggle label="Invalidate Previous Tokens" wire:model="collectionForm.options.other.tokens_options.{{ $key }}.invalidate_previous_tokens" />
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </x-slot:content>
                                </x-collapse>
                            </x-accordion>
                        </div>
                    </x-tab>
                @endif

            </x-tabs>

            <x-slot:actions>
                <x-button label="Cancel" x-on:click="$wire.showConfigureCollectionDrawer = false" />
                <x-button label="Save" class="btn-primary" type="submit"
                    spinner="saveCollection" />
            </x-slot:actions>
        </x-form>

    </x-drawer>

    <x-modal wire:model="showFieldIndexModal" title="Update Index">
        <div class="space-y-2">
            <div class="bg-info/10 border border-info/20 rounded-lg p-4">
                <div class="flex gap-2">
                    <x-icon name="o-information-circle" class="w-5 h-5 text-info shrink-0" />
                    <div class="text-sm">
                        <p class="font-semibold text-info mb-1">About Indexes</p>
                        <p class="opacity-80">Indexes improve query performance for frequently searched fields. Unique indexes also enforce data uniqueness.</p>
                    </div>
                </div>
            </div>

            <div class="my-4"></div>

            <x-tags label="Selected Fields"  wire:model="fieldsToBeIndexed" disabled />
            <x-toggle label="Unique" wire:model="isUniqueIndex" />

            <div class="divider my-2"></div>

            <div class="flex items-center flex-wrap gap-2">
                @foreach ($fields as $field)
                    <x-button label="{{ $field->name }}" @class(['btn-sm', in_array($field->name, $fieldsToBeIndexed) ? 'btn-accent' : 'btn-soft']) wire:click="indexToggleField('{{ $field->name }}')" />
                @endforeach
            </div>

        </div>

        <x-slot:actions>
            <div class="w-full flex items-center justify-between flex-wrap gap-2">
                <x-button icon="o-trash" tooltip-right="Drop Index" class="btn-ghost btn-circle scale-90 {{ empty($fieldsToBeIndexed) ? 'opacity-0' : '' }}" wire:click="dropIndex" spinner="dropIndex" />
                <div class="flex items-center flex-wrap gap-2">
                    <x-button label="Cancel" x-on:click="$wire.showFieldIndexModal = false" />
                    <x-button class="btn-primary" label="Set Index" wire:click="createIndex" spinner="createIndex" :disabled="empty($fieldsToBeIndexed)" />
                </div>
            </div>
        </x-slot:actions>
    </x-modal>

    <x-modal wire:model="showRelationPickerModal" title="Select {{ $relationPicker['collection']->name ?? 'users' }} records">
        <div class="space-y-6">
            <x-input 
                wire:model.live.debounce.300ms="relationPicker.search"
                placeholder="Filter records..."
                icon="o-magnifying-glass"
                clearable
            >
                <x-slot:append>
                    <x-button link="{{ route('collections', ['collection' => $relationPicker['collection'] ?? '--', 'recordId' => '--']) }}" external>
                        New record
                    </x-button>
                </x-slot:append>
            </x-input>

            <div class="border border-base-300 rounded-md overflow-hidden max-h-96 overflow-y-auto">
                @if(!empty($relationPicker['records']))
                    @foreach($relationPicker['records'] as $record)
                        @php($isSelected = in_array($record->data['id'], $relationPicker['selected'] ?? []))
                        
                        <div 
                            wire:key="relation-record-{{ $record->data['id'] }}"
                            class="group flex items-center justify-between p-4 border-b border-base-200 last:border-b-0 cursor-pointer hover:bg-base-300 transition-colors {{ $isSelected ? 'bg-base-300' : '' }}"
                            wire:click="toggleRelationRecord('{{ $record->data['id'] }}')">
                            
                            <div class="flex items-center gap-4">
                                <div class="shrink-0">
                                    <x-icon name="o-check-circle" @class(['size-6 stroke-primary transition-all duration-300', 'opacity-10 grayscale-100' => !$isSelected]) />
                                </div>
                                
                                <div class="flex items-baseline gap-2">
                                    <div>
                                        <p class="font-medium">
                                            {{ $record->data[$relationPicker['displayField']] }}
                                        </p>
                                        <p class="text-xs opacity-80">
                                            {{ $record->data['id'] }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="{{ $isSelected ? 'block' : 'hidden group-hover:block' }}">
                                {{-- <x-button x-on:click.stop="" class="btn-ghost rounded-full btn-xs">
                                    <x-icon name="o-pencil" class="w-4 h-4 text-gray-500" />
                                </x-button> --}}
                                <x-button x-on:click.stop="" link="{{ route('collections', ['collection' => $relationPicker['collection'], 'recordId' => $record->data['id']]) }}" external class="btn-ghost rounded-full btn-xs">
                                    <x-icon name="o-arrow-top-right-on-square" class="w-4 h-4 text-gray-400" />
                                </x-button>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="p-8 text-center text-gray-500">
                        No records found
                    </div>
                @endif
            </div>

            <div>
                <h4 class="font-bold text-gray-600 text-sm mb-2">Selected</h4>
                @if(empty($relationPicker['selected']))
                    <p class="text-gray-400 text-sm">No selected records.</p>
                @else
                    <div class="flex flex-wrap gap-2">
                        <span class="text-sm text-gray-600">{{ count($relationPicker['selected']) }} record(s) selected</span>
                    </div>
                @endif
            </div>
        </div>

        <x-slot:actions>
            <x-button 
                label="Cancel" 
                x-on:click="$wire.showRelationPickerModal = false" />
            
            <x-button 
                label="Set selection" 
                class="btn-primary"
                wire:click="saveRelationSelection" 
                spinner="saveRelationSelection" />
        </x-slot:actions>
    </x-modal>

    <x-modal wire:model="showConfirmDeleteDialog" title="Confirm Delete">
        Are you sure you want to delete {{ count($recordToDelete) > 1 ? count($recordToDelete) : 'this' }}
        {{ str('record')->plural(count($recordToDelete)) }}? This action cannot be undone.

        <x-slot:actions>
            <x-button label="Cancel" x-on:click="$wire.showConfirmDeleteDialog = false" />
            <x-button class="btn-error" label="Delete" wire:click="confirmDeleteRecord" spinner="confirmDeleteRecord" />
        </x-slot:actions>
    </x-modal>

</div>