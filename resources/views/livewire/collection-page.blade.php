@assets
    <script src="https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/umd/photoswipe.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/umd/photoswipe-lightbox.umd.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/photoswipe.min.css" rel="stylesheet">
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
                <p>{{ $row->id }}</p>
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
            @endif
        @endforeach

        @scope('actions', $row)
            <x-button icon="o-arrow-right" x-on:click="$wire.show('{{ $row->id }}');" spinner class="btn-sm" />
        @endscope
    </x-table>

    <div class="fixed bottom-0 left-0 right-0" x-show="$wire.selected.length > 0" x-transition x-cloak>
        <div class="flex justify-center m-8">
            <x-card>
                <div class="flex flex-row items-center gap-4">
                    <p>Selected <span class="font-bold">{{ count($this->selected) }}</span>
                        {{ str('record')->plural(count($this->selected)) }}</p>
                    <x-button label="Reset" x-on:click="$wire.selected = []" class="btn-soft" />
                    <x-button label="Delete Selected" wire:click="promptDelete('{{ implode(',', $selected) }}')"
                        class="btn-error btn-soft" />
                </div>
            </x-card>
        </div>
    </div>

    {{-- MODALS --}}

    <x-drawer wire:model="showRecordDrawer" class="w-full lg:w-2/5" right>
        <div class="flex justify-between">
            <div class="flex items-center gap-2">
                <x-button icon="o-x-mark" class="btn-circle btn-ghost" x-on:click="$wire.showRecordDrawer = false" />
                <p class="text-sm">{{ $form['id'] ? 'Update' : 'New' }} <span
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
                    x-on:click="$wire.duplicate($wire.form.id_old)" />

                <x-menu-separator />

                <x-menu-item title="Delete" icon="o-trash" class="text-error"
                    x-on:click="$wire.promptDelete($wire.form.id_old)" />
            </x-dropdown>
        </div>

        <div class="my-4"></div>

        <x-form wire:submit="save">
            @foreach ($fields as $field)
                @if ($field->name === 'id')
                    <x-input type="text" wire:model="form.id_old" class="hidden" />
                    <x-input :wire:key="$field->name" :label="$field->name" type="text" wire:model="form.id"
                        icon="o-key" placeholder="Leave blank to auto generate..." wire:loading.attr="disabled"
                        wire:target="fillRecordForm" />
                    @continue
                @elseif ($field->name === 'created' || $field->name === 'updated')
                    <x-input :wire:key="$field->name" :label="$field->name" type="datetime"
                        wire:model="form.{{ $field->name }}" icon="o-calendar-days" readonly
                        wire:loading.attr="disabled" wire:target="fillRecordForm" />
                    @continue
                @elseif ($field->name === 'password' && $field->collection->type === App\Enums\CollectionType::Auth)
                    <x-input type="hidden" wire:model="form.{{ $field->name }}" class="hidden" />
                    <x-password :wire:key="$field->name" :label="$field->name" wire:model="form.{{ $field->name }}_new"
                        password-icon="o-lock-closed" placeholder="Fill to change password..."
                        autocomplete="new-password" wire:loading.attr="disabled" wire:target="fillRecordForm" />
                    @continue
                @endif

                @switch($field->type)
                    @case(\App\Enums\FieldType::Bool)
                        <x-toggle :wire:key="$field->name" :label="$field->name" wire:model="form.{{ $field->name }}"
                            id="form-{{ $field->name }}" wire:loading.attr="disabled" wire:target="fillRecordForm" />
                    @break

                    @case(\App\Enums\FieldType::Email)
                        <x-input :wire:key="$field->name" :label="$field->name" type="email"
                            wire:model="form.{{ $field->name }}" icon="o-envelope" :required="$field->required == true" autocomplete="email"
                            wire:loading.attr="disabled" wire:target="fillRecordForm" />
                    @break

                    @case(\App\Enums\FieldType::Number)
                        <x-input :wire:key="$field->name" :label="$field->name" type="number"
                            wire:model="form.{{ $field->name }}" icon="o-hashtag" :required="$field->required == true"
                            wire:loading.attr="disabled" wire:target="fillRecordForm" />
                    @break

                    @case(\App\Enums\FieldType::Datetime)
                        <x-input :wire:key="$field->name" :label="$field->name" type="datetime"
                            wire:model="form.{{ $field->name }}" icon="o-calendar-days" :required="$field->required == true"
                            wire:loading.attr="disabled" wire:target="fillRecordForm" />
                    @break

                    @case(\App\Enums\FieldType::File)
                        <x-file-library :wire:key="$field->name" :label="$field->name" wire:model="files.{{ $field->name }}"
                            wire:library="library.{{ $field->name }}" :preview="data_get($library, $field->name, collect([]))" hint="rule" accept="*"
                            wire:loading.attr="disabled" wire:target="fillRecordForm" />
                    @break

                    @default
                        <x-input :wire:key="$field->name" :label="$field->name" wire:model="form.{{ $field->name }}"
                            icon="lucide.text-cursor" :required="$field->required == true" wire:loading.attr="disabled"
                            wire:target="fillRecordForm" />
                @endswitch
            @endforeach

            <x-slot:actions>
                <x-button label="Cancel" x-on:click="$wire.showRecordDrawer = false" />
                <x-button label="Save" class="btn-primary" type="submit" spinner="save" />
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

        <x-form>
            <x-input label="Name" wire:model="collectionForm.name" suffix="Type: {{ $collection->type }}"
                wire:loading.attr="disabled" wire:target="fillCollectionForm" required />

            <div class="my-2"></div>

            <x-tabs wire:model="tabSelected" active-class="bg-primary rounded !text-white"
                label-class="font-semibold w-full p-2" label-div-class="bg-primary/5 flex rounded">
                <x-tab name="fields-tab" label="Fields">
                    <x-accordion wire:model="fieldOpen">
                        <div class="space-y-2 px-0.5" x-data="{
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
                        }" x-init="initSortable()"
                            @fields-updated.window="destroySortable(); $nextTick(() => initSortable())"
                            @destroy-sortable.window="destroySortable()">
                            <div id="sortable-fields-list">
                                
                                    @foreach ($collectionForm['fields'] as $index => $field)
                                        @php
                                            $fieldId = $field['id'];
                                            $field = new App\Models\CollectionField($field);
                                            $isDeleted = isset($collectionForm['fields'][$index]['_deleted']) && $collectionForm['fields'][$index]['_deleted'];
                                        @endphp

                                        <div class="flex items-center gap-2 mb-4 group relative"
                                            data-field-id="{{ $fieldId }}" wire:key="field-{{ $fieldId }}">
                                            <x-icon name="o-bars-3"
                                                class="w-4 h-4 drag-handle cursor-move text-gray-400 hover:text-gray-600 opacity-0 group-hover:opacity-100 absolute left-0 -translate-x-6" />
                                            <x-collapse separator :class="$isDeleted ? 'opacity-50 bg-error/5' : ''" class="w-full rounded"
                                                name="{{ $field->name }}" wire:loading.class="opacity-50" name="collapse_{{ $fieldId }}"
                                                wire:target="duplicateField({{ $index }}),deleteField({{ $index }})">
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
                                                                        @case(App\Enums\FieldType::Text)
                                                                            <x-input label="Min Length" type="number"
                                                                                wire:model="collectionForm.fields.{{ $index }}.options.minLength"
                                                                                placeholder="No minimum" min="0" :disabled="$field->name === 'password'" />
                                                                            <x-input label="Max Length" type="number"
                                                                                wire:model="collectionForm.fields.{{ $index }}.options.maxLength"
                                                                                placeholder="No maximum" min="1" :disabled="$field->name === 'password'" />
                                                                            <x-input label="Pattern (Regex)"
                                                                                wire:model="collectionForm.fields.{{ $index }}.options.pattern"
                                                                                placeholder="e.g., /^[A-Z]/" :disabled="$field->name === 'password'" />
                                                                            <x-input label="Auto Generate Pattern (Regex)"
                                                                                wire:model="collectionForm.fields.{{ $index }}.options.autoGeneratePattern"
                                                                                placeholder="e.g., /^[A-Z]/" :disabled="$field->name === 'password'" />
                                                                        @break

                                                                        @case(App\Enums\FieldType::Email)
                                                                         {{-- @TODO: Fix email validation and x-tags --}}
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
                                                                <div class="flex flex-wrap items-center gap-4">
                                                                    @if ($field->type == App\Enums\FieldType::File)
                                                                        <x-toggle id="toggle-multiple-{{ $index }}" label="Allow Multiple"
                                                                                wire:model.live="collectionForm.fields.{{ $index }}.options.multiple"
                                                                                hint="Allow multiple file uploads" />
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
                                                                        <x-button icon="o-bars-3"
                                                                            class="btn-circle btn-ghost" />
                                                                    </x-slot:trigger>

                                                                    <x-menu-item title="Duplicate" icon="o-document-duplicate"
                                                                        x-on:click="$wire.duplicateField({{ $fieldId }})" />
                                                                    @if (!$field->locked)
                                                                        <x-menu-item title="Delete" icon="o-trash"
                                                                            class="text-error"
                                                                            x-on:click="$wire.deleteField({{ $fieldId }})" />
                                                                    @endif
                                                                </x-dropdown>
                                                            </div>
                                                            @if ($field->rules)
                                                                <div>
                                                                    <label
                                                                        class="text-xs font-semibold text-gray-600">Validation
                                                                        Rules</label>
                                                                    <p class="text-sm font-mono bg-base-200 p-2 rounded">
                                                                        {{ is_array($field->rules) ? implode(', ', $field->rules) : $field->rules }}
                                                                    </p>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </x-slot:content>
                                            </x-collapse>
                                        </div>
                                    @endforeach
                            </div>
                        </x-accordion>

                        <x-button label="New Field" icon="o-plus" class="w-full btn-outline btn-primary"
                            wire:click="addNewField" spinner />
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
                <x-button label="Save" class="btn-primary" type="button" wire:click="saveCollection"
                    spinner="saveCollection" />
            </x-slot:actions>
        </x-form>

    </x-drawer>

    <x-modal wire:model="showConfirmDeleteDialog" title="Confirm Delete">
        Are you sure you want to delete {{ count($recordToDelete) > 1 ? count($recordToDelete) : 'this' }}
        {{ str('record')->plural(count($recordToDelete)) }}? This action cannot be undone.

        <x-slot:actions>
            <x-button label="Cancel" x-on:click="$wire.showConfirmDeleteDialog = false" />
            <x-button class="btn-error" label="Delete" wire:click="confirmDelete" spinner="confirmDelete" />
        </x-slot:actions>
    </x-modal>

</div>