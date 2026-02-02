<?php

use App\Delivery\Rules\ValidRuleExpression;
use App\Domain\Auth\Enums\OtpType;
use App\Domain\Auth\Models\AuthOtp;
use App\Domain\Collection\Enums\CollectionType;
use App\Domain\Collection\Exceptions\IndexOperationException;
use App\Domain\Collection\Services\IndexStrategies\MysqlIndexStrategy;
use App\Domain\Field\Enums\FieldType;
use App\Domain\Field\Models\CollectionField;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public \App\Domain\Collection\Models\Collection $collection;
    public \Illuminate\Database\Eloquent\Collection $fields;

    public bool $showConfigureCollectionDrawer = false;
    public bool $showConfirmTruncateCollection = false;
    public bool $showConfirmDeleteCollection = false;

    public array $collectionForm = ['fields' => []];

    public Collection $collectionIndexes;

    public array $fieldsToBeIndexed = [];
    public bool $showFieldIndexModal = false;
    public bool $isUniqueIndex = false;

    public string $tabSelected = 'fields-tab';
    public string $fieldOpen = '';
    public string $optionOpen = '';

    public array $mimeTypes = [];
    public array $mimeTypePresets = [];
    public array $availableCollections = [];

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

    public function mount(): void
    {
        $this->fields = $this->collection->fields()->orderBy('order')->get();

        $this->fillCollectionForm();
        $this->fillCollectionIndexes();

        $this->availableCollections = [
            ['id' => '', 'name' => 'Choose collection', 'disabled' => true],
            ...\App\Domain\Collection\Models\Collection::whereNot('id', $this->collection->id)
                ->where('project_id', $this->collection->project_id)
                ->get()
                ->map(fn($c) => ['id' => $c->id, 'name' => $c->name])
                ->toArray(),
        ];

        $this->mimeTypes = config('velo.available_mime_types');
        $this->mimeTypePresets = config('velo.mime_types_presets');
    }

    #[On('show-collection')]
    public function open(): void
    {
        $this->showConfigureCollectionDrawer = true;
    }

    public function updatedCollectionForm($value, $key): void
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

        if ($tokens[0] == 'fields' && $tokens[2] == 'options' && $tokens[3] == 'multiple') {
            $this->collectionForm['fields'][$index]['options']['multiple'] = intval($value);
        }

        $model = new CollectionField($this->collectionForm['fields'][$index]);
        $this->collectionForm['fields'][$index]['options'] = $model->options->toArray();

        $this->ensureFieldOptionsDefaults($this->collectionForm['fields'][$index]);
    }

    #[Computed]
    public function fieldsToArray()
    {
        return $this->collection->fields()->orderBy('order')->get()->map(function ($f) {
            $fieldArray = $f->toArray();
            if ($f->options) {
                $fieldArray['options'] = $f->options->toArray();
            }

            return $fieldArray;
        })->toArray();
    }

    public function fillCollectionForm(): void
    {
        $this->resetValidation();
        $this->collectionForm = $this->collection->toArray();
        $this->collectionForm['fields'] = $this->fieldsToArray();

        if ($this->collection->type === CollectionType::Auth && empty($this->collectionForm['options'])) {
            $this->collectionForm['options'] = \App\Domain\Collection\Models\Collection::getDefaultAuthOptions();
        }

        foreach ($this->collectionForm['fields'] as &$field) {
            $this->ensureFieldOptionsDefaults($field);
        }
    }

    public function updateFieldOrder($ids): void
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

    public function addNewField(): void
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
            'name' => 'newField__' . time(),
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

        $this->fieldOpen = 'collapse_' . $newField['id'];
    }

    public function duplicateField($targetId): void
    {
        $field = array_find($this->collectionForm['fields'], fn($f) => $f['id'] === $targetId);

        if (!$field) {
            $this->error(
                title: 'Cannot duplicate field.',
                description: 'Field not found.',
                position: 'toast-bottom toast-end',
                icon: 'o-information-circle',
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
            'locked' => false,
            'indexed' => false,
            'unique' => false,
        ];

        array_splice($this->collectionForm['fields'], $targetIndex, 0, [$newField]);
        foreach ($this->collectionForm['fields'] as $index => &$field) {
            $field['order'] = $index;
        }

        $this->fieldOpen = 'collapse_' . $newField['id'];
    }

    public function deleteField($targetId): void
    {
        $key = array_find_key($this->collectionForm['fields'], fn($f) => $f['id'] === $targetId);
        $lockedStatus = $this->collection->fields->where('id', $targetId)->first()?->locked;

        if (!$key) {
            $this->info('Field not found.');

            return;
        }

        if ($lockedStatus) {
            $this->info('Field is locked.');

            return;
        }

        // Straight to hell if not exists on original data
        if ($this->collection->fields->contains('id', $targetId)) {
            $this->collectionForm['fields'][$key]['_deleted'] = true;

            return;
        }

        unset($this->collectionForm['fields'][$key]);
        foreach ($this->collectionForm['fields'] as $index => &$field) {
            $field['order'] = $index;
        }

        $this->fieldOpen = '';
    }

    public function restoreField($targetId): void
    {
        $key = array_find_key($this->collectionForm['fields'], fn($f) => $f['id'] === $targetId);

        if (!$key) {
            $this->error(
                title: 'Cannot restore field.',
                description: 'Field not found.',
                position: 'toast-bottom toast-end',
                icon: 'o-information-circle',
                timeout: 2000,
            );

            return;
        }

        unset($this->collectionForm['fields'][$key]['_deleted']);

        $this->fieldOpen = 'collapse_' . $this->collectionForm['fields'][$key]['id'];
    }

    public function applyFilePresets($fieldIdx, string $presetsName): void
    {
        if (!isset($this->mimeTypePresets[$presetsName])) {
            $this->info('Preset does not exist.');

            return;
        }

        if (!isset($this->collectionForm['fields'][$fieldIdx]['options']['allowedMimeTypes']) || !\is_array($this->collectionForm['fields'][$fieldIdx]['options']['allowedMimeTypes'])) {
            $this->info('Presets can only be applied to file-type fields.');

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

    protected function validateCollectionForm(): void
    {
        $rules = [];
        $messages = [];
        $attributes = [];

        $rules['collectionForm.name'] = ['required', 'regex:/^[a-zA-Z_]+$/', 'unique:collections,name,' . $this->collectionForm['id']];

        $expressionRule = new ValidRuleExpression([
            'sys_request',
            ...$this->collection->fields->pluck('name'),
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
            if (isset($field['_deleted'])) {
                continue;
            }

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

        if ($this->collection->type === CollectionType::Auth) {
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
                $index = (int)collect(explode('.', $path))->first(fn($part) => is_numeric($part));
                $field = $this->collectionForm['fields'][$index];
                if ($field) {
                    $this->fieldOpen = 'collapse_' . $field['id'];
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

    public function saveCollection(): void
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

            if ($this->collectionForm['options']['other']['tokens_options']['auth_duration']['invalidate_previous_tokens']) {
                AuthOtp::where('action', OtpType::AUTHENTICATION)->whereNull('used_at')->update(['used_at' => now()]);
                $this->collectionForm['options']['other']['tokens_options']['auth_duration']['invalidate_previous_tokens'] = false;
            }

            if ($this->collectionForm['options']['other']['tokens_options']['password_reset_duration']['invalidate_previous_tokens']) {
                AuthOtp::where('action', OtpType::PASSWORD_RESET)->whereNull('used_at')->update(['used_at' => now()]);
                $this->collectionForm['options']['other']['tokens_options']['password_reset_duration']['invalidate_previous_tokens'] = false;
            }

            if ($this->collectionForm['options']['other']['tokens_options']['email_verification']['invalidate_previous_tokens']) {
                AuthOtp::where('action', OtpType::EMAIL_VERIFICATION)->whereNull('used_at')->update(['used_at' => now()]);
                $this->collectionForm['options']['other']['tokens_options']['email_verification']['invalidate_previous_tokens'] = false;
            }

            if ($this->collectionForm['options']['other']['tokens_options']['email_change_duration']['invalidate_previous_tokens']) {
                AuthOtp::where('action', OtpType::EMAIL_CHANGE)->whereNull('used_at')->update(['used_at' => now()]);
                $this->collectionForm['options']['other']['tokens_options']['email_change_duration']['invalidate_previous_tokens'] = false;
            }

            if ($this->collectionForm['options']['other']['tokens_options']['protected_file_access_duration']['invalidate_previous_tokens']) {
                // AuthOtp::where('action', ...)->whereNull('used_at')->update(['used_at' => now()]);
                $this->collectionForm['options']['other']['tokens_options']['protected_file_access_duration']['invalidate_previous_tokens'] = false;
            }


            $this->collection->update([
                'name' => $this->collectionForm['name'],
                'api_rules' => $this->collectionForm['api_rules'],
                'options' => $this->collectionForm['options'],
            ]);


            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();

            $this->dispatch('fields-updated');
            $this->dispatch('collection-updated');
            $this->fillCollectionForm();

            $this->showConfigureCollectionDrawer = false;
            $this->fieldOpen = '';

            $this->error(
                title: 'Failed to Save!',
                description: "Failed to save collection configuration. $e",
                position: 'toast-bottom toast-end',
                icon: 'o-check-circle',
                timeout: 2000,
            );

            return;
        }

        if ($this->collection->wasChanged('name')) {
            $this->redirectRoute('collections', ['collection' => $this->collectionForm['name']]);
            return;
        }

        $this->dispatch('fields-updated');
        $this->dispatch('collection-updated');
        $this->fillCollectionForm();

        $this->showConfigureCollectionDrawer = false;

        $this->fieldOpen = '';

        $this->success(
            title: 'Success!',
            description: 'Collection configuration updated successfully.',
            position: 'toast-bottom toast-end',
            timeout: 2000,
        );
    }

    public function truncateCollection(): void
    {
        $this->collection->records()->buildQuery()->delete();
        $this->showConfirmTruncateCollection = false;
        $this->info('Collection truncated successfully.');
    }

    public function deleteCollection(): void
    {
        if (\App\Domain\Collection\Models\Collection::where('project_id', $this->collection->project_id)->count() == 1) {
            $this->error('Cannot delete the only collection in the project.');
            return;
        }

        $this->collection->delete();
        $this->showConfirmDeleteCollection = false;
        $this->redirectRoute('home', navigate: true);
    }

    /* === START INDEX OPERATION === */

    public function updatedShowFieldIndexModal(): void
    {
        $this->resetValidation('fieldsToBeIndexed');
    }

    public function fillCollectionIndexes(): void
    {
        $this->collectionIndexes = $this->collection->indexes()->get();
    }

    public function showIndex($indexId): void
    {
        if (!$this->collectionIndexes->firstWhere('id', '=', $indexId)) {
            $this->info('Index not found.');

            return;
        }

        $index = $this->collectionIndexes->firstWhere('id', '=', $indexId);

        $this->isUniqueIndex = str_starts_with('uq_', $index->name);
        $this->fieldsToBeIndexed = $index->field_names;
        $this->showFieldIndexModal = true;
    }

    public function addNewIndex(): void
    {
        $this->isUniqueIndex = false;
        $this->fieldsToBeIndexed = [];
        $this->showFieldIndexModal = true;
    }

    public function indexToggleField($field): void
    {
        if ($this->collection->fields->firstWhere('name', $field)?->type === FieldType::Relation) {
            $this->info('Relation fields are indexed automatically.', timeout: 4500);
            return;
        }

        if (!\in_array($field, $this->fieldsToBeIndexed)) {
            $this->fieldsToBeIndexed[] = $field;
        } else {
            $this->fieldsToBeIndexed = array_filter($this->fieldsToBeIndexed, fn($a) => $a != $field);
        }
    }

    public function dropIndex(): void
    {
        if (empty($this->fieldsToBeIndexed)) {
            $this->info('Index not found.');

            return;
        }

        $availableFields = $this->collection->fields->pluck('name');
        foreach ($this->fieldsToBeIndexed as $fieldName) {
            if (!$availableFields->contains($fieldName)) {
                $this->info("Invalid field: $fieldName.");

                return;
            }
        }

        $indexManager = new MysqlIndexStrategy;

        $indexManager->dropIndex($this->collection, $this->fieldsToBeIndexed);

        $this->showFieldIndexModal = false;
        $this->isUniqueIndex = false;
        $this->fieldsToBeIndexed = [];
        $this->dispatch('collection-updated');
        $this->fillCollectionIndexes();
        $this->success('Index deleted.');
    }

    public function createIndex(): void
    {
        if (empty($this->fieldsToBeIndexed)) {
            $this->info('An index must contain at least one field.');

            return;
        }

        $availableFields = $this->collection->fields->pluck('name');
        foreach ($this->fieldsToBeIndexed as $fieldName) {
            if (!$availableFields->contains($fieldName)) {
                $this->info("Invalid field: $fieldName.");

                return;
            }
        }

        $indexManager = new MysqlIndexStrategy;

        if ($indexManager->hasIndex($this->collection, $this->fieldsToBeIndexed, $this->isUniqueIndex)) {
            $this->info('Index already exists.');

            return;
        }

        try {
            $indexManager->createIndex($this->collection, $this->fieldsToBeIndexed, $this->isUniqueIndex);
        } catch (IndexOperationException $e) {
            if ($e->getCode() === 1062) {
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
        $this->success('Created new index.');
    }

    /* === END INDEX OPERATION === */
};
?>
<div>
    <x-drawer wire:model="showConfigureCollectionDrawer" class="w-full lg:w-2/5" right>
        <div class="flex justify-between">
            <div class="flex items-center gap-2">
                <x-button icon="o-x-mark" class="btn-circle btn-ghost"
                          x-on:click="$wire.showConfigureCollectionDrawer = false"/>
                <p class="text-sm">Configure <span class="font-bold">{{ $collection->name }} collection</span></p>
            </div>
            <x-dropdown top left>
                <x-slot:trigger>
                    <x-button icon="o-bars-3" class="btn-circle btn-ghost"/>
                </x-slot:trigger>

                <x-menu-item title="Truncate" icon="o-archive-box-x-mark"
                             class="text-error"
                             x-on:click="$wire.showConfirmTruncateCollection = true"/>
                <x-menu-item title="Drop" icon="o-trash"
                             class="text-error"
                             x-on:click="$wire.showConfirmDeleteCollection = true"/>
            </x-dropdown>
        </div>

        <x-form wire:submit.prevent="saveCollection">
            <x-input label="Name" wire:model="collectionForm.name" suffix="Type: {{ $collection->type }}"
                     wire:loading.attr="disabled" wire:target="fillCollectionForm" required/>

            <div class="my-2"></div>

            <x-tabs wire:model="tabSelected" active-class="bg-primary rounded !text-white"
                    label-class="font-semibold w-full p-2" label-div-class="bg-primary/5 flex rounded">
                <x-tab name="fields-tab" label="Fields">
                    <x-accordion wire:model="fieldOpen">
                        <div class="space-y-2 px-0.5">
                            <div id="sortable-fields-list" wire:sortable="updateFieldOrder"
                                 wire:sortable.options="{ animation: 150, ghostClass: 'bg-primary/10', dragClass: 'opacity-50'}">
                                @foreach ($collectionForm['fields'] as $index => $field)
                                    <div class="flex items-center gap-2 mb-4 group relative"
                                         wire:key="field-{{ $field['id'] }}"
                                         wire:sortable.item="{{ $field['id'] }}">
                                        @php
                                            $fieldId = $field['id'];
                                            $field = new \App\Domain\Field\Models\CollectionField($field);
                                            $isDeleted = isset($collectionForm['fields'][$index]['_deleted']) && $collectionForm['fields'][$index]['_deleted'];
                                        @endphp

                                        <x-icon name="o-bars-3"
                                                wire:sortable.handle
                                                class="w-4 h-4 drag-handle cursor-move text-gray-400 hover:text-gray-600 opacity-0 group-hover:opacity-100 absolute left-0 -translate-x-6"/>

                                        <x-collapse separator
                                                    class="w-full rounded"
                                                    name="collapse_{{ $fieldId }}"
                                                    wire:loading.class="opacity-50"
                                                    wire:target="duplicateField({{ $index }}), deleteField({{ $index }})">
                                            <x-slot:heading>
                                                <div class="flex flex-col md:flex-row justify-between gap-2 w-full">
                                                    <div class="flex items-center gap-2">
                                                        <x-icon name="{{ $field->getIcon() }}" class="w-4 h-4"/>
                                                        <span class="font-semibold"
                                                              class="{{ $isDeleted ? 'line-through' : '' }}">{{ $field->name }}</span>
                                                    </div>
                                                    <div class="flex md:flex-row-reverse items-center flex-wrap gap-2">
                                                        <x-badge value="{{ $field->type->value }}"
                                                                 class="badge-sm badge-ghost"/>
                                                        @if ($isDeleted)
                                                            <x-badge value="Deleted" class="badge-sm badge-error"/>
                                                        @endif
                                                        @if ($field->required)
                                                            <x-badge value="Nonempty"
                                                                     class="badge-sm badge-info badge-soft"/>
                                                        @endif
                                                        @if ($field->hidden)
                                                            <x-badge value="Hidden"
                                                                     class="badge-sm badge-error badge-soft"/>
                                                        @endif
                                                    </div>
                                                </div>
                                            </x-slot:heading>
                                            <x-slot:content>
                                                @if ($isDeleted)
                                                    <div
                                                        class="flex items-center justify-between p-4 bg-error/10 rounded-lg">
                                                        <div>
                                                            <p class="font-semibold text-error">This field will be
                                                                deleted
                                                                when you save.</p>
                                                        </div>
                                                        <x-button label="Restore" icon="o-arrow-uturn-left"
                                                                  wire:click="restoreField({{ $fieldId }})"
                                                                  class="btn-sm btn-primary"/>
                                                    </div>
                                                @else
                                                    <div class="space-y-3 pt-2">
                                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                            <x-input label="Name"
                                                                     wire:model.blur="collectionForm.fields.{{ $index }}.name"
                                                                     :disabled="$field->locked == true"/>
                                                            <x-select label="Type"
                                                                      wire:model.live="collectionForm.fields.{{ $index }}.type"
                                                                      :options="\App\Domain\Field\Enums\FieldType::toArray()"
                                                                      :icon="$field->getIcon()"
                                                                      :disabled="$field->locked == true"/>
                                                            @switch($field->type)
                                                                @case(\App\Domain\Field\Enums\FieldType::Relation)
                                                                    <div class="col-span-1 md:col-span-2">
                                                                        <x-select label="Reference Collection"
                                                                                  wire:model="collectionForm.fields.{{ $index }}.options.collection"
                                                                                  :options="$availableCollections"
                                                                                  icon="o-share"/>
                                                                    </div>

                                                                    @if ($collectionForm['fields'][$index]['options']['multiple'] == true)
                                                                        <x-input type="number" label="Min Select"
                                                                                 wire:model="collectionForm.fields.{{ $index }}.options.minSelect"
                                                                                 placeholder="No min select" min="0"/>
                                                                        <x-input type="number" label="Min Select"
                                                                                 wire:model="collectionForm.fields.{{ $index }}.options.maxSelect"
                                                                                 placeholder="No max select" min="0"/>
                                                                    @endif
                                                                    @break

                                                                @case(\App\Domain\Field\Enums\FieldType::Text)
                                                                    <x-input label="Min Length" type="number"
                                                                             wire:model="collectionForm.fields.{{ $index }}.options.minLength"
                                                                             placeholder="No minimum" min="0"
                                                                             :disabled="$field->name === 'password' && $field->collection->type === \App\Domain\Collection\Enums\CollectionType::Auth"/>
                                                                    <x-input label="Max Length" type="number"
                                                                             wire:model="collectionForm.fields.{{ $index }}.options.maxLength"
                                                                             placeholder="No maximum" min="1"
                                                                             :disabled="$field->name === 'password' && $field->collection->type === \App\Domain\Collection\Enums\CollectionType::Auth"/>
                                                                    <x-input label="Pattern (Regex)"
                                                                             wire:model="collectionForm.fields.{{ $index }}.options.pattern"
                                                                             placeholder="e.g., /^[A-Z]/"
                                                                             :disabled="$field->name === 'password' && $field->collection->type === \App\Domain\Collection\Enums\CollectionType::Auth"/>
                                                                    <x-input label="Auto Generate Pattern (Regex)"
                                                                             wire:model="collectionForm.fields.{{ $index }}.options.autoGeneratePattern"
                                                                             placeholder="e.g., INV-[0-9]{5}"
                                                                             :disabled="$field->name === 'password' && $field->collection->type === \App\Domain\Collection\Enums\CollectionType::Auth"/>
                                                                    @break

                                                                @case(\App\Domain\Field\Enums\FieldType::Email)
                                                                    <x-tags label="Allowed Domains"
                                                                            wire:model="collectionForm.fields.{{ $index }}.options.allowedDomains"
                                                                            icon="o-globe-asia-australia"
                                                                            hint="Separate each domain with a comma"
                                                                            clearable/>
                                                                    <x-tags label="Blocked Domains"
                                                                            wire:model="collectionForm.fields.{{ $index }}.options.blockedDomains"
                                                                            icon="o-globe-asia-australia"
                                                                            hint="Separate each domain with a comma"
                                                                            clearable/>
                                                                    @break

                                                                @case(\App\Domain\Field\Enums\FieldType::Number)
                                                                    <x-input label="Min" type="number"
                                                                             wire:model="collectionForm.fields.{{ $index }}.options.min"
                                                                             placeholder="No minimum" step="any"/>
                                                                    <x-input label="Max" type="number"
                                                                             wire:model="collectionForm.fields.{{ $index }}.options.max"
                                                                             placeholder="No maximum" step="any"/>
                                                                    <x-toggle label="Allow Decimals"
                                                                              wire:model="collectionForm.fields.{{ $index }}.options.allowDecimals"/>
                                                                    @break

                                                                @case(\App\Domain\Field\Enums\FieldType::Bool)
                                                                    {{-- // --}}
                                                                    @break

                                                                @case(\App\Domain\Field\Enums\FieldType::Datetime)
                                                                    <x-input label="Min Date"
                                                                             wire:model="collectionForm.fields.{{ $index }}.options.minDate"
                                                                             placeholder="2024-01-01 or 'now'"
                                                                             :disabled="in_array($field->name, ['created', 'updated'])"/>
                                                                    <x-input label="Max Date"
                                                                             wire:model="collectionForm.fields.{{ $index }}.options.maxDate"
                                                                             placeholder="2024-12-31 or 'now'"
                                                                             :disabled="in_array($field->name, ['created', 'updated'])"/>
                                                                    @break

                                                                @case(\App\Domain\Field\Enums\FieldType::File)
                                                                    <div class="col-span-1 md:col-span-2">
                                                                        <x-choices-offline label="Allowed Mime Types"
                                                                                           wire:model="collectionForm.fields.{{ $index }}.options.allowedMimeTypes"
                                                                                           :options="$mimeTypes"
                                                                                           placeholder="Search..."
                                                                                           multiple
                                                                                           searchable/>
                                                                        <div class="my-2"></div>
                                                                        <x-dropdown>
                                                                            <x-slot:trigger>
                                                                                <x-button label="Use Presets"
                                                                                          class="btn-sm btn-soft"
                                                                                          icon-right="o-chevron-down"/>
                                                                            </x-slot:trigger>

                                                                            @foreach ($mimeTypePresets as $presets => $mimes)
                                                                                <x-menu-item :title="$presets"
                                                                                             x-on:click="$wire.applyFilePresets({{ $index }}, '{{ $presets }}')"/>
                                                                            @endforeach
                                                                        </x-dropdown>
                                                                    </div>
                                                                    <x-input label="Min Size (bytes)" type="number"
                                                                             wire:model="collectionForm.fields.{{ $index }}.options.minSize"
                                                                             placeholder="No Limit" min="0"/>
                                                                    <x-input label="Max Size (bytes)" type="number"
                                                                             wire:model="collectionForm.fields.{{ $index }}.options.maxSize"
                                                                             placeholder="No Limit" min="0"/>
                                                                    @if ($collectionForm['fields'][$index]['options']['multiple'])
                                                                        <x-input label="Max Files" type="number"
                                                                                 wire:model="collectionForm.fields.{{ $index }}.options.maxFiles"
                                                                                 placeholder="Unlimited" min="1"/>
                                                                    @endif
                                                                    {{-- @TODO: Add thumbnail option for future --}}
                                                                    @break
                                                            @endswitch
                                                        </div>
                                                        <div class="flex items-baseline justify-between gap-6">
                                                            <div class="grid grid-cols-1 md:grid-cols-2 gx-4">
                                                                @if ($field->type == \App\Domain\Field\Enums\FieldType::File)
                                                                    <x-toggle id="toggle-multiple-{{ $index }}"
                                                                              label="Allow Multiple"
                                                                              wire:model.live="collectionForm.fields.{{ $index }}.options.multiple"
                                                                              hint="Allow multiple file uploads"/>
                                                                @endif
                                                                @if ($field->type == \App\Domain\Field\Enums\FieldType::Relation)
                                                                    <x-toggle id="toggle-multiple-{{ $index }}"
                                                                              label="Allow Multiple"
                                                                              wire:model.live="collectionForm.fields.{{ $index }}.options.multiple"
                                                                              hint="Allow multiple relations"/>
                                                                    <x-toggle id="toggle-cascadeDelete-{{ $index }}"
                                                                              label="Cascade Delete"
                                                                              wire:model.live="collectionForm.fields.{{ $index }}.options.cascadeDelete"
                                                                              hint="Delete records if relation is deleted"/>
                                                                @endif
                                                                <x-toggle id="toggle-required-{{ $index }}"
                                                                          label="Nonempty" hint="Value cannot be empty"
                                                                          wire:model="collectionForm.fields.{{ $index }}.required"
                                                                          :disabled="$field->locked == true"/>
                                                                <x-toggle id="toggle-hidden-{{ $index }}"
                                                                          label="Hidden"
                                                                          hint="Hide field from API response"
                                                                          wire:model="collectionForm.fields.{{ $index }}.hidden"
                                                                          :disabled="$field->locked == true"/>
                                                            </div>
                                                            <x-dropdown top left>
                                                                <x-slot:trigger>
                                                                    <x-button icon="o-bars-3"
                                                                              class="btn-circle btn-ghost"/>
                                                                </x-slot:trigger>

                                                                <x-menu-item title="Duplicate"
                                                                             icon="o-document-duplicate"
                                                                             x-on:click="$wire.duplicateField({{ $fieldId }})"/>
                                                                <x-menu-item title="Delete" icon="o-trash"
                                                                             class="text-error"
                                                                             :hidden="$field->locked == true"
                                                                             x-on:click="$wire.deleteField({{ $fieldId }})"/>
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
                              wire:click="addNewField" spinner/>

                    <div class="divider my-2"></div>

                    <p class="text-base-content text-sm mb-1">Unique constraints and indexes
                        ({{ count($collectionIndexes) }})</p>
                    <div class="flex items-center flex-wrap gap-2">
                        @foreach ($collectionIndexes as $index)
                            <x-button
                                label="{{ (str_starts_with($index->index_name, 'uq_') ? 'Unique: ' : '') . implode(', ', $index->field_names) }}"
                                class="btn-soft btn-sm" wire:click="showIndex({{ $index->id }})"
                                spinner="showIndex({{ $index->id }})"/>
                        @endforeach
                        <x-button label="New Index" icon="o-plus" class="btn-sm btn-soft"
                                  wire:click="addNewIndex" spinner="addNewIndex"/>
                    </div>

                </x-tab>
                <x-tab name="api-rules-tab" label="API Rules">
                    <div class="space-y-4 px-0.5">
                        @foreach ($collectionForm['api_rules'] as $apiRule => $value)
                            @continue($apiRule == 'authenticate')
                            <x-textarea wire:model="collectionForm.api_rules.{{ $apiRule }}"
                                        label="{{ ucfirst($apiRule) }} Rule"
                                        placeholder="{{ ucfirst($apiRule) }} Rule. Leave blank to grant everyone access."
                                        inline/>
                        @endforeach

                        @if ($this->collection->type === \App\Domain\Collection\Enums\CollectionType::Auth)
                            <div class="divider my-2"></div>

                            <x-collapse separator>
                                <x-slot:heading>
                                    <p class="text-base-content text-sm">Additional Auth Rules</p>
                                </x-slot:heading>
                                <x-slot:content>
                                    <div class="space-y-4">
                                        <x-textarea wire:model="collectionForm.api_rules.authenticate"
                                                    label="Authentication Rule" placeholder="Authentication Rule"
                                                    hint="This rule is executed every time before authentication allowing you to restrict who can authenticate. For example, to allow only verified users you can set it to verified = true. Leave it empty to allow anyone with an account to authenticate. To disable authentication entirely you can change it to 'Set superusers only'"
                                                    inline/>
                                        <x-textarea wire:model="collectionForm.api_rules.manage" label="Manage Rule"
                                                    placeholder="Manage Rule" inline
                                                    hint="This rule is executed in addition to the create and update API rules. It enables superuser-like permissions to allow fully managing the auth record(s), eg. changing the password without requiring to enter the old one, directly updating the verified state or email, etc."/>
                                    </div>
                                </x-slot:content>
                            </x-collapse>
                        @endif
                    </div>
                </x-tab>

                @if ($this->collection->type === \App\Domain\Collection\Enums\CollectionType::Auth)
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
                                                    <x-toggle id="auth-methods-standard-enabled"
                                                              wire:model="collectionForm.options.auth_methods.standard.enabled"/>
                                                </div>
                                                <div class="ml-2">
                                                    <x-choices-offline
                                                        label="Fields"
                                                        wire:model="collectionForm.options.auth_methods.standard.fields"
                                                        :options="$this->collection->fields->map(fn($f) => ['id' => $f->name, 'name' => $f->name])->toArray()"
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
                                                    <x-toggle id="auth-methods-oauth2-enabled"
                                                              wire:model="collectionForm.options.auth_methods.oauth2.enabled"/>
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
                                                    <x-toggle id="auth-methods-otp-enabled"
                                                              wire:model="collectionForm.options.auth_methods.otp.enabled"/>
                                                </div>

                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <x-input label="Duration (seconds)" type="number"
                                                             wire:model="collectionForm.options.auth_methods.otp.config.duration_s"/>
                                                    <x-input label="Length" type="number"
                                                             wire:model="collectionForm.options.auth_methods.otp.config.generate_password_length"/>
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
                                                'otp_email' => 'OTP Email',
                                                'login_alert' => 'Login Alert'
                                            ] as $key => $label)
                                                <div class="p-4 rounded-lg bg-base-100">
                                                    <div class="font-bold mb-4">{{ $label }}</div>
                                                    <div class="space-y-4">
                                                        <x-input label="Subject"
                                                                 wire:model="collectionForm.options.mail_templates.{{ $key }}.subject"/>
                                                        <x-textarea label="Body (HTML supported)"
                                                                    wire:model="collectionForm.options.mail_templates.{{ $key }}.body"
                                                                    rows="4"/>
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
                                                        <x-input label="Duration (seconds)" type="number"
                                                                 wire:model="collectionForm.options.other.tokens_options.{{ $key }}.value"/>
                                                        <x-toggle
                                                            id="collectionForm-options-other-tokens_options-{{ $key }}-invalidate_previous_tokens"
                                                            label="Invalidate Previous Tokens"
                                                            wire:model="collectionForm.options.other.tokens_options.{{ $key }}.invalidate_previous_tokens"/>
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
                <x-button label="Cancel" x-on:click="$wire.showConfigureCollectionDrawer = false"/>
                <x-button label="Save" class="btn-primary" type="submit"
                          spinner="saveCollection"/>
            </x-slot:actions>
        </x-form>

    </x-drawer>

    <x-modal wire:model="showConfirmTruncateCollection" title="Truncate Collection?">
        <div class="mb-5">
            Are you sure you want to delete ALL records in <strong>{{ $collection->name }}</strong>? This action cannot
            be
            undone.
        </div>
        <x-slot:actions>
            <x-button label="Cancel" x-on:click="$wire.showConfirmTruncateCollection = false"/>
            <x-button label="Truncate" class="btn-error" wire:click="truncateCollection"/>
        </x-slot:actions>
    </x-modal>

    <x-modal wire:model="showConfirmDeleteCollection" title="Delete Collection?">
        <div class="mb-5">
            Are you sure you want to delete the collection <strong>{{ $collection->name }}</strong>? ALL data will be
            lost.
        </div>
        <x-slot:actions>
            <x-button label="Cancel" x-on:click="$wire.showConfirmDeleteCollection = false"/>
            <x-button label="Delete" class="btn-error" wire:click="deleteCollection"/>
        </x-slot:actions>
    </x-modal>

    <x-modal wire:model="showFieldIndexModal" title="Update Index">
        <div class="space-y-2">
            <div class="bg-info/10 border border-info/20 rounded-lg p-4">
                <div class="flex gap-2">
                    <x-icon name="o-information-circle" class="w-5 h-5 text-info shrink-0"/>
                    <div class="text-sm">
                        <p class="font-semibold text-info mb-1">About Indexes</p>
                        <p class="opacity-80">Indexes improve query performance for frequently searched fields. Unique
                            indexes also enforce data uniqueness.</p>
                    </div>
                </div>
            </div>

            <div class="bg-info/10 border border-info/20 rounded-lg p-4">
                <div class="flex gap-2">
                    <x-icon name="o-information-circle" class="w-5 h-5 text-info shrink-0"/>
                    <div class="text-sm">
                        <p class="font-semibold text-info mb-1">Missing Fields?</p>
                        <p class="opacity-80">Save the collection first. All the fields will be available once it is
                            saved.</p>
                    </div>
                </div>
            </div>

            <div class="my-4"></div>

            <x-tags label="Selected Fields" wire:model="fieldsToBeIndexed" disabled/>
            <x-toggle label="Unique" wire:model="isUniqueIndex"/>

            <div class="divider my-2"></div>

            <div class="flex items-center flex-wrap gap-2">
                @foreach ($collection->fields as $field)
                    <x-button label="{{ $field->name }}"
                              @class(['btn-sm', in_array($field->name, $fieldsToBeIndexed) ? 'btn-accent' : 'btn-soft']) wire:click="indexToggleField('{{ $field->name }}')"/>
                @endforeach
            </div>

        </div>

        <x-slot:actions>
            <div class="w-full flex items-center justify-between flex-wrap gap-2">
                <x-button icon="o-trash" tooltip-right="Drop Index"
                          class="btn-ghost btn-circle scale-90 {{ empty($fieldsToBeIndexed) ? 'opacity-0' : '' }}"
                          wire:click="dropIndex" spinner="dropIndex"/>
                <div class="flex items-center flex-wrap gap-2">
                    <x-button label="Cancel" x-on:click="$wire.showFieldIndexModal = false"/>
                    <x-button class="btn-primary" label="Set Index" wire:click="createIndex" spinner="createIndex"
                              :disabled="empty($fieldsToBeIndexed)"/>
                </div>
            </div>
        </x-slot:actions>
    </x-modal>

</div>
