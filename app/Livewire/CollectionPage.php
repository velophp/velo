<?php

namespace App\Livewire;

use DB;
use Mary\Traits\Toast;
use Livewire\Component;
use Illuminate\Support\Str;
use App\Traits\FileLibrarySync;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Support\Facades\Validator;
use App\Enums\{FieldType, CollectionType};
use Livewire\{WithFileUploads, WithPagination};
use App\Services\IndexStrategies\MysqlIndexStrategy;
use Livewire\Attributes\{Computed, Title, On, Rule};
use App\Models\{Collection, CollectionField, Record};
use Illuminate\Support\Collection as LaravelCollection;
use App\Services\{CompareArrays, RecordQueryCompiler, RecordRulesCompiler};

class CollectionPage extends Component
{
    use WithFileUploads, WithPagination, Toast, FileLibrarySync;

    public Collection $collection;
    public $fields;
    public array $breadcrumbs = [];
    public bool $showRecordDrawer = false;
    public bool $showConfirmDeleteDialog = false;
    public bool $showConfigureCollectionDrawer = false;

    // Record Form State
    public array $form = [];

    // Collection Form State
    public $collectionForm = ['fields' => []];
    public string $tabSelected = 'fields-tab';
    public string $fieldOpen = '';

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

    // Helpers
    public array $optionsBool = [['id' => 0, 'name' => 'Single'], ['id' => 1, 'name' => 'Multiple']];
    public array $mimeTypes = [
        ['id' => 'application/pdf', 'name' => 'application/pdf', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/337/337946.png'],
        ['id' => 'application/json', 'name' => 'application/json', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136525.png'],
        ['id' => 'application/xml', 'name' => 'application/xml', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136526.png'],
        ['id' => 'application/zip', 'name' => 'application/zip', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136544.png'],
        ['id' => 'audio/mpeg', 'name' => 'audio/mpeg', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136548.png'],
        ['id' => 'audio/wav', 'name' => 'audio/wav', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136548.png'],
        ['id' => 'image/gif', 'name' => 'image/gif', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136521.png'],
        ['id' => 'image/jpeg', 'name' => 'image/jpeg', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136524.png'],
        ['id' => 'image/png', 'name' => 'image/png', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136523.png'],
        ['id' => 'image/svg+xml', 'name' => 'image/svg+xml', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136530.png'],
        ['id' => 'image/webp', 'name' => 'image/webp', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/8263/8263118.png'],
        ['id' => 'text/css', 'name' => 'text/css', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136527.png'],
        ['id' => 'text/csv', 'name' => 'text/csv', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136533.png'],
        ['id' => 'text/html', 'name' => 'text/html', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136528.png'],
        ['id' => 'text/plain', 'name' => 'text/plain', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136538.png'],
        ['id' => 'video/mp4', 'name' => 'video/mp4', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136545.png'],
        ['id' => 'video/mpeg', 'name' => 'video/mpeg', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136545.png'],
        ['id' => 'video/quicktime', 'name' => 'video/quicktime', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136545.png'],
        ['id' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'name' => '.docx', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/888/888883.png'],
        ['id' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'name' => '.xlsx', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/888/888850.png'],
    ];
    public array $mimeTypePresets = [
        'image' => [
            'image/gif',
            'image/jpeg',
            'image/png',
            'image/svg+xml',
            'image/webp',
        ],
        'audio' => [
            'audio/mpeg',
            'audio/wav',
        ],
        'video' => [
            'video/mp4',
            'video/mpeg',
            'video/quicktime',
        ],
        'documents' => [
            'application/pdf',
            'text/csv',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
        'archive' => [
            'application/zip',
        ],
    ];

    public function mount(Collection $collection): void
    {
        $this->collection = $collection;
        $this->fields = $collection->fields->sortBy('order')->values();
        $this->library = [];

        // Preload values
        $this->fillFieldsVisibility();
        $this->fillRecordForm();
        $this->fillCollectionForm();
        $this->breadcrumbs = [
            ['link' => route('home'), 'icon' => 's-home'],
            ['label' => ucfirst(request()->segment(2))],
            ['label' => $this->collection->name]
        ];
    }

    public function render()
    {
        return view('livewire.collection-page')->title("Collection - {$this->collection->name}");
    }

    /* === START TABLE METHODS === */

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function toggleField(string $field)
    {
        if (!\array_key_exists($field, $this->fieldsVisibility))
            return;

        $this->fieldsVisibility[$field] = !$this->fieldsVisibility[$field];
    }

    #[Computed]
    public function tableHeaders(): array
    {
        return $this->fields
            ->filter(fn($f) => isset($this->fieldsVisibility[$f->name]) && $this->fieldsVisibility[$f->name])
            ->sortBy('order')
            ->map(function ($f) {
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
                    $headers['format'] = fn($row, $field) => json_encode($field);
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

    public function fillFieldsVisibility()
    {
        foreach ($this->fields as $i => $field) {
            $this->fieldsVisibility[$field->name] = true;

            if ($this->collection->type === CollectionType::Auth) {
                if ($field->name === 'password') {
                    $this->fieldsVisibility['password'] = false;
                }
            }
        }
    }

    /* === END TABLE METHODS === */

    /* === START RECORD OPERATION === */

    public function fillRecordForm($data = null)
    {
        if ($data == null) {
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
            return;
        }

        $this->form = $data;
        foreach ($this->fields as $field) {
            if ($field->type === FieldType::File) {
                $this->files[$field->name] = [];

                // Load existing library data from form if it exists
                $existingLibrary = $this->form[$field->name] ?? [];
                if (\is_array($existingLibrary) && !empty($existingLibrary)) {
                    $this->library[$field->name] = collect($existingLibrary);
                } else {
                    $this->library[$field->name] = collect([]);
                }
            }
        }
    }

    public function showRecord(string $id): void
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
        $this->fillRecordForm($data);
        $this->showRecordDrawer = true;
    }

    public function duplicateRecord(string $id): void
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
        $data = [...$data, 'id' => ''];
        $this->fillRecordForm($data);
    }

    public function promptDeleteRecord($id): void
    {
        $this->recordToDelete = array_filter(explode(',', $id));
        $this->showConfirmDeleteDialog = true;
    }

    public function confirmDeleteRecord(): void
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

        $this->showRecordDrawer = false;
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

    public function saveRecord(): void
    {
        $recordId = $this->form['id_old'] ?? null;
        $status = $recordId ? 'Updated' : 'Created';
        $record = null;

        $rulesCompiler = new RecordRulesCompiler($this->collection, new MysqlIndexStrategy, ignoreId: $recordId);

        $rules = $rulesCompiler->getRules(prefix: 'form.');
        $messages = [];
        $attributes = [];

        if ($recordId) {
            $record = $this->collection->queryCompiler()->filter('id', '=', $recordId)->firstRaw();
        }
        
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
        } catch (\Exception $e) {
            // dd($e,  data_get($this, 'form.avatar'));
            throw $e;
        }

        if ($record) {

            // Sync file fields to storage and update form
            foreach ($this->fields as $field) {
                if ($field->type === FieldType::File) {
                    $existingLibrary = \is_array($record->data) && isset($record->data[$field->name])
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

        $this->showRecordDrawer = false;

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

    /* === END RECORD OPERATION === */


    /* === START COLLECTION CONFIGURATION === */

    public function updatedCollectionForm($value, $key)
    {
        $tokens = explode('.', $key);
        $index = $tokens[1] ?? null;

        if ($tokens[0] == 'fields' && $tokens[2] == 'options' && $tokens[3] == 'multiple') {
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
        $this->collectionForm = $this->collection->toArray();
        $this->collectionForm['fields'] = $this->fieldsToArray();

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
        $newField = [
            'collection_id' => $this->collection->id,
            'id' => time(),
            'name' => 'newField__' . time(),
            'type' => FieldType::Text,
            'order' => \count($this->collectionForm['fields']),
            'unique' => false,
            'indexed' => false,
            'required' => false,
            'locked' => false,
            'options' => []
        ];

        $model = new CollectionField($newField);
        $newField['options'] = $model->options->toArray();
        $this->ensureFieldOptionsDefaults($newField);

        $this->collectionForm['fields'][] = $newField;
        $this->fieldOpen = 'collapse_' . $newField['id'];
    }

    public function duplicateField($targetId)
    {
        $field = array_find($this->collectionForm['fields'], fn($f) => $f['id'] === $targetId);

        if (!$field) {
            $this->error(
                title: 'Cannot duplicate field.',
                description: "Field not found.",
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
            'name' => $field['name'] . '__copy',
            'order' => $targetIndex,
        ];


        array_splice($this->collectionForm['fields'], $targetIndex, 0, [$newField]);
        foreach ($this->collectionForm['fields'] as $index => &$field) {
            $field['order'] = $index;
        }

        $this->fieldOpen = 'collapse_' . $newField['id'];
    }

    public function deleteField($targetId)
    {
        $key = array_find_key($this->collectionForm['fields'], fn($f) => $f['id'] === $targetId);
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
        $key = array_find_key($this->collectionForm['fields'], fn($f) => $f['id'] === $targetId);

        if (!$key) {
            $this->error(
                title: 'Cannot restore field.',
                description: "Field not found.",
                position: 'toast-bottom toast-end',
                icon: 'o-information-circle',
                css: 'alert-error',
                timeout: 2000,
            );
            return;
        }

        unset($this->collectionForm['fields'][$key]['_deleted']);

        $this->fieldOpen = 'collapse_' . $this->collectionForm['fields'][$key]['id'];
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
                $field['options'][$key] = boolval($field['options'][$key]);
            }
        }
    }

    public function saveCollection()
    {
        /* === PREPARE VALIDATION RULES === */

        $rules = [];
        $messages = [];
        $attributes = [];

        $rules['collectionForm.name'] = ['required', 'regex:/^[a-zA-Z_]+$/', 'unique:collections,name,' . $this->collectionForm['id']];
        $messages['collectionForm.name.regex'][] = 'Collection name can only contain letters and underscores.';
        $messages['collectionForm.name.unique'][] = 'Collection with the same name already exists.';

        $incomingFields = $this->collectionForm['fields'];

        // Stitch all rules together
        foreach ($incomingFields as $index => $field) {

            $rules["collectionForm.fields.{$index}.name"] = ['required', 'regex:/^[a-zA-Z0-9_]+$/', 'unique:collection_fields,name,' . $field['id']];
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

        /* === PREPARE VALIDATION RULES === */

        $this->validate($rules, $messages, $attributes);
        
        try {

            DB::beginTransaction();

            foreach ($incomingFields as $field) {

                $oldField = CollectionField::find($field['id']);

                // Handle deletion of existing fields
                if ($oldField && isset($field['_deleted']) && $field['_deleted']) {
                    if ($oldField->locked)
                        continue;

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
                        'unique' => $field['unique'] ?? false,
                        'indexed' => $field['indexed'] ?? false,
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
                    'unique' => $field['unique'] ?? false,
                    'indexed' => $field['indexed'] ?? false,
                    'required' => $field['required'] ?? false,
                    'options' => $field['options'] ?? [],
                    'hidden' => $field['hidden'] ?? false,
                ];

                $oldField->update($toUpdateData);
            }

            DB::commit();

        } catch (\Exception $e) {

            DB::rollBack();

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
            description: "Collection configuration updated successfully.",
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 2000,
        );

        // Place this at last - handle collection name change
        if ($this->collectionForm['name'] != $this->collection->name) {
            $this->collection->update([
                'name' => $this->collectionForm['name'],
            ]);

            $this->navigate(route('collection', ['collection' => $this->collection->fresh()]), navigate: true);
            return;
        }

    }

    /* === END COLLECTION CONFIGURATION === */

    #[On('toast')]
    public function showToast($message = 'Ok')
    {
        $this->info(
            title: $message,
            position: 'toast-bottom toast-end',
            icon: 'o-information-circle',
            css: 'alert-info',
            timeout: 1500,
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
    public function showError($message = 'Error')
    {
        $this->info(
            title: $message,
            position: 'toast-bottom toast-end',
            icon: 'o-information-circle',
            css: 'alert-error',
            timeout: 1500,
        );
    }

}