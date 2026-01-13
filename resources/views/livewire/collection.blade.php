<?php

use App\Enums\{FieldType, CollectionType};
use App\Models\{Collection, CollectionField, Record};
use App\Services\{RecordQueryCompiler,RecordRulesCompiler};
use Livewire\Attributes\{Computed, Title, On};
use Livewire\Volt\Component;
use Livewire\{WithFileUploads, WithPagination};
use Mary\Traits\Toast;
use Carbon\Carbon;

new class extends Component
{
    use WithFileUploads, WithPagination, Toast;

    public Collection $collection;
    public $fields;
    public array $breadcrumbs = [];
    public bool $showRecordDetailDrawer = false;
    public bool $showConfirmDeleteDialog = false;
    public bool $showConfigureCollectionDrawer = false;
    public array $recordToDelete = [];
    public array $form = [];
    public array $collectionForm = ['fields' => []];
    public string $tabSelected = 'fields-tab';

    // Table State
    public int $perPage = 15;
    public string $filter = '';
    public array $sortBy = ['column' => 'created', 'direction' => 'desc'];
    public array $selected = [];

    public function mount(Collection $collection): void
    {
        $this->collection = $collection;
        $this->fields = $collection->fields;
        
        foreach ($this->fields as $field) {
            $this->form[$field->name] = $field->type === FieldType::Bool ? false : '';
        }

        $this->breadcrumbs = [
            ['link' => route('home'), 'icon' => 's-home'],
            ['label' => 'Collection'],
            ['label' => $this->collection->name]
        ];
    }

    public function title(): string
    {
        return "Collection - {$this->collection->name}";
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function tableHeaders(): array
    {
        return $this->fields->map(function ($f) {
            $headers = [
                'key' => $f->name,
                'label' => $f->name,
                'format' => null,
            ];

            if ($f->type == FieldType::Datetime) {
                $headers['format'] = ['date', 'Y-m-d H:i:s'];
            } elseif ($f->type == FieldType::Bool) {
                $headers['format'] = fn($row, $field) => $field ? 'Yes' : 'No';
            } else {
                $headers['format'] = fn($row, $field) => $field ? $field : '-';
            }

            return $headers;
        })->toArray();
    }

    #[Computed]
    public function tableRows()
    {
        $compiler = new RecordQueryCompiler($this->collection);

        if (!empty($this->sortBy['column'])) {
            $compiler->sort($this->sortBy['column'], $this->sortBy['direction']);
        }

        if (!empty($this->filter)) {
            $compiler->filterFromString($this->filter);
        }

        return $compiler->paginate($this->perPage);
    }

    protected function validateFields(): array
    {
        $rulesCompiler = new RecordRulesCompiler($this->collection);
        return $this->validate($rulesCompiler->getRules());
    }

    public function save(): void
    {
        $this->validateFields();

        $recordId = $this->form['id_old'] ?? null;

        $status = $recordId ? 'Updated' : 'Created';

        $record = null;

        if ($recordId) {
            $record = $this->collection->recordQueryCompiler()
                ->filter('id', '=', $recordId)
                ->firstRaw();
        }

        if ($record) {
            unset($this->form['id_old']);
            $record->update([
                'data' => $this->form,
            ]);
        } else {
            Record::create([
                'collection_id' => $this->collection->id,
                'data' => $this->form,
            ]);
        }

        $this->showRecordDetailDrawer = false;

        foreach ($this->fields as $field) {
            $this->form[$field->name] = $field->type === FieldType::Bool ? false : '';
        }
        
        unset($this->tableRows);

        $this->success(
            title: 'Success!',
            description: "$status new {$this->collection->name} record",
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 2000,
        );
    }

    public function show(string $id): void 
    {
        $compiler = new RecordQueryCompiler($this->collection);
        $result = $compiler->filter('id', '=', $id)->first();

        if (!$result) {
            $this->error(
                title: 'Cannot show record.',
                description: "Record not found.",
                position: 'toast-bottom toast-end',
                icon: 'o-information-circle',
                css: 'alert-error',
                timeout: 2000,
            );
            return;
        }

        $data = $result->data;
        $this->form = ['id_old' => $data['id'], ...$data];
        $this->showRecordDetailDrawer = true;
    }

    public function duplicate(string $id): void 
    {
        $compiler = new RecordQueryCompiler($this->collection);
        $result = $compiler->filter('id', '=', $id)->first();

        if (!$result) {
            $this->error(
                title: 'Cannot duplicate record.',
                description: "Record not found.",
                position: 'toast-bottom toast-end',
                icon: 'o-information-circle',
                css: 'alert-error',
                timeout: 2000,
            );
            return;
        }

        $data = $result->data;
        $this->form = [...$data, 'id' => ''];
        $this->showRecordDetailDrawer = true;
    }

    public function promptDelete($id): void
    {
        $this->recordToDelete = array_filter(explode(',', $id));
        $this->showConfirmDeleteDialog = true;
    }

    public function confirmDelete(): void
    {
        $count = count($this->recordToDelete);

        foreach ($this->recordToDelete as $id) {
            $compiler = new RecordQueryCompiler($this->collection);
            $result = $compiler->filter('id', '=', $id)->firstRaw();

            if (!$result) {
                $this->error(
                    title: 'Cannot delete record.',
                    description: "Record not found.",
                    position: 'toast-bottom toast-end',
                    icon: 'o-information-circle',
                    css: 'alert-error',
                    timeout: 2000,
                );
                $this->showConfirmDeleteDialog = false;
                return;
            }

            $result->delete();
        }

        $this->showRecordDetailDrawer = false;
        $this->showConfirmDeleteDialog = false;
        $this->recordToDelete = [];
        $this->selected = [];
        
        unset($this->tableRows);

        $this->success(
            title: 'Success!',
            description: "Deleted $count {$this->collection->name} " . str('record')->plural($count) . ".",
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 2000,
        );
    }

    public function openRecordDrawer()
    {
        foreach ($this->fields as $field) {
            $this->form[$field->name] = $field->type === FieldType::Bool ? false : '';
        }
        
        $this->showRecordDetailDrawer = true;
    }

    public function saveCollectionConfiguration(): void
    {
        $this->validate([
            'collectionForm.name' => 'required|string|regex:/^[a-zA-Z_]+$/|max:255',
        ],
        [
            'collectionForm.name.regex' => 'Only letters and underscore are allowed.'
        ]);

        $oldFields = $this->fields->keyBy('id')->toArray();
        $newFields = collect($this->collectionForm['fields'])->keyBy('id')->toArray();

        $validationRules = [];
        $validationMessages = [];
        
        foreach ($this->collectionForm['fields'] as $index => $fieldData) {
            $fieldId = $fieldData['id'] ?? null;
            $oldField = $oldFields[$fieldId] ?? null;
            
            if ($oldField && $oldField['locked']) {
                if ($oldField['name'] !== $fieldData['name']) {
                    $this->addError(
                        "collectionForm.fields.{$index}.name",
                        "Field '{$oldField['name']}' is locked and cannot be renamed."
                    );
                }
                if ($oldField['type'] !== $fieldData['type']) {
                    $this->addError(
                        "collectionForm.fields.{$index}.type",
                        "Field '{$oldField['name']}' is locked and its type cannot be changed."
                    );
                }
            }

            $validationRules["collectionForm.fields.{$index}.name"] = 'required|string|regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/|max:255';
            $validationMessages["collectionForm.fields.{$index}.name.regex"] = 'Field name must start with a letter or underscore and contain only letters, numbers, and underscores.';
            
            $validationRules["collectionForm.fields.{$index}.type"] = 'required|string';
            
            if (isset($fieldData['type']) && $fieldData['type'] === FieldType::Text->value) {
                if (isset($fieldData['min_length'])) {
                    $validationRules["collectionForm.fields.{$index}.min_length"] = 'nullable|integer|min:0';
                }
                if (isset($fieldData['max_length'])) {
                    $validationRules["collectionForm.fields.{$index}.max_length"] = 'nullable|integer|min:1|max:65535';
                }
                if (isset($fieldData['min_length']) && isset($fieldData['max_length'])) {
                    if ($fieldData['min_length'] > $fieldData['max_length']) {
                        $this->addError(
                            "collectionForm.fields.{$index}.min_length",
                            "Minimum length cannot be greater than maximum length."
                        );
                    }
                }
            }
        }

        if (!empty($validationRules)) {
            $this->validate($validationRules, $validationMessages);
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            $this->error(
                title: 'Validation Failed',
                description: "Please fix the errors and try again.",
                position: 'toast-bottom toast-end',
                icon: 'o-exclamation-circle',
                css: 'alert-error',
                timeout: 5000,
            );
            return;
        }

        $changes = [];
        foreach ($this->collectionForm['fields'] as $fieldData) {
            $fieldId = $fieldData['id'] ?? null;
            $oldField = $oldFields[$fieldId] ?? null;
            
            if ($oldField) {
                $fieldChanges = [];
                
                foreach (['name', 'type', 'required', 'unique', 'indexed', 'hidden', 'min_length', 'max_length'] as $prop) {
                    $oldValue = $oldField[$prop] ?? null;
                    $newValue = $fieldData[$prop] ?? null;
                    
                    if ($oldValue != $newValue) {
                        $fieldChanges[$prop] = [
                            'old' => $oldValue,
                            'new' => $newValue,
                        ];
                    }
                }
                
                if (!empty($fieldChanges)) {
                    $changes[$fieldData['name']] = $fieldChanges;
                }
            }
        }

        $this->collection->update([
            'name' => $this->collectionForm['name'],
        ]);

        $newFieldIds = [];
        
        foreach ($this->collectionForm['fields'] as $fieldData) {
            $fieldId = $fieldData['id'] ?? null;
            
            if (isset($fieldData['_deleted']) && $fieldData['_deleted']) {
                continue;
            }
            
            if ($fieldId) {
                $field = CollectionField::find($fieldId);
                
                if ($field) {
                    $newFieldIds[] = $fieldId;
                    $updateData = [];
                    
                    if (!$field->locked) {
                        $updateData['name'] = $fieldData['name'];
                        $updateData['type'] = $fieldData['type'];
                    }
                    
                    if (isset($fieldData['required'])) {
                        $updateData['required'] = $fieldData['required'];
                    }
                    if (isset($fieldData['unique'])) {
                        $updateData['unique'] = $fieldData['unique'];
                    }
                    if (isset($fieldData['indexed'])) {
                        $updateData['indexed'] = $fieldData['indexed'];
                    }
                    if (isset($fieldData['hidden'])) {
                        $updateData['hidden'] = $fieldData['hidden'];
                    }
                    if (isset($fieldData['min_length'])) {
                        $updateData['min_length'] = $fieldData['min_length'] ?? 0;
                    }
                    if (isset($fieldData['max_length'])) {
                        $updateData['max_length'] = $fieldData['max_length'] ?? 5000;
                    }
                    
                    if (!empty($updateData)) {
                        $field->update($updateData);
                    }
                }
            } else {
                $newField = $this->collection->fields()->create([
                    'name' => $fieldData['name'],
                    'type' => $fieldData['type'],
                    'required' => $fieldData['required'] ?? false,
                    'unique' => $fieldData['unique'] ?? false,
                    'indexed' => $fieldData['indexed'] ?? false,
                    'locked' => $fieldData['locked'] ?? false,
                    'hidden' => $fieldData['hidden'] ?? false,
                    'min_length' => $fieldData['min_length'] ?? 0,
                    'max_length' => $fieldData['max_length'] ?? 5000,
                ]);
                $newFieldIds[] = $newField->id;
            }
        }

        $deletedCount = 0;
        foreach ($this->collectionForm['fields'] as $fieldData) {
            if (isset($fieldData['_deleted']) && $fieldData['_deleted'] && isset($fieldData['id'])) {
                if (!($fieldData['locked'] ?? false)) {
                    CollectionField::find($fieldData['id'])?->delete();
                    $deletedCount++;
                }
            }
        }


        $this->fields = $this->collection->fresh()->fields;
        $this->showConfigureCollectionDrawer = false;

        if (!empty($changes)) {
            \Log::info('Collection configuration changes', [
                'collection' => $this->collection->name,
                'changes' => $changes,
            ]);
        }

        $this->success(
            title: 'Success!',
            description: "Collection configuration updated successfully" . (!empty($changes) ? " (" . count($changes) . " ". str('field')->plural(count($changes)) ." modified)" : "") . ($deletedCount > 0 ? " (" . $deletedCount . " ". str('field')->plural($deletedCount) ." deleted)" : ""),
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 2000,
        );
    }

    public function openConfigureCollectionDrawer()
    {
        $this->collectionForm = $this->collection->toArray();
        $this->collectionForm['fields'] = $this->fields->toArray();
        $this->showConfigureCollectionDrawer = true;
    }

    public function duplicateField(int $index): void
    {
        if (!isset($this->collectionForm['fields'][$index])) {
            $this->error(
                title: 'Error',
                description: "Field not found.",
                position: 'toast-bottom toast-end',
                icon: 'o-exclamation-circle',
                css: 'alert-error',
                timeout: 1500,
            );
            return;
        }

        $fieldToDuplicate = $this->collectionForm['fields'][$index];
        
        $newField = $fieldToDuplicate;
        unset($newField['id']);
        $newField['name'] = $fieldToDuplicate['name'] . '_copy';
        $newField['locked'] = false;
        
        $fields = $this->collectionForm['fields'];
        array_splice($fields, $index + 1, 0, [$newField]);
        $this->collectionForm['fields'] = $fields;

        $this->info(
            title: 'Field Duplicated',
            description: "Field '{$fieldToDuplicate['name']}' has been duplicated.",
            position: 'toast-bottom toast-end',
            icon: 'o-document-duplicate',
            css: 'alert-info',
            timeout: 1500,
        );
    }

    public function deleteField(int $index): void
    {
        if (!isset($this->collectionForm['fields'][$index])) {
            $this->error(
                title: 'Error',
                description: "Field not found.",
                position: 'toast-bottom toast-end',
                icon: 'o-exclamation-circle',
                css: 'alert-error',
                timeout: 1500,
            );
            return;
        }

        $fieldToDelete = $this->collectionForm['fields'][$index];
        
        if ($fieldToDelete['locked'] ?? false) {
            $this->error(
                title: 'Cannot Delete',
                description: "Field '{$fieldToDelete['name']}' is locked and cannot be deleted.",
                position: 'toast-bottom toast-end',
                icon: 'o-lock-closed',
                css: 'alert-error',
                timeout: 1500,
            );
            return;
        }

        $this->collectionForm['fields'][$index]['_deleted'] = true;

    }

    public function restoreField(int $index): void
    {
        if (!isset($this->collectionForm['fields'][$index])) {
            $this->error(
                title: 'Error',
                description: "Field not found.",
                position: 'toast-bottom toast-end',
                icon: 'o-exclamation-circle',
                css: 'alert-error',
                timeout: 1500,
            );
            return;
        }

        $fieldToRestore = $this->collectionForm['fields'][$index];
        
        unset($this->collectionForm['fields'][$index]['_deleted']);
    }

    public function addNewField(): void
    {
        $newField = [
            'name' => 'new_field_' . time(),
            'type' => FieldType::Text->value,
            'required' => false,
            'unique' => false,
            'indexed' => false,
            'locked' => false,
            'hidden' => false,
            'min_length' => 0,
            'max_length' => 5000,
            'rules' => null,
        ];

        $this->collectionForm['fields'][] = $newField;
    }

    #[On('toast')]
    public function showToast($message = 'Ok')
    {
        $this->info(
            title: $message,
            position: 'toast-bottom toast-end',
            icon: 'o-information-circle',
            css: 'alert-info',
            timeout: 2000,
        );
    }

}

?>

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
        @row-selection="console.log($event.detail)"
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
            <div class="flex flex-col w-20">
                <p>{{ Carbon::parse($row->created)->format('Y-m-d') }}</p>
                <p class="text-xs opacity-80">{{ Carbon::parse($row->created)->format('H:i:s') }}</p>
            </div>
        @endscope

        @scope('cell_updated', $row)
            <div class="flex flex-col w-20">
                <p>{{ Carbon::parse($row->created)->format('Y-m-d') }}</p>
                <p class="text-xs opacity-80">{{ Carbon::parse($row->created)->format('H:i:s') }}</p>
            </div>
        @endscope

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

    {{-- === MODALS === --}}

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
                @elseif ($field->name === 'password' && $field->collection->type === CollectionType::Auth)
                    <x-password :label="$field->name" wire:model="form.{{ $field->name }}" password-icon="o-lock-closed" placeholder="Fill to change password..." />
                    @continue
                @endif

                @switch($field->type)
                    @case(\App\Enums\FieldType::Bool)
                        <x-toggle :label="$field->name" wire:model="form.{{ $field->name }}" />
                        @break
                    @case(\App\Enums\FieldType::Email)
                        <x-input :label="$field->name" type="email" wire:model="form.{{ $field->name }}" icon="o-envelope" :required="$field->required"  />
                        @break
                    @case(\App\Enums\FieldType::Number)
                        <x-input :label="$field->name" type="number" wire:model="form.{{ $field->name }}" icon="o-hashtag" :required="$field->required"  />
                        @break
                    @case(\App\Enums\FieldType::Datetime)
                        <x-input :label="$field->name" type="datetime" wire:model="form.{{ $field->name }}" icon="o-calendar-days" :required="$field->required"  />
                        @break
                    @default
                        <x-input :label="$field->name" wire:model="form.{{ $field->name }}" icon="lucide.text-cursor"  />
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
                    <div class="space-y-2 px-0.5">
                        @foreach($collectionForm['fields'] as $index => $field)
                            @php($field = new CollectionField($field))
                            @php($isDeleted = isset($collectionForm['fields'][$index]['_deleted']) && $collectionForm['fields'][$index]['_deleted'])

                            <x-collapse separator :class="$isDeleted ? 'opacity-50 bg-error/5' : ''">
                                <x-slot:heading>
                                    <div class="flex items-center gap-2 w-full">
                                        <x-icon name="{{ $field->getIcon() }}" class="w-4 h-4" />
                                        <span class="font-semibold" class="{{ $isDeleted ? 'line-through' : '' }}">{{ $field->name }}</span>
                                        <x-badge value="{{ $field->type->value }}" class="badge-sm badge-ghost" />
                                        @if($isDeleted)
                                            <x-badge value="Marked for Deletion" class="badge-sm badge-error hidden md:block" />
                                        @endif
                                    </div>
                                </x-slot:heading>
                                <x-slot:content>
                                    @if($isDeleted)
                                        <div class="flex items-center flex-wrap gap-4 justify-between p-4 bg-error/10 rounded-lg">
                                            <div>
                                                <p class="font-semibold text-error">This field will be deleted when you save.</p>
                                            </div>
                                            <x-button label="Restore" icon="o-arrow-uturn-left" wire:click="restoreField({{ $index }})" class="btn-sm btn-primary" />
                                        </div>
                                    @else
                                    <div class="space-y-3 pt-2">
                                        <div class="grid grid-cols-2 gap-4">
                                                <x-input label="Name" wire:model.blur="collectionForm.fields.{{ $index }}.name" :disabled="$field->locked == true" />
                                                <x-select label="Type" wire:model.live="collectionForm.fields.{{ $index }}.type" :options="FieldType::toArray()" :icon="$field->getIcon()" :disabled="$field->locked == true" />
                                                @if ($field->type === FieldType::Text && $field->locked != true)
                                                    <x-input label="Min Length" type="number" wire:model="collectionForm.fields.{{ $index }}.min_length" placeholder="Default to 0" min="0" :disabled="$field->locked == true" />
                                                    <x-input label="Max Length" type="number" wire:model="collectionForm.fields.{{ $index }}.max_length" placeholder="Default to 5000" min="0" :disabled="$field->locked == true" />
                                                @endif
                                        </div>
                                        <div class="flex items-baseline justify-between gap-6">
                                            <div class="flex items-center flex-wrap gap-4">
                                                <x-toggle label="Nonempty" hint="Value cannot be empty" wire:model="collectionForm.fields.{{ $index }}.required" :disabled="$field->locked == true" />
                                                <x-toggle label="Hidden" hint="Hide field from API response" wire:model="collectionForm.fields.{{ $index }}.hidden" :disabled="$field->locked == true" />
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
                        @endforeach

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