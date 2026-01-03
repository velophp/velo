<?php

namespace App\Livewire;

use App\Enums\{FieldType, CollectionType};
use App\Models\{Collection, CollectionField, Record};
use App\Services\{RecordQueryCompiler,RecordRulesCompiler};
use App\Traits\FileLibrarySync;
use Livewire\Attributes\{Computed, Title, On, Rule};
use Livewire\Component;
use Livewire\{WithFileUploads, WithPagination};
use Mary\Traits\Toast;
use Illuminate\Support\Collection as LaravelCollection;

class CollectionPage extends Component
{
    use WithFileUploads, WithPagination, Toast, FileLibrarySync;

    public Collection $collection;
    public $fields;
    public array $breadcrumbs = [];
    public bool $showRecordDetailDrawer = false;
    public bool $showConfirmDeleteDialog = false;
    public bool $showConfigureCollectionDrawer = false;
    public array $recordToDelete = [];
    public array $collectionForm = ['fields' => []];
    public string $tabSelected = 'fields-tab';

    // Form State
    public array $form = [];

    // File Library State
    #[Rule(['files.*.*' => 'image|max:10240'])]
    public array $files = []; // Temp files for image library
    public array $library = [];

    // Table State
    public int $perPage = 15;
    public string $filter = '';
    public array $sortBy = ['column' => 'created', 'direction' => 'desc'];
    public array $selected = [];

    public function mount(Collection $collection): void
    {
        $this->collection = $collection;
        $this->fields = $collection->fields;
        
        $this->library = [];

        foreach ($this->fields as $field) {
            $this->form[$field->name] = $field->type === FieldType::Bool ? false : '';
            
            if ($field->type === FieldType::File) {
                $this->files[$field->name] = [];
                $this->library[$field->name] = collect([]);
            }
        }

        $this->breadcrumbs = [
            ['link' => route('home'), 'icon' => 's-home'],
            ['label' => ucfirst(request()->segment(2))],
            ['label' => $this->collection->name]
        ];
    }

