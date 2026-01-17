<?php

namespace App\Livewire;

use App\Enums\CollectionType;
use App\Enums\FieldType;
use App\Exceptions\IndexOperationException;
use App\Exceptions\InvalidRecordException;
use App\Models\Collection;
use App\Models\CollectionField;
use App\Models\Record;
use App\Rules\RuleExpression;
use App\Services\IndexStrategies\MysqlIndexStrategy;
use App\Services\RecordRulesCompiler;
use App\Traits\FileLibrarySync;
use DB;
use Illuminate\Support\Collection as LaravelCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class CollectionPage extends Component
{
    use FileLibrarySync;
    use Toast;
    use WithFileUploads;
    use WithPagination;

    #[Url]
    public $recordId = '';

    public Collection $collection;

    public $fields;

    public array $breadcrumbs = [];

    public bool $showConfirmDeleteDialog = false;

    // Record Form State
    public bool $showRecordDrawer = false;

    public array $form = [];

    // Collection Form State
    public bool $showConfigureCollectionDrawer = false;

    public $collectionForm = ['fields' => []];

    public string $tabSelected = 'fields-tab';

    public string $fieldOpen = '';

    public string $optionOpen = '';

    // Field Index Form State
    public bool $showFieldIndexModal = false;

    public array $fieldsToBeIndexed = [];

    public LaravelCollection $collectionIndexes;

    public bool $isUniqueIndex = false;

    // File Library State
    #[Rule(['files.*.*' => 'file'])]
    public array $files = []; // Temp files for image library

    public array $library = [];

    // Table State
    public int $perPage = 15;

    public string $filter = '';

    public array $sortBy = ['column' => 'created', 'direction' => 'asc'];

    public array $selected = [];

    public array $fieldsVisibility = [];

    public array $recordToDelete = [];

    // Relation Picker States
    public bool $showRelationPickerModal = false;

    public array $relationPicker = [
        'collection' => null,
        'fieldName' => '',
        'multiple' => false,
        'search' => '',
        'records' => [],
        'selected' => [],
        'displayField' => 'id',
    ];

    // Helpers
    public array $optionsBool = [['id' => 0, 'name' => 'False'], ['id' => 1, 'name' => 'True']];

    public array $optionsBoolSingleMultiple = [['id' => 0, 'name' => 'Single'], ['id' => 1, 'name' => 'Multiple']];

    public array $mimeTypes = [];

    public array $mimeTypePresets = [];

    public array $tinyMceConfig = [];

    public array $availableCollections = [];

    public function mount(Collection $collection): void
    {
        $this->collection = $collection;
        $this->fields = $collection->fields->sortBy('order')->values();
        $this->library = [];

        // Preload values
        $this->fillFieldsVisibility();
        $this->fillRecordForm();
        $this->fillCollectionForm();
        $this->fillCollectionIndexes();

        $this->breadcrumbs = [
            ['link' => route('home'), 'icon' => 's-home'],
            ['label' => ucfirst(request()->route()->getName())],
            ['label' => $this->collection->name],
        ];

        $this->availableCollections = [
            ['id' => '', 'name' => 'Choose collection', 'disabled' => true],
            ...Collection::whereNot('id', $this->collection->id)
                ->where('project_id', $this->collection->project_id)
                ->get()
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
                ->toArray(),
        ];

        $this->mimeTypes = config('larabase.available_mime_types');
        $this->mimeTypePresets = config('larabase.mime_types_presets');
        $this->tinyMceConfig = config('larabase.tinymce_config');

        if ($this->recordId) {
            $record = $this->collection->records()->filter('id', '=', $this->recordId)->first();
            if ($record) {
                $this->fillRecordForm($record->data->toArray());
                $this->showRecordDrawer = true;
            }
        }
    }

    public function render()
    {
        return view('livewire.collection-page')->title("Collection - {$this->collection->name}");
    }

    /* === START RELATION PICKER === */

    public function openRelationPicker(string $fieldName)
    {
        $field = $this->fields->firstWhere('name', $fieldName);
        if (!$field || FieldType::Relation !== $field->type) {
            return $this->showToast('Invalid field.');
        }

        $collectionId = $field->options->collection;
        $collection = Collection::find($collectionId);

        if (!$collection) {
            return $this->showToast('Collection not found.');
        }

        $priority = config('larabase.relation_display_fields');

        $displayField = $collection->fields
            ->whereIn('name', $priority)
            ->sortBy(fn ($field) => array_search($field->name, $priority))
            ->first()?->name;
        if (!$displayField) {
            $displayField = 'id';
        }

        $oldValues = \is_array($this->form[$fieldName]) ? $this->form[$fieldName] : [];

        $this->relationPicker = [
            'collection' => $collection,
            'fieldName' => $fieldName,
            'multiple' => $field->options->multiple,
            'search' => '',
            'records' => [],
            'selected' => [...$oldValues],
            'displayField' => $displayField,
        ];

        $this->loadRelationRecords();
        $this->showRelationPickerModal = true;
    }

    public function loadRelationRecords()
    {
        if (!$this->relationPicker['collection']) {
            return;
        }

        $query = $this->relationPicker['collection']->records();

        if (!empty($this->relationPicker['search'])) {
            $query->filterFromString($this->relationPicker['search']);
        }

        $this->relationPicker['records'] = $query->buildQuery()->get();
    }

    public function updatedRelationPickerSearch()
    {
        $this->loadRelationRecords();
    }

    public function toggleRelationRecord(string $recordId)
    {
        $selected = $this->relationPicker['selected'] ?? [];

        if (\in_array($recordId, $selected)) {
            $this->relationPicker['selected'] = array_values(array_filter($selected, fn ($id) => $id !== $recordId));
        } else {
            if ($this->relationPicker['multiple']) {
                $this->relationPicker['selected'][] = $recordId;
            } else {
                $this->relationPicker['selected'] = [$recordId];
            }
        }
    }

    public function saveRelationSelection()
    {
        $fieldName = $this->relationPicker['fieldName'];
        $this->form[$fieldName] = $this->relationPicker['selected'];
        $this->showRelationPickerModal = false;
    }

    /* === END RELATION PICKER === */

    /* === START TABLE METHODS === */

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function toggleField(string $field)
    {
        if (!\array_key_exists($field, $this->fieldsVisibility)) {
            return;
        }

        $this->fieldsVisibility[$field] = !$this->fieldsVisibility[$field];
    }

    #[Computed]
    public function tableHeaders(): array
    {
        $this->fillFieldsVisibility();

        return $this->fields
            ->filter(fn ($f) => isset($this->fieldsVisibility[$f->name]) && $this->fieldsVisibility[$f->name])
            ->sortBy('order')
            ->map(function ($f) {
                $headers = [
                    'key' => $f->name,
                    'label' => $f->name,
                    'format' => null,
                ];

                if (FieldType::Datetime == $f->type) {
                    $headers['format'] = ['date', 'Y-m-d H:i:s'];
                } elseif (FieldType::Bool == $f->type) {
                    $headers['format'] = fn ($row, $field) => $field ? 'Yes' : 'No';
                } elseif (FieldType::File == $f->type) {
                    $headers['format'] = fn ($row, $field) => json_encode($field);
                } else {
                    $headers['format'] = fn ($row, $field) => $field ?: '-';
                }

                return $headers;
            })->toArray();
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
            if (CollectionType::Auth === $this->collection->type && $this->fields->firstWhere('name', 'password')) {
                $recordData->password = Str::repeat('*', 12);
            }

            return $recordData;
        });

        return $data;
    }

    public function fillFieldsVisibility()
    {
        foreach ($this->fields as $i => $field) {
            // Only initialize if not already set (preserves user toggles)
            if (!array_key_exists($field->name, $this->fieldsVisibility)) {
                $this->fieldsVisibility[$field->name] = true;

                if (CollectionType::Auth === $this->collection->type) {
                    if ('password' === $field->name) {
                        $this->fieldsVisibility['password'] = false;
                    }
                }
            }
        }
    }

    /* === END TABLE METHODS === */

    /* === START RECORD OPERATION === */

    public function fillRecordForm($data = null)
    {
        $this->resetValidation();
        if (null == $data) {
            foreach ($this->fields as $field) {
                if (FieldType::Bool === $field->type) {
                    $this->form[$field->name] = false;

                    continue;
                }

                if (FieldType::File === $field->type) {
                    $this->form[$field->name] = [];
                    $this->files[$field->name] = [];
                    $this->library[$field->name] = collect([]);

                    continue;
                }

                $this->form[$field->name] = '';
            }

            return;
        }

        $this->form = $data;
        foreach ($this->fields as $field) {
            if ('password' == $field->name && CollectionType::Auth === $this->collection->type) {
                $this->form['password'] = Str::repeat('*', 12);

                continue;
            }

            if (FieldType::File === $field->type) {
                $this->files[$field->name] = [];

                // Load existing library data from form if it exists
                $existingLibrary = $this->form[$field->name] ?? [];
                if (\is_array($existingLibrary) && !empty($existingLibrary)) {
                    $this->library[$field->name] = collect($existingLibrary);
                } else {
                    $this->library[$field->name] = collect([]);
                }
            }

            if (FieldType::Relation === $field->type) {
                $this->relationPicker['selected'] = \is_array($data[$field->name]) ? $data[$field->name] : [];
            }
        }
    }

    public function showRecord(string $id): void
    {
        $compiler = $this->collection->records();
        $result = $compiler->filter('id', '=', $id)->first();

        if (!$result) {
            $this->error(
                title: 'Cannot show record.',
                description: 'Record not found.',
                position: 'toast-bottom toast-end',
                icon: 'o-information-circle',
                css: 'alert-error',
                timeout: 2000,
            );

            return;
        }

        $data = collect($result->data);
        $data = ['id_old' => $data['id'], ...$data];
        $this->fillRecordForm($data);
        $this->showRecordDrawer = true;
    }

    public function duplicateRecord(string $id): void
    {
        $compiler = $this->collection->records();
        $result = $compiler->filter('id', '=', $id)->first();

        if (!$result) {
            $this->error(
                title: 'Cannot duplicate record.',
                description: 'Record not found.',
                position: 'toast-bottom toast-end',
                icon: 'o-information-circle',
                css: 'alert-error',
                timeout: 2000,
            );

            return;
        }

        $fileTypeFields = $this->fields
            ->filter(fn ($f) => FieldType::File === $f->type)
            ->mapWithKeys(fn ($f) => [$f->name => []])
            ->toArray();

        $data = collect($result->data);
        $data = [...$data, 'id' => '', ...$fileTypeFields];
        $this->fillRecordForm($data);
    }

    public function promptDeleteRecord($id): void
    {
        $this->recordToDelete = array_filter(explode(',', $id));
        $this->showConfirmDeleteDialog = true;
    }

    public function confirmDeleteRecord(): void
    {
        try {
            $count = count($this->recordToDelete);

            foreach ($this->recordToDelete as $id) {
                $compiler = $this->collection->records();
                $result = $compiler->filter('id', '=', $id)->firstRaw();

                if (!$result) {
                    $this->error(
                        title: 'Cannot delete record.',
                        description: 'Record not found.',
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

            $this->showRecordDrawer = false;
            $this->showConfirmDeleteDialog = false;
            $this->recordToDelete = [];
            $this->selected = [];

            unset($this->tableRows);

            $this->success(
                title: 'Success!',
                description: "Deleted $count {$this->collection->name} ".str('record')->plural($count).'.',
                position: 'toast-bottom toast-end',
                icon: 'o-check-circle',
                css: 'alert-success',
                timeout: 2000,
            );
        } catch (InvalidRecordException $e) {
            $this->showError($e->getMessage());
        }
    }

    protected function validateRecord(): void
    {
        $recordId = $this->form['id_old'] ?? null;

        $attributes = [];
        $rules = app(RecordRulesCompiler::class)
            ->forCollection($this->collection)
            ->using(new MysqlIndexStrategy())
            ->ignoreId($recordId)
            ->withForm($this->form)
            ->compile(prefix: 'form.');

        foreach ($rules as $ruleName => $rule) {
            if (str_ends_with($ruleName, '.*')) {
                $index = Str::between($ruleName, 'fields.', '.options');
                $attributes[$ruleName] = "value on [{$index}]";

                continue;
            }

            $newName = explode('.', $ruleName);
            $newName = end($newName);
            $attributes[$ruleName] = Str::lower(Str::headline($newName));
        }

        try {
            $this->validate($rules, [], $attributes);
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    public function saveRecord(): void
    {
        $this->validateRecord();

        $recordId = $this->form['id_old'] ?? null;
        $status = $recordId ? 'Updated' : 'Created';

        $record = $recordId
            ? $this->collection->records()->filter('id', '=', $recordId)->firstRaw()
            : null;

        if ($record) {
            // Sync file fields to storage and update form
            foreach ($this->fields as $field) {
                if (FieldType::File === $field->type) {
                    $existingLibrary = \is_array($record->data) && isset($record->data[$field->name])
                        ? $record->data[$field->name]
                        : [];

                    if (!empty($this->files[$field->name])) {
                        $updatedLibrary = $this->syncMedia(
                            library: "library.{$field->name}",
                            files: "files.{$field->name}",
                            storage_subpath: "collections/{$this->collection->name}/{$record->data['id']}/{$field->name}",
                            existingLibrary: $existingLibrary,
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

            unset($this->form['id_old']);
            $record->update([
                'data' => $this->form,
            ]);
        } else {
            unset($this->form['id_old']);
            $record = Record::create([
                'collection_id' => $this->collection->id,
                'data' => $this->form,
            ]);

            // Sync file fields to storage and update form
            foreach ($this->fields as $field) {
                if (FieldType::File === $field->type && !empty($this->files[$field->name])) {
                    $updatedLibrary = $this->syncMedia(
                        library: "library.{$field->name}",
                        files: "files.{$field->name}",
                        storage_subpath: "collections/{$this->collection->name}/{$record->data['id']}/{$field->name}",
                        existingLibrary: [],
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

        $this->showRecordDrawer = false;

        foreach ($this->fields as $field) {
            $this->form[$field->name] = FieldType::Bool === $field->type ? false : '';

            if (FieldType::File === $field->type) {
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

    /* === END RECORD OPERATION === */

    /* === START COLLECTION CONFIGURATION === */

    public function updatedCollectionForm($value, $key)
    {
        $tokens = explode('.', $key);

        if (str_starts_with($key, 'api_rules')) {
            return;
        }
        if (str_starts_with($key, 'options')) {
            return;
        }

        $index = $tokens[1] ?? null;

        if (!$index) {
            return;
        }

        if ('fields' == $tokens[0] && 'options' == $tokens[2] && 'multiple' == $tokens[3]) {
            $this->collectionForm['fields'][$index]['options']['multiple'] = \intval($value);
        }

        $model = new CollectionField($this->collectionForm['fields'][$index]);
        $this->collectionForm['fields'][$index]['options'] = $model->options->toArray();

        $this->ensureFieldOptionsDefaults($this->collectionForm['fields'][$index]);
    }

    #[Computed]
    public function fieldsToArray()
    {
        return $this->fields->map(function ($f) {
            $fieldArray = $f->toArray();
            if ($f->options) {
                $fieldArray['options'] = $f->options->toArray();
            }

            return $fieldArray;
        })->toArray();
    }

    public function fillCollectionForm()
    {
        $this->resetValidation();
        $this->collectionForm = $this->collection->toArray();
        $this->collectionForm['fields'] = $this->fieldsToArray();

        if (CollectionType::Auth === $this->collection->type && empty($this->collectionForm['options'])) {
            $this->collectionForm['options'] = Collection::getDefaultAuthOptions();
        }

        foreach ($this->collectionForm['fields'] as &$field) {
            $this->ensureFieldOptionsDefaults($field);
        }
    }

    public function updateFieldOrder($ids)
    {
        $orderedIds = array_column($ids, 'value');
        foreach ($orderedIds as $newOrder => $fieldId) {
            foreach ($this->collectionForm['fields'] as &$formField) {
                if ($formField['id'] == $fieldId) {
                    $formField['order'] = $newOrder;
                    break;
                }
            }
        }

        usort($this->collectionForm['fields'], function ($a, $b) {
            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
        });

        $this->collectionForm['fields'] = array_values($this->collectionForm['fields']);

        foreach ($this->collectionForm['fields'] as $index => $_) {
            $this->updatedCollectionForm(null, "fields.$index.order");
        }
    }

    public function addNewField()
    {
        $insertPosition = \count($this->collectionForm['fields']);
        foreach ($this->collectionForm['fields'] as $index => $field) {
            if (\in_array($field['name'], ['created', 'updated'])) {
                $insertPosition = min($insertPosition, $index);
            }
        }

        $newField = [
            'collection_id' => $this->collection->id,
            'id' => time(),
            'name' => 'newField__'.time(),
            'type' => FieldType::Text,
            'order' => $insertPosition,
            'unique' => false,
            'indexed' => false,
            'required' => false,
            'locked' => false,
            'options' => [],
        ];

        $model = new CollectionField($newField);
        $newField['options'] = $model->options->toArray();
        $this->ensureFieldOptionsDefaults($newField);

        array_splice($this->collectionForm['fields'], $insertPosition, 0, [$newField]);
        foreach ($this->collectionForm['fields'] as $index => &$field) {
            $field['order'] = $index;
        }

        $this->fieldOpen = 'collapse_'.$newField['id'];
    }

    public function duplicateField($targetId)
    {
        $field = array_find($this->collectionForm['fields'], fn ($f) => $f['id'] === $targetId);

        if (!$field) {
            $this->error(
                title: 'Cannot duplicate field.',
                description: 'Field not found.',
                position: 'toast-bottom toast-end',
                icon: 'o-information-circle',
                css: 'alert-error',
                timeout: 2000,
            );

            return;
        }

        $targetIndex = $field['order'] + 1;

        $newField = [
            ...$field,
            'collection_id' => $this->collection->id,
            'id' => time(),
            'name' => $field['name'].'__copy',
            'order' => $targetIndex,
            'locked' => false,
            'indexed' => false,
            'unique' => false,
        ];

        array_splice($this->collectionForm['fields'], $targetIndex, 0, [$newField]);
        foreach ($this->collectionForm['fields'] as $index => &$field) {
            $field['order'] = $index;
        }

        $this->fieldOpen = 'collapse_'.$newField['id'];
    }

    public function deleteField($targetId)
    {
        $key = array_find_key($this->collectionForm['fields'], fn ($f) => $f['id'] === $targetId);
        $lockedStatus = $this->fields->where('id', $targetId)->first()?->locked;

        if (!$key) {
            $this->showToast('Field not found.');

            return;
        }

        if ($lockedStatus) {
            $this->showToast('Field is locked.');

            return;
        }

        // Straight to hell if not exists on original data
        if ($this->fields->contains('id', $targetId)) {
            $this->collectionForm['fields'][$key]['_deleted'] = true;

            return;
        }

        unset($this->collectionForm['fields'][$key]);
        foreach ($this->collectionForm['fields'] as $index => &$field) {
            $field['order'] = $index;
        }

        $this->fieldOpen = '';
    }

    public function restoreField($targetId)
    {
        $key = array_find_key($this->collectionForm['fields'], fn ($f) => $f['id'] === $targetId);

        if (!$key) {
            $this->error(
                title: 'Cannot restore field.',
                description: 'Field not found.',
                position: 'toast-bottom toast-end',
                icon: 'o-information-circle',
                css: 'alert-error',
                timeout: 2000,
            );

            return;
        }

        unset($this->collectionForm['fields'][$key]['_deleted']);

        $this->fieldOpen = 'collapse_'.$this->collectionForm['fields'][$key]['id'];
    }

    public function applyFilePresets($fieldIdx, string $presetsName)
    {
        if (!isset($this->mimeTypePresets[$presetsName])) {
            $this->showToast('Preset does not exist.');

            return;
        }

        if (!isset($this->collectionForm['fields'][$fieldIdx]['options']['allowedMimeTypes']) || !\is_array($this->collectionForm['fields'][$fieldIdx]['options']['allowedMimeTypes'])) {
            $this->showToast('Presets can only be applied to file-type fields.');

            return;
        }

        $this->collectionForm['fields'][$fieldIdx]['options']['allowedMimeTypes'] = $this->mimeTypePresets[$presetsName];
    }

    private function ensureFieldOptionsDefaults(array &$field): void
    {
        // Ensure field options have required default empty arrays for UI components
        $requiredDefaultsArray = ['allowedDomains', 'blockedDomains', 'allowedMimeTypes'];

        foreach ($requiredDefaultsArray as $key) {
            if (!isset($field['options'][$key])) {
                $field['options'][$key] = [];
            }
        }

        $requiredDefaultsBool = ['hidden', 'required', 'multiple', 'allowDecimals'];

        foreach ($requiredDefaultsBool as $key) {
            if (in_array($key, ['hidden', 'required'])) {
                if (isset($field[$key])) {
                    $field[$key] = boolval($field[$key]);
                }

                continue;
            }

            if (isset($field['options'][$key])) {
                // dump($field['options'][$key]);
                // $field['options'][$key] = filter_var($field['options'][$key], FILTER_VALIDATE_BOOLEAN);
            }
        }
    }

    protected function validateCollectionForm()
    {
        $rules = [];
        $messages = [];
        $attributes = [];

        $rules['collectionForm.name'] = ['required', 'regex:/^[a-zA-Z_]+$/', 'unique:collections,name,'.$this->collectionForm['id']];

        $expressionRule = new RuleExpression([
            'sys_request',
            ...$this->fields->pluck('name'),
            'SUPERUSER_ONLY',
        ]);

        foreach (['list', 'view', 'create', 'update', 'delete'] as $action) {
            $rules["collectionForm.api_rules.$action"] = ['present', 'nullable', $expressionRule];
        }

        $messages['collectionForm.name.regex'][] = 'Collection name can only contain letters and underscores.';
        $messages['collectionForm.name.unique'][] = 'Collection with the same name already exists.';

        $incomingFields = $this->collectionForm['fields'];

        // Stitch all rules together
        foreach ($incomingFields as $index => $field) {
            $rules["collectionForm.fields.{$index}.name"] = ['required', 'regex:/^[a-zA-Z0-9_]+$/', \Illuminate\Validation\Rule::unique('collection_fields', 'name')->where('collection_id', $this->collection->id)->ignore($field['id'], 'collection_fields.id')];
            $messages["collectionForm.fields.{$index}.name.regex"] = 'Field name can only contain letters, numbers, and underscores.';
            $messages["collectionForm.fields.{$index}.name.unique"] = 'Field with the same name already exists.';

            $rules["collectionForm.fields.{$index}.type"] = ['required', new Enum(type: FieldType::class)];
            $rules["collectionForm.fields.{$index}.required"] = ['boolean'];
            $rules["collectionForm.fields.{$index}.hidden"] = ['boolean'];

            $model = new CollectionField($field);
            $typeRules = $model->options->getValidationRules();
            $typeRuleMessages = $model->options->getValidationMessages();
            $minToMaxMap = [
                'minSize' => 'maxSize',
                'min' => 'max',
                'minLength' => 'maxLength',
            ];

            foreach ($typeRules as $field => $rule) {
                if (isset($minToMaxMap[$field]) && isset($typeRules[$minToMaxMap[$field]])) {
                    $rule[] = "lte:collectionForm.fields.{$index}.options.{$minToMaxMap[$field]}";
                }

                $rules["collectionForm.fields.{$index}.options.{$field}"] = $rule;
            }

            foreach ($typeRuleMessages as $field => $msg) {
                $messages["collectionForm.fields.{$index}.options.{$field}"] = $msg;
            }
        }

        if (CollectionType::Auth === $this->collection->type) {
            // Auth Methods
            $rules['collectionForm.options.auth_methods.standard.enabled'] = ['boolean'];
            $rules['collectionForm.options.auth_methods.standard.fields'] = [
                'required',
                'array',
                function ($attribute, $value, $fail) {
                    if (!in_array('email', $value)) {
                        $fail('The identifiers field must contain "email".');
                    }
                },
            ];
            $rules['collectionForm.options.auth_methods.oauth2.enabled'] = ['boolean'];
            $rules['collectionForm.options.auth_methods.otp.enabled'] = ['boolean'];
            $rules['collectionForm.options.auth_methods.otp.config.duration_s'] = ['required', 'integer', 'min:1'];
            $rules['collectionForm.options.auth_methods.otp.config.generate_password_length'] = ['required', 'integer', 'min:4', 'max:20'];

            // Token Options
            $tokenTypes = [
                'auth_duration',
                'email_verification',
                'password_reset_duration',
                'email_change_duration',
                'protected_file_access_duration',
            ];

            foreach ($tokenTypes as $type) {
                $rules["collectionForm.options.other.tokens_options.{$type}.value"] = ['required', 'integer', 'min:1'];
                $rules["collectionForm.options.other.tokens_options.{$type}.invalidate_previous_tokens"] = ['boolean'];
            }

            // Mail Templates
            $mailTemplates = [
                'verification',
                'password_reset',
                'confirm_email_change',
                'otp_email',
                'login_alert',
            ];

            foreach ($mailTemplates as $template) {
                $rules["collectionForm.options.mail_templates.{$template}.subject"] = ['nullable', 'string', 'max:255'];
                $rules["collectionForm.options.mail_templates.{$template}.body"] = ['nullable', 'string'];
            }
        }

        // Format attributes name
        foreach ($rules as $ruleName => $rule) {
            if (str_ends_with($ruleName, '.*')) {
                $index = Str::between($ruleName, 'fields.', '.options');
                $attributes[$ruleName] = "value on [{$index}]";

                continue;
            }

            $newName = explode('.', $ruleName);
            $newName = $newName[\count($newName) - 1];
            $attributes[$ruleName] = Str::lower(Str::headline($newName));
        }

        try {
            $this->validate($rules, $messages, $attributes);
        } catch (ValidationException $e) {
            $fieldErrors = $e->validator->errors()->get('collectionForm.fields.*');
            foreach ($fieldErrors as $path => $messages) {
                $index = (int) collect(explode('.', $path))->first(fn ($part) => is_numeric($part));
                $field = $this->collectionForm['fields'][$index];
                if ($field) {
                    $this->fieldOpen = 'collapse_'.$field['id'];
                }
            }

            $optionErrors = $e->validator->errors()->get('collectionForm.options.*');
            foreach ($optionErrors as $path => $messages) {
                $name = str($path)->after('options.')->explode('.')->first();
                $this->optionOpen = $name;
            }

            if ($e->validator->errors()->has('collectionForm.options.*')) {
                $this->tabSelected = 'options-tab';
            }

            if ($e->validator->errors()->has('collectionForm.api_rules.*')) {
                $this->tabSelected = 'api-rules-tab';
            }

            throw $e;
        }
    }

    public function saveCollection()
    {
        $this->validateCollectionForm();

        try {
            \DB::beginTransaction();

            $incomingFields = $this->collectionForm['fields'];
            foreach ($incomingFields as $field) {
                $oldField = CollectionField::find($field['id']);

                // Handle deletion of existing fields
                if ($oldField && isset($field['_deleted']) && $field['_deleted']) {
                    if ($oldField->locked) {
                        continue;
                    }

                    $oldField->delete();

                    continue;
                }

                // Skip deleted fields that don't exist in DB
                if (isset($field['_deleted']) && $field['_deleted']) {
                    continue;
                }

                // Handle new fields (id is timestamp, not found in DB)
                if (!$oldField) {
                    CollectionField::create([
                        'collection_id' => $this->collection->id,
                        'name' => $field['name'],
                        'type' => $field['type'],
                        'order' => $field['order'],
                        'required' => $field['required'] ?? false,
                        'locked' => false,
                        'options' => $field['options'] ?? [],
                        'hidden' => $field['hidden'] ?? false,
                    ]);

                    continue;
                }

                // Handle locked fields - only allow updating specific fields
                if ($oldField->locked) {
                    $allowedProperties = ['order'];
                    $allowedOptions = ['allowedDomains', 'blockedDomains', 'minLength', 'maxLength'];
                    $optionsToUpdate = [];
                    $propertiesToUpdate = [];

                    foreach ($allowedOptions as $optionKey) {
                        if (isset($field['options'][$optionKey])) {
                            $optionsToUpdate[$optionKey] = $field['options'][$optionKey];
                        }
                    }

                    foreach ($allowedProperties as $propKey) {
                        if (isset($field[$propKey])) {
                            $propertiesToUpdate[$propKey] = $field[$propKey];
                        }
                    }

                    if (!empty($optionsToUpdate) || !empty($propertiesToUpdate)) {
                        $updateData = $propertiesToUpdate;

                        if (!empty($optionsToUpdate)) {
                            $currentOptions = $oldField->options->toArray();
                            $updateData['options'] = array_merge($currentOptions, $optionsToUpdate);
                        }

                        $oldField->update($updateData);
                    }

                    continue;
                }

                // Prepare update data for unlocked fields (protect reserved properties: locked, collection_id)
                $toUpdateData = [
                    'name' => $field['name'],
                    'type' => $field['type'],
                    'order' => $field['order'],
                    'required' => $field['required'] ?? false,
                    'options' => $field['options'] ?? [],
                    'hidden' => $field['hidden'] ?? false,
                ];

                $oldField->update($toUpdateData);
            }

            $this->collection->update([
                'name' => $this->collectionForm['name'],
                'api_rules' => $this->collectionForm['api_rules'],
                'options' => $this->collectionForm['options'],
            ]);

            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();

            $this->collection = $this->collection->fresh();
            $this->fields = $this->collection->fields->sortBy('order')->values();
            $this->fillCollectionForm();

            $this->showConfigureCollectionDrawer = false;
            $this->fieldOpen = '';

            $this->error(
                title: 'Failed to Save!',
                description: "Failed to save collection configuration. $e",
                position: 'toast-bottom toast-end',
                icon: 'o-check-circle',
                css: 'alert-error',
                timeout: 2000,
            );

            return;
        }

        if ($this->collection->wasChanged('name')) {
            return $this->redirectroute('collections', ['collection' => $this->collection->fresh()]);
        }

        // Refresh collection and fields
        $this->collection = $this->collection->fresh();
        $this->fields = $this->collection->fields->sortBy('order')->values();
        $this->fillCollectionForm();
        $this->dispatch('fields-updated');

        $this->showConfigureCollectionDrawer = false;

        $this->dispatch('fields-updated');
        $this->fieldOpen = '';

        $this->success(
            title: 'Success!',
            description: 'Collection configuration updated successfully.',
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 2000,
        );
    }

    /* === END COLLECTION CONFIGURATION === */

    /* === START INDEX OPERATION === */

    public function updatedShowFieldIndexModal()
    {
        $this->resetValidation('fieldsToBeIndexed');
    }

    public function fillCollectionIndexes()
    {
        $this->collectionIndexes = $this->collection->indexes()->get();
    }

    public function showIndex($indexId)
    {
        if (!$this->collectionIndexes->firstWhere('id', '=', $indexId)) {
            $this->showToast('Index not found.');

            return;
        }

        $index = $this->collectionIndexes->firstWhere('id', '=', $indexId);

        $this->isUniqueIndex = str_starts_with('uq_', $index->name);
        $this->fieldsToBeIndexed = $index->field_names;
        $this->showFieldIndexModal = true;
    }

    public function addNewIndex()
    {
        $this->isUniqueIndex = false;
        $this->fieldsToBeIndexed = [];
        $this->showFieldIndexModal = true;
    }

    public function indexToggleField($field)
    {
        if (FieldType::Relation === $this->fields->firstWhere('name', $field)?->type) {
            return $this->showToast('Indexing relationships is not supported yet.', timeout: 4500);
        }

        if (!\in_array($field, $this->fieldsToBeIndexed)) {
            $this->fieldsToBeIndexed[] = $field;
        } else {
            $this->fieldsToBeIndexed = array_filter($this->fieldsToBeIndexed, fn ($a) => $a != $field);
        }
    }

    public function dropIndex()
    {
        if (empty($this->fieldsToBeIndexed)) {
            $this->showToast('Index not found.');

            return;
        }

        $availableFields = $this->fields->pluck('name');
        foreach ($this->fieldsToBeIndexed as $fieldName) {
            if (!$availableFields->contains($fieldName)) {
                $this->showToast("Invalid field: $fieldName.");

                return;
            }
        }

        $indexManager = new MysqlIndexStrategy();

        $indexManager->dropIndex($this->collection, $this->fieldsToBeIndexed);

        $this->showFieldIndexModal = false;
        $this->isUniqueIndex = false;
        $this->fieldsToBeIndexed = [];
        $this->fields->fresh();
        $this->fillCollectionIndexes();
        $this->showSuccess('Index deleted.');
    }

    public function createIndex()
    {
        if (empty($this->fieldsToBeIndexed)) {
            $this->showToast('An index must contain at least one field.');

            return;
        }

        $availableFields = $this->fields->pluck('name');
        foreach ($this->fieldsToBeIndexed as $fieldName) {
            if (!$availableFields->contains($fieldName)) {
                $this->showToast("Invalid field: $fieldName.");

                return;
            }
        }

        $indexManager = new MysqlIndexStrategy();

        if ($indexManager->hasIndex($this->collection, $this->fieldsToBeIndexed, $this->isUniqueIndex)) {
            $this->showToast('Index already exists.');

            return;
        }

        try {
            $indexManager->createIndex($this->collection, $this->fieldsToBeIndexed, $this->isUniqueIndex);
        } catch (IndexOperationException $e) {
            if (1062 === $e->getCode()) {
                $this->addError('fieldsToBeIndexed', $e->getMessage());

                return;
            }

            $this->addError('fieldsToBeIndexed', $e->getMessage());

            return;
        }

        $this->showFieldIndexModal = false;
        $this->isUniqueIndex = false;
        $this->fieldsToBeIndexed = [];
        $this->fillCollectionIndexes();
        $this->showSuccess('Created new index.');
    }

    /* === END INDEX OPERATION === */

    #[On('toast')]
    public function showToast($message = 'Ok', $timeout = 1500)
    {
        $this->info(
            title: $message,
            position: 'toast-bottom toast-end',
            icon: 'o-information-circle',
            css: 'alert-info',
            timeout: $timeout,
        );
    }

    #[On('success')]
    public function showSuccess($message = 'Success')
    {
        $this->info(
            title: $message,
            position: 'toast-bottom toast-end',
            icon: 'o-information-circle',
            css: 'alert-success',
            timeout: 1500,
        );
    }

    #[On('error')]
    public function showError($title = 'Error', $message = '')
    {
        $this->info(
            title: $title,
            description: $message,
            position: 'toast-bottom toast-end',
            icon: 'o-information-circle',
            css: 'alert-error',
            timeout: 4000,
        );
    }
}
