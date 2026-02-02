<?php

use App\Domain\Collection\Enums\CollectionType;
use App\Domain\Collection\Services\IndexStrategies\MysqlIndexStrategy;
use App\Domain\Field\Enums\FieldType;
use App\Domain\Project\Exceptions\InvalidRecordException;
use App\Domain\Record\Models\Record;
use App\Domain\Record\Services\RecordRulesCompiler;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new class extends Component {
    use Toast, WithFileUploads;

    public \App\Domain\Collection\Models\Collection $collection;
    public \App\Domain\Collection\Models\Collection $originalCollection;
    public Collection $fields;

    public bool $showRecordDrawer = false;
    public bool $showConfirmDeleteDialog = false;

    public array $form = [];

    public ?string $recordId = null;

    public array $tinyMceConfig = [];

    public function mount(\App\Domain\Collection\Models\Collection $collection): void
    {
        $this->originalCollection = $collection;
        $this->collection = $collection;
        $this->fillFields();
        $this->fillRecordForm();
        $this->tinyMceConfig = config('velo.tinymce_config');
    }

    public function resetRecordForm(): void
    {
        $this->fillRecordForm();
    }

    #[On('show-record')]
    public function showRecord(string $id, ?string $collectionId = null): void
    {
        $targetId = $collectionId ?? $this->originalCollection->id;

        if ($this->collection->id !== $targetId) {
            $this->collection = \App\Domain\Collection\Models\Collection::find($targetId) ?? $this->originalCollection;
            $this->fillFields();
        }

        $compiler = $this->collection->records();
        $result = $compiler->filter('id', '=', $id)->first();

        if (!$result) {
            $this->error(title: 'Cannot show record.', description: 'Record not found.', position: 'toast-bottom toast-end', icon: 'o-information-circle', timeout: 2000);

            return;
        }

        $this->fillRecordForm($result->data);
        $this->showRecordDrawer = true;
    }

    protected function fillFields(): void
    {
        $this->fields = $this->collection->fields->sortBy('order')->values();
    }

    public function fillRecordForm($data = null): void
    {
        $this->resetValidation();

        if ($data == null) {
            $this->recordId = null;
            foreach ($this->fields as $field) {
                if ($field->type === FieldType::Bool) {
                    $this->form[$field->name] = false;

                    continue;
                }

                if ($field->type === FieldType::File) {
                    $this->form[$field->name] = $field->options->multiple ? [] : null;

                    continue;
                }

                $this->form[$field->name] = '';
            }

            return;
        }

        $this->form = [...$data];
        $this->recordId = $data->get('id');

        foreach ($this->fields as $field) {
            if ($field->type === FieldType::File) {
                $this->form[$field->name] = $field->options->multiple ? (is_array($data[$field->name]) ? $data[$field->name] : [$data[$field->name]]) : $data[$field->name];
            }
        }
    }

    public function duplicateRecord(): void
    {
        if ($this->recordId == null) {
            $this->info('Record not found.');
            return;
        }

        $this->form['id'] = '';
        $this->recordId = null;
    }

    public function promptDeleteRecord(): void
    {
        if (!$this->recordId) {
            return;
        }
        $this->showConfirmDeleteDialog = true;
    }

    public function confirmDeleteRecord(): void
    {
        try {
            if (!$this->recordId) {
                $this->info('Record not found.');
                return;
            }

            $record = $this->collection->records()->filter('id', '=', $this->recordId)->buildQuery()->first();

            if ($record) {
                $record->delete();
            }

            $this->dispatch('update-table');

            $this->showConfirmDeleteDialog = false;
            $this->showRecordDrawer = false;
            $this->recordId = null;

            $this->success(title: 'Success!', description: "Deleted 1 {$this->collection->name} record.", position: 'toast-bottom toast-end', timeout: 2000);
        } catch (InvalidRecordException $e) {
            $this->error($e->getMessage());
        }
    }

    protected function validateRecord(): void
    {
        $recordId = $this->recordId;

        $attributes = [];
        $rules = app(RecordRulesCompiler::class)->forCollection($this->collection)->using(new MysqlIndexStrategy())->ignoreId($recordId)->withForm($this->form)->compile(prefix: 'form.');

        foreach ($rules as $ruleName => $rule) {
            if (str_ends_with($ruleName, '.*')) {
                $index = Str::between($ruleName, 'fields.', '.options');
                $attributes[$ruleName] = "value on [$index]";

                continue;
            }

            $newName = explode('.', $ruleName);
            $newName = end($newName);
            $attributes[$ruleName] = Str::lower(Str::headline($newName));
        }

        $this->validate($rules, [], $attributes);
    }

    public function saveRecord(): void
    {
        $this->validateRecord();

        $recordId = $this->recordId;
        $status = $recordId ? 'Updated' : 'Created';

        $record = $recordId ? $this->collection->records()->filter('id', '=', $recordId)->firstRaw() : null;

        $data = collect($this->form);

        $fileFields = $this->fields->filter(fn($field) => $field->type === FieldType::File);
        $fileUploadService = app(\App\Delivery\Services\HandleFileUpload::class)->forCollection($record->collection ?? $this->collection);

        foreach ($fileFields as $field) {
            $value = $data->get($field->name);
            if (empty($value)) {
                continue;
            }

            if (!$field->options->multiple) {
                if ($value instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                    $data->put($field->name, $fileUploadService->fromUpload($value)->save());
                    continue;
                }

                if (isset($value['uuid'])) {
                    $data->put($field->name, $value);
                    continue;
                }

                $data->put($field->name, null);
                continue;
            }

            $files = is_array($value) ? $value : [$value];
            $files = array_filter($files);
            $processedFiles = [];

            foreach ($files as $file) {
                if (is_array($file) && isset($file['uuid'])) {
                    $processedFiles[] = $file;

                    continue;
                }

                if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                    continue;
                }

                $processed = $fileUploadService->fromUpload($file)->save();

                if ($processed) {
                    $processedFiles[] = $processed;
                }
            }

            $data->put($field->name, $processedFiles);
        }

        Record::updateOrCreate(['id' => $record?->id, 'collection_id' => $this->collection->id], ['data' => $data]);

        $this->showRecordDrawer = false;
        $this->dispatch('update-table');

        $this->success(title: 'Success!', description: "$status new {$this->collection->name} record");
    }

    public function openRelationPicker(string $fieldName): void
    {
        $field = $this->fields->firstWhere('name', $fieldName);

        if (!$field || $field->type !== FieldType::Relation) {
            $this->error('Invalid field.');
            return;
        }

        $collectionId = $field->options->collection;
        $multiple = $field->options->multiple ?? false;
        $selected = $this->form[$fieldName];

        $this->dispatch('open-relation-picker', collectionId: $collectionId, fieldName: $fieldName, selected: $selected, multiple: $multiple);
    }

    #[On('relation-selected')]
    public function relationSelected(string $fieldName, array|string|null $selected): void
    {
        $this->form[$fieldName] = $selected;
    }

    #[On('collection-updated')]
    public function collectionUpdated(): void
    {
        $this->collection->fresh();
        $this->fillFields();
        $this->fillRecordForm();
    }
};
?>