    public function render()
    {
        return view('livewire.collection-page');
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
        $parseFiles = fn($f) => empty($f) ? '-' : implode(' ', array_map(fn($v) => '[IMAGE][' . $v->url . ']', $f));

        return $this->fields->map(function ($f) use ($parseFiles) {
            $headers = [
                'key' => $f->name,
                'label' => $f->name,
                'format' => null,
            ];

            if ($f->type == FieldType::Datetime) {
                $headers['format'] = ['date', 'Y-m-d H:i:s'];
            } elseif ($f->type == FieldType::Bool) {
                $headers['format'] = fn($row, $field) => $field ? 'Yes' : 'No';
            } elseif ($f->type == FieldType::File) {
                $headers['format'] = fn($row, $field) => $parseFiles($field);
            } else {
                $headers['format'] = fn($row, $field) => $field ?: '-';
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
            $record = $this->collection->queryCompiler()
                ->filter('id', '=', $recordId)
                ->firstRaw();
        }

        unset($this->form['id_old']);

        if ($record) {
            
            // Sync file fields to storage and update form
            foreach ($this->fields as $field) {
                if ($field->type === FieldType::File) {
                    $existingLibrary = is_array($record->data) && isset($record->data[$field->name]) 
                        ? $record->data[$field->name] 
                        : [];
                    
                    if (!empty($this->files[$field->name])) {
                        $updatedLibrary = $this->syncMedia(
                            library: "library.{$field->name}",
                            files: "files.{$field->name}",
                            storage_subpath: "collections/{$this->collection->name}/{$record->data['id']}/{$field->name}",
                            existingLibrary: $existingLibrary
                        );
                        
                        $this->form[$field->name] = $updatedLibrary->toArray();
                    } else {
                        // Just update library if files were removed but not added
                        $currentLibrary = data_get($this, "library.{$field->name}");
                        if ($currentLibrary instanceof LaravelCollection) {
                            $this->form[$field->name] = $currentLibrary->toArray();
                        }
                    }
                }
            }
            
            $record->update([
                'data' => $this->form,
            ]);
        } else {
            $record = Record::create([
                'collection_id' => $this->collection->id,
                'data' => $this->form,
            ]);

            // Sync file fields to storage and update form
            foreach ($this->fields as $field) {
                if ($field->type === FieldType::File && !empty($this->files[$field->name])) {
                    $updatedLibrary = $this->syncMedia(
                        library: "library.{$field->name}",
                        files: "files.{$field->name}",
                        storage_subpath: "collections/{$this->collection->name}/{$record->data['id']}/{$field->name}",
                        existingLibrary: []
                    );
                    
                    $this->form[$field->name] = $updatedLibrary->toArray();
                }
            }
            
            // Update record with file URLs if any were uploaded
            if (collect($this->fields)->where('type', FieldType::File)->isNotEmpty()) {
                $record->update([
                    'data' => $this->form,
                ]);
            }
        }

        $this->showRecordDetailDrawer = false;

        foreach ($this->fields as $field) {
            $this->form[$field->name] = $field->type === FieldType::Bool ? false : '';
            
            if ($field->type === FieldType::File) {
                $this->files[$field->name] = [];
                $this->library[$field->name] = collect([]);
            }
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

        $data = collect($result->data);
        $data = ['id_old' => $data['id'], ...$data];
        $this->openRecordDrawer($data);
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

        $data = collect($result->data);
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

    public function openRecordDrawer($data = null)
    {
        if (!$data) {
            foreach ($this->fields as $field) {
                if ($field->type === FieldType::Bool) {
                    $this->form[$field->name] = false;
                    continue;
                }

                if ($field->type === FieldType::File) {
                    $this->form[$field->name] = [];
                    $this->files[$field->name] = [];
                    $this->library[$field->name] = collect([]);
                    continue;
                }

                $this->form[$field->name] = '';
            }
        } else {
            $this->form = $data;
            
            foreach ($this->fields as $field) {
                if ($field->type === FieldType::File) {
                    $this->files[$field->name] = [];
                    
                    // Load existing library data from form if it exists
                    $existingLibrary = $this->form[$field->name] ?? [];
                    if (is_array($existingLibrary) && !empty($existingLibrary)) {
                        $this->library[$field->name] = collect($existingLibrary);
                    } else {
                        $this->library[$field->name] = collect([]);
                    }
                }
            }
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
                    'order' => $fieldData['order'] ?? 999,
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
        $this->collectionForm['fields'] = $this->fields->map(fn ($field) => $field->toArray())->toArray();
        $this->showConfigureCollectionDrawer = false;

        $this->dispatch('fields-updated');

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

        $this->dispatch('fields-updated');

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
        
        $this->dispatch('fields-updated');
    }

    public function updateFieldOrder(array $orderedIds): void
    {
        $reorderedFields = [];
        
        foreach ($orderedIds as $order => $id) {
            foreach ($this->collectionForm['fields'] as $field) {
                if (($field['id'] ?? null) == $id || ($field['name'] ?? null) == $id) {
                    $field['order'] = $order;
                    $reorderedFields[] = $field;
                    
                    // Update order in database for existing fields
                    if (isset($field['id'])) {
                        CollectionField::where('id', $field['id'])->update(['order' => $order]);
                    }
                    break;
                }
            }
        }
        
        // Add any fields that weren't in the ordered list (new fields without IDs)
        foreach ($this->collectionForm['fields'] as $field) {
            $fieldId = $field['id'] ?? $field['name'] ?? null;
            if ($fieldId && !in_array($fieldId, $orderedIds)) {
                $field['order'] = count($reorderedFields);
                $reorderedFields[] = $field;
            }
        }
        
        $this->collectionForm['fields'] = $reorderedFields;
        
        // Don't re-render after reordering to prevent SortableJS conflicts
        $this->skipRender();
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