@assets
<link href="https://unpkg.com/filepond@^4/dist/filepond.css" rel="stylesheet"/>
<link href="https://unpkg.com/filepond-plugin-image-preview/dist/filepond-plugin-image-preview.css" rel="stylesheet"/>
<script src="https://unpkg.com/filepond-plugin-image-preview/dist/filepond-plugin-image-preview.js"></script>
<script src="https://unpkg.com/filepond-plugin-file-validate-size/dist/filepond-plugin-file-validate-size.js"></script>
<script src="https://unpkg.com/filepond-plugin-file-validate-type/dist/filepond-plugin-file-validate-type.js"></script>
<script src="https://unpkg.com/filepond@^4/dist/filepond.js"></script>
<script src="https://cdn.tiny.cloud/1/{{ config('velo.tinymce_key') }}/tinymce/6/tinymce.min.js"
        referrerpolicy="origin"></script>
@endassets

<script>
    FilePond.registerPlugin(FilePondPluginImagePreview);
    FilePond.registerPlugin(FilePondPluginFileValidateSize);
    FilePond.registerPlugin(FilePondPluginFileValidateType);
</script>

<div>
    <livewire:dialogs.relation-picker/>

    <x-drawer wire:model="showRecordDrawer" class="w-full lg:w-2/5" right without-trap-focus
              x-on:open-record-drawer.window="$wire.showRecordDrawer = true;"
              x-on:close-record-drawer.window="$wire.showRecordDrawer = false;" @close="$wire.resetRecordForm();">
        <div class="flex justify-between">
            <div class="flex items-center gap-2">
                <x-button icon="o-x-mark" class="btn-circle btn-ghost" x-on:click="$wire.showRecordDrawer = false"/>
                <p class="text-sm">{{ isset($form['id']) && $form['id'] ? 'Update' : 'New' }} <span
                        class="font-bold">{{ $collection->name }}</span> record</p>
            </div>
            <x-dropdown right>
                <x-slot:trigger>
                    <x-button icon="o-bars-2" class="btn-circle btn-ghost" :hidden="empty($form['id'])"/>
                </x-slot:trigger>

                <x-menu-item title="Copy raw JSON" icon="o-document-text" x-data="{
                    copyJson() {
                        const data = Object.fromEntries(Object.entries($wire.form).filter(([key]) => key !== 'id_old'));
                        const json = JSON.stringify(data, null, 2);
                        window.copyText(json);
                        $wire.dispatchSelf('toast', { message: 'Copied raw JSON to your clipboard.' });
                    }
                }"
                             x-on:click="copyJson"/>
                <x-menu-item title="Duplicate" icon="o-document-duplicate" x-on:click="$wire.duplicateRecord()"/>

                <x-menu-separator/>

                <x-menu-item title="Delete" icon="o-trash" class="text-error" wire:click="promptDeleteRecord"/>
            </x-dropdown>
        </div>

        <div class="my-4"></div>

        <x-form wire:submit="saveRecord">
            @foreach ($fields as $field)
                @if ($field->name === 'id')
                    <x-input type="text" wire:model="form.id_old" class="hidden"/>
                    <x-input :wire:key="$field->name" :label="$field->name" type="text" wire:model="form.id"
                             :icon="$field->getIcon()" placeholder="Leave blank to auto generate..."
                             wire:loading.attr="disabled"
                             wire:target="fillRecordForm"/>
                    @continue
                @elseif ($field->name === 'created' || $field->name === 'updated')
                    <x-input :wire:key="$field->name" :label="$field->name" type="datetime"
                             wire:model="form.{{ $field->name }}" :icon="$field->getIcon()" readonly
                             wire:loading.attr="disabled"
                             wire:target="fillRecordForm"/>
                    @continue
                @elseif (
                    $field->collection->type === \App\Domain\Collection\Enums\CollectionType::Auth &&
                        $field->name === 'password' &&
                        $field->collection->type === CollectionType::Auth)
                    @if (empty($form['password']))
                        <x-password :wire:key="$field->name" :label="$field->name" wire:model="form.{{ $field->name }}"
                                    :password-icon="$field->getIcon()" placeholder="" autocomplete="new-password"
                                    wire:loading.attr="disabled"
                                    wire:target="fillRecordForm" :required="true"/>
                    @else
                        <x-input type="hidden" wire:model="form.{{ $field->name }}" class="hidden"/>
                        <x-password :wire:key="$field->name" :label="$field->name"
                                    wire:model="form.{{ $field->name }}_new"
                                    :password-icon="$field->getIcon()" placeholder="Fill to change password..."
                                    autocomplete="new-password"
                                    wire:loading.attr="disabled" wire:target="fillRecordForm"/>
                    @endif
                    @continue
                @endif

                @switch($field->type)
                    @case(FieldType::Bool)
                        <x-toggle :wire:key="$field->name" :label="$field->name" wire:model="form.{{ $field->name }}"
                                  id="form-{{ $field->name }}" wire:loading.attr="disabled"
                                  wire:target="fillRecordForm"/>
                        @break

                    @case(FieldType::Email)
                        <x-input :wire:key="$field->name" :label="$field->name" type="email"
                                 wire:model="form.{{ $field->name }}" :icon="$field->getIcon()"
                                 :required="$field->required == true" autocomplete="email"
                                 wire:loading.attr="disabled" wire:target="fillRecordForm"/>
                        @break

                    @case(FieldType::Number)
                        <x-input :wire:key="$field->name" :label="$field->name" type="number"
                                 wire:model="form.{{ $field->name }}" :icon="$field->getIcon()"
                                 :required="$field->required == true"
                                 wire:loading.attr="disabled" wire:target="fillRecordForm"/>
                        @break

                    @case(FieldType::Datetime)
                        <x-input :wire:key="$field->name" :label="$field->name" type="datetime"
                                 wire:model="form.{{ $field->name }}" :icon="$field->getIcon()"
                                 :required="$field->required == true"
                                 wire:loading.attr="disabled" wire:target="fillRecordForm"/>
                        @break

                    @case(FieldType::File)
                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">{{ $field->name }} <span
                                    class="text-error {{ $field->required == true ? '' : 'hidden' }}">*</span></legend>
                            <div wire:ignore x-data="{
                                model: $wire.entangle('form.{{ $field->name }}'),
                                fieldOptions: JSON.parse('{{ json_encode($field->options) }}'),
                                initFilePond() {
                                    let initialFiles = [];
                                    if (Array.isArray(this.model)) {
                                        initialFiles = this.model.map(f => {
                                            if (Array.isArray(f) && f.length > 0) f = f[0];
                                            if (typeof f === 'string') return { source: f, options: { type: 'local' } };
                                            if (f?.uuid) return { source: f.url, options: { type: 'local' } };
                                            return null;
                                        }).filter(f => f);
                                    } else if (this.model?.uuid) {
                                        initialFiles = [
                                            { source: this.model?.url, options: { type: 'local' } }
                                        ];
                                    }

                                    const extraOpts = {};
                                    extraOpts.allowMultiple = true;
                                    extraOpts.maxFiles = this.fieldOptions.multiple ? (this.fieldOptions.maxFiles || 999) : 1;
                                    extraOpts.acceptedFileTypes = this.fieldOptions.allowedMimeTypes;
                                    if (this.fieldOptions.minSize) {
                                        extraOpts.minFileSize = this.fieldOptions.minSize;
                                    }
                                    if (this.fieldOptions.maxSize) {
                                        extraOpts.maxFileSize = this.fieldOptions.maxSize;
                                    }

                                    FilePond.create($refs.input, {
                                        files: initialFiles,
                                        credits: false,
                                        server: {
                                            process: (fieldName, file, metadata, load, error, progress, abort, transfer, options) => {
                                                $wire.upload('form.{{ $field->name }}', file, load, error, progress);

                                                return {
                                                    abort: () => {
                                                        $wire.cancelUpload('form.{{ $field->name }}');
                                                        abort();
                                                    }
                                                };
                                            },
                                            load: (source, load, error, progress, abort) => {
                                                fetch(`/${source}`)
                                                    .then(response => {
                                                        if (!response.ok) throw new Error('Failed to load file.');
                                                        return response.blob();
                                                    })
                                                    .then(blob => {
                                                        const name = source.split('/').pop() || 'file';
                                                        load(new File([blob], name, { type: blob.type }));
                                                    })
                                                    .catch(err => error(err.message));

                                                return {
                                                    abort: () => abort(),
                                                };
                                            },
                                            revert: (filename, load) => {
                                                $wire.removeUpload('form.{{ $field->name }}', filename, load)
                                            },
                                        },
                                        onremovefile: (err, file) => {
                                            if (err) return;

                                            const source = file?.source;
                                            if (!source) return;

                                            const normalize = (item) => {
                                                if (typeof item === 'string') return item;
                                                if (item && typeof item === 'object' && item.url) return item.url;
                                                return null;
                                            };

                                            this.model = this.fieldOptions.multiple ? (this.model || []).filter((item) => normalize(item) !== source) : null;
                                        },
                                        ...extraOpts,
                                    });
                                }
                            }" x-init="initFilePond()">
                                <x-reset-input x-ref="input" class="validator" multiple/>
                            </div>
                            @if ($errors->has('form.' . $field->name))
                                @foreach ($errors->get('form.' . $field->name) as $message)
                                    @foreach (Arr::wrap($message) as $line)
                                        <div class="text-error" x-class="text-error">{{ $line }}</div>
                                    @endforeach
                                @endforeach
                            @endif
                        </fieldset>
                        @break

                    @case(FieldType::RichText)
                        <x-editor :wire:key="$field->name" :label="$field->name" wire:model="form.{{ $field->name }}"
                                  :icon="$field->getIcon()" :required="$field->required == true"
                                  wire:loading.attr="disabled" :config="$tinyMceConfig"
                                  wire:target="fillRecordForm"/>
                        @break

                    @case(FieldType::Relation)
                        <div wire:key="{{ $field->name }}">
                            <fieldset class="fieldset py-0">
                                <legend class="fieldset-legend mb-0.5">
                                    {{ $field->name }}
                                    @if ($field->required)
                                        <span class="text-error">*</span>
                                    @endif
                                </legend>

                                <div
                                    class="input w-full h-auto min-h-10 py-2 flex flex-wrap gap-2 items-center {{ $errors->has("form.$field->name") || $errors->has("form.{$field->name}.*") ? 'input-error' : '' }}">
                                    @php
                                        $selectedIds = is_array($form[$field->name])
                                            ? $form[$field->name]
                                            : [$form[$field->name]];
                                        $relatedCollection = \App\Domain\Collection\Models\Collection::find($field->options->collection);
                                        $priority = config('velo.relation_display_fields');
                                        $displayField = $relatedCollection->fields
                                            ->whereIn('name', $priority)
                                            ->sortBy(fn($field) => array_search($field->name, $priority))
                                            ->first()?->name;
                                        if (!$displayField) {
                                            $displayField = 'id';
                                        }
                                    @endphp

                                    @if (!empty($selectedIds) && $relatedCollection)
                                        @foreach ($selectedIds as $recordId)
                                            @php
                                                $record = $relatedCollection
                                                    ->records()
                                                    ->filter('id', '=', $recordId)
                                                    ->firstRaw();
                                            @endphp
                                            @if ($record)
                                                <div class="badge badge-soft badge-sm flex items-center gap-2 py-3.5">
                                                    <p>{{ $record->data[$displayField] }}</p>
                                                </div>
                                            @endif
                                        @endforeach
                                    @else
                                        <span class="opacity-40 select-none">No records selected</span>
                                    @endif
                                </div>

                                <x-button label="Open Relation Picker" icon="lucide.pen-tool"
                                          class="btn-sm btn-soft mt-2 w-full"
                                          x-on:click="$wire.openRelationPicker('{{ $field->name }}')"
                                          wire:loading.attr="disabled"
                                          wire:target="fillRecordForm"/>

                                @foreach ($errors->get("form.{$field->name}") as $message)
                                    <div class="text-error text-sm mt-1">{{ json_encode($message) }}</div>
                                @endforeach
                                @foreach ($errors->get("form.{$field->name}.*") as $message)
                                    <div class="text-error text-sm mt-1">{{ json_encode($message) }}</div>
                                @endforeach
                            </fieldset>
                        </div>
                        @break

                    @default
                        <x-input :wire:key="$field->name" :label="$field->name" wire:model="form.{{ $field->name }}"
                                 :icon="$field->getIcon()" :required="$field->required == true"
                                 wire:loading.attr="disabled" wire:target="fillRecordForm"/>
                @endswitch
            @endforeach

            <x-slot:actions>
                <x-button label="Cancel" x-on:click="$wire.showRecordDrawer = false"/>
                <x-button label="Save" class="btn-primary" type="submit" spinner="saveRecord"/>
            </x-slot:actions>
        </x-form>

    </x-drawer>

    <x-modal wire:model="showConfirmDeleteDialog" title="Confirm Delete">
        Are you sure you want to delete this record? This action cannot be undone.

        <x-slot:actions>
            <x-button label="Cancel" x-on:click="$wire.showConfirmDeleteDialog = false"/>
            <x-button class="btn-error" label="Delete" wire:click="confirmDeleteRecord"
                      spinner="confirmDeleteRecord"/>
        </x-slot:actions>
    </x-modal>

</div>
