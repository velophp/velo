<?php

use App\Delivery\Models\RealtimeConnection;
use App\Delivery\Models\User;
use App\Domain\Auth\Models\AuthOtp;
use App\Domain\Auth\Models\AuthSession;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;
    use WithPagination;

    // Core
    public string $collection = '';
    public string $collectionName = '';
    public array $breadcrumbs = [];

    // Config
    public string $modelClass = '';
    public bool $canCreate = false;
    public bool $canEdit = false;
    public bool $canDelete = true;
    public array $headersConfig = [];
    public array $formConfig = [];

    // Table State
    public int $perPage = 15;
    public string $filter = '';
    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];
    public array $selected = [];
    public array $fieldsVisibility = [];

    // Filters
    public array $enumFilters = []; // ['column' => 'value']
    public array $enumColumns = []; // ['column' => EnumClass]

    // Form allow-list
    public bool $showRecordDrawer = false;
    public ?int $editingId = null;

    // Form Data
    public array $data = [];

    // Delete
    public bool $showConfirmDeleteDialog = false;
    public array $recordToDelete = [];

    public function mount(?string $collection = null): void
    {
        $this->collection = $collection ?? collect(request()->segments())->last();

        $configs = $this->getConfigs($this->collection);
        if (!$configs) {
            abort(404);
        }

        $this->modelClass = $configs['model'];
        $this->collectionName = $configs['name'];
        $this->canCreate = $configs['can_create'] ?? false;
        $this->canEdit = $configs['can_edit'] ?? false;
        $this->canDelete = $configs['can_delete'] ?? true;

        $this->headersConfig = $configs['headers'];
        $this->formConfig = $configs['form_fields'] ?? [];

        foreach ($this->headersConfig as $key => $conf) {
            $this->fieldsVisibility[$key] = $conf['visible'] ?? true;
        }

        $this->detectEnumColumns();

        $this->breadcrumbs = [['link' => route('home'), 'icon' => 's-home'], ['label' => 'System'], ['label' => $this->collectionName]];
    }

    public function getConfigs(string $collection): ?array
    {
        return match ($collection) {
            'superusers' => [
                'model' => User::class,
                'name' => 'Superusers',
                'can_create' => true,
                'can_edit' => true,
                'headers' => [
                    'id' => ['label' => 'ID', 'visible' => true],
                    'name' => ['label' => 'Name', 'visible' => true],
                    'email' => ['label' => 'Email', 'visible' => true],
                    'password' => ['label' => 'Password', 'visible' => true],
                    'created_at' => ['label' => 'Created', 'visible' => true],
                    'updated_at' => ['label' => 'Updated', 'visible' => true],
                ],
                'form_fields' => [
                    'name' => ['label' => 'Name', 'type' => 'text', 'required' => true, 'icon' => 'o-user'],
                    'email' => ['label' => 'Email', 'type' => 'email', 'required' => true, 'icon' => 'o-envelope'],
                    'password' => ['label' => 'Password', 'type' => 'password', 'required' => true, 'icon' => 'o-key', 'create_only' => true],
                    'password_new' => ['label' => 'New Password', 'type' => 'password', 'required' => false, 'icon' => 'o-key', 'update_only' => true],
                ],
            ],
            'sessions' => [
                'model' => AuthSession::class,
                'name' => 'Auth Sessions',
                'can_create' => false,
                'can_edit' => true,
                'headers' => [
                    'id' => ['label' => 'ID', 'visible' => true],
                    'project.name' => ['label' => 'Project', 'visible' => false],
                    'collection.name' => ['label' => 'Collection', 'visible' => true],
                    'record_id' => ['label' => 'Record', 'visible' => true],
                    'token_hash' => ['label' => 'Token', 'visible' => true],
                    'device_name' => ['label' => 'Device', 'visible' => true],
                    'ip_address' => ['label' => 'IP', 'visible' => true],
                    'last_used_at' => ['label' => 'Last Used', 'visible' => true],
                    'created_at' => ['label' => 'Created', 'visible' => true],
                    'updated_at' => ['label' => 'Updated', 'visible' => true],
                ],
                'form_fields' => [
                    'record_id' => ['label' => 'Record ID', 'type' => 'text', 'required' => true],
                    'device_name' => ['label' => 'Device Name', 'type' => 'text', 'required' => false],
                    'ip_address' => ['label' => 'IP Address', 'type' => 'text', 'required' => false],
                    'expires_at' => ['label' => 'Expires At', 'type' => 'datetime-local', 'required' => false],
                ],
            ],
            'otps' => [
                'model' => AuthOtp::class,
                'name' => 'OTPs',
                'can_create' => false,
                'can_edit' => true,
                'headers' => [
                    'id' => ['label' => 'ID', 'visible' => true],
                    'project.name' => ['label' => 'Project', 'visible' => false],
                    'collection.name' => ['label' => 'Collection', 'visible' => true],
                    'record_id' => ['label' => 'Record', 'visible' => true],
                    'action' => ['label' => 'Action', 'visible' => true],
                    'token_hash' => ['label' => 'Token', 'visible' => true],
                    'expires_at' => ['label' => 'Expires', 'visible' => true],
                    'used_at' => ['label' => 'Used', 'visible' => true],
                    'created_at' => ['label' => 'Created', 'visible' => true],
                    'updated_at' => ['label' => 'Updated', 'visible' => true],
                ],
                'form_fields' => [
                    'action' => ['label' => 'Action', 'type' => 'select', 'options' => \App\Domain\Auth\Enums\OtpType::cases(), 'required' => true],
                    'token_hash' => ['label' => 'Token (Hashed)', 'type' => 'text', 'required' => false, 'readonly' => true],
                    'expires_at' => ['label' => 'Expires At', 'type' => 'datetime-local', 'required' => false],
                ],
            ],
            'realtime' => [
                'model' => RealtimeConnection::class,
                'name' => 'Realtime Connections',
                'can_create' => false,
                'can_edit' => true,
                'headers' => [
                    'id' => ['label' => 'ID', 'visible' => true],
                    'project.name' => ['label' => 'Project', 'visible' => false],
                    'collection.name' => ['label' => 'Collection', 'visible' => true],
                    'record_id' => ['label' => 'Record', 'visible' => true],
                    'socket_id' => ['label' => 'Socket ID', 'visible' => true],
                    'channel_name' => ['label' => 'Channel Name', 'visible' => true],
                    'filter' => ['label' => 'Filter', 'visible' => true],
                    'is_public' => ['label' => 'Public', 'visible' => true],
                    'last_seen_at' => ['label' => 'Last Seen', 'visible' => true],
                    'created_at' => ['label' => 'Created', 'visible' => true],
                ],
                'form_fields' => [
                    'socket_id' => ['label' => 'Socket ID', 'type' => 'text', 'required' => false, 'readonly' => true],
                    'channel_name' => ['label' => 'Channel Name', 'type' => 'text', 'required' => false, 'readonly' => true],
                    'filter' => ['label' => 'Filter', 'type' => 'text', 'required' => false, 'readonly' => true],
                    'is_public' => ['label' => 'Public', 'type' => 'checkbox', 'required' => false, 'readonly' => true],
                    'last_seen_at' => ['label' => 'Last Seen', 'type' => 'datetime-local', 'required' => false, 'readonly' => true],
                ],
            ],
            default => null,
        };
    }

    public function detectEnumColumns(): void
    {
        $model = new $this->modelClass();
        $casts = $model->getCasts();
        foreach ($casts as $key => $type) {
            if (enum_exists($type)) {
                $this->enumColumns[$key] = $type;
                if (!isset($this->enumFilters[$key])) {
                    $this->enumFilters[$key] = '';
                }
            }
        }
    }

    public function rules(): array
    {
        $rules = [];

        foreach ($this->formConfig as $field => $config) {
            // Skip fields that are not relevant for the current mode (create vs update)
            if ($this->editingId && ($config['create_only'] ?? false)) {
                continue;
            }
            if (!$this->editingId && ($config['update_only'] ?? false)) {
                continue;
            }

            $fieldRules = [];
            if ($config['required'] ?? false) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            if ($this->collection === 'superusers' && $field === 'email') {
                $fieldRules[] = 'email';
                $fieldRules[] = 'max:255';
                $fieldRules[] = Rule::unique('users', 'email')->ignore($this->editingId);
            } elseif ($this->collection === 'superusers' && ($field === 'password' || $field === 'password_new')) {
                if (!$this->editingId && $field === 'password') {
                    $fieldRules[] = Password::min(8);
                } elseif ($this->editingId && $field === 'password_new' && !empty($this->data['password_new'])) {
                    $fieldRules[] = Password::min(8);
                }
            } else {
                if (($config['type'] ?? '') === 'email') {
                    $fieldRules[] = 'email';
                }
                if (($config['type'] ?? '') === 'number') {
                    $fieldRules[] = 'numeric';
                }
            }

            $rules['data.' . $field] = $fieldRules;
        }

        return $rules;
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function updatedEnumFilters(): void
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
        $headers = [];
        foreach ($this->fieldsVisibility as $field => $visible) {
            if ($visible) {
                $headers[] = [
                    'key' => $field,
                    'label' => $this->headersConfig[$field]['label'] ?? $field,
                    'format' => null,
                ];
            }
        }
        return $headers;
    }

    #[Computed]
    public function tableRows()
    {
        $query = $this->modelClass::query();

        if (!empty($this->filter)) {
            $query->where(function ($q) {
                $cols = Schema::getColumnListing(new $this->modelClass()->getTable());
                $searchables = ['id', 'name', 'email', 'record_id', 'token_hash', 'device_name', 'ip_address'];

                $first = true;
                foreach ($searchables as $col) {
                    if (in_array($col, $cols)) {
                        if ($first) {
                            $q->where($col, 'like', "%{$this->filter}%");
                            $first = false;
                        } else {
                            $q->orWhere($col, 'like', "%{$this->filter}%");
                        }
                    }
                }
            });
        }

        // Apply Enum Filters
        foreach ($this->enumFilters as $col => $val) {
            if (!empty($val)) {
                $query->where($col, $val);
            }
        }

        if (!empty($this->sortBy['column'])) {
            $query->orderBy($this->sortBy['column'], $this->sortBy['direction']);
        }

        return $query->paginate($this->perPage);
    }

    /* === RECORD OPERATIONS === */

    public function resetForm(): void
    {
        $this->resetValidation();
        $this->data = [];

        // Set default values if needed
        foreach ($this->formConfig as $field => $config) {
            $this->data[$field] = null;
        }

        $this->editingId = null;
    }

    public function showRecord(int $id): void
    {
        if (!$this->canEdit && !$this->canCreate) {
            return;
        }

        $record = $this->modelClass::find($id);

        if (!$record) {
            $this->showError('Record not found.');
            return;
        }

        $this->editingId = $record->id;
        $this->resetForm();
        $this->editingId = $id; // resetForm clears it

        // Populate data
        foreach ($this->formConfig as $field => $config) {
            // Handle special cases
            if ($this->collection === 'superusers' && $field === 'password') {
                $this->data[$field] = '********';
            } elseif ($this->collection === 'superusers' && $field === 'password_new') {
                $this->data[$field] = '';
            } else {
                $val = $record->{$field};
                if ($val instanceof \UnitEnum) {
                    $val = $val->value;
                } elseif ($val instanceof \Carbon\Carbon) {
                    $val = $val->format('Y-m-d\TH:i');
                }
                $this->data[$field] = $val;
            }
        }

        $this->showRecordDrawer = true;
    }

    public function duplicateRecord(int $id): void
    {
        if (!$this->canCreate) {
            return;
        }

        $record = $this->modelClass::find($id);

        if (!$record) {
            $this->showError('Record not found.');
            return;
        }

        $this->resetForm();

        foreach ($this->formConfig as $field => $config) {
            if ($config['create_only'] ?? false) {
                continue;
            } // Don't copy passwords etc?
            if ($field === 'password') {
                continue;
            }
            if ($field === 'password_new') {
                continue;
            }

            $val = $record->{$field};
            if ($val instanceof \UnitEnum) {
                $val = $val->value;
            } elseif ($val instanceof \Carbon\Carbon) {
                $val = $val->format('Y-m-d\TH:i');
            }
            $this->data[$field] = $val;
        }

        $this->showRecordDrawer = true;
    }

    public function promptDeleteRecord(string $ids): void
    {
        if (!$this->canDelete) {
            return;
        }
        $this->recordToDelete = array_filter(explode(',', $ids));
        $this->showConfirmDeleteDialog = true;
    }

    public function confirmDeleteRecord(): void
    {
        if (!$this->canDelete) {
            return;
        }

        if ($this->collection === 'superusers' && in_array(Auth::user()->id, $this->recordToDelete)) {
            $this->showError('Cannot delete logged in superuser.');
            return;
        }

        $count = count($this->recordToDelete);
        $this->modelClass::destroy($this->recordToDelete);

        $this->showRecordDrawer = false;
        $this->showConfirmDeleteDialog = false;
        $this->recordToDelete = [];
        $this->selected = [];
        unset($this->tableRows);

        $this->showSuccess("Deleted $count " . Str::plural('record', $count) . '.');
    }

    public function saveRecord(): void
    {
        if (!$this->canEdit && $this->editingId) {
            return;
        }
        if (!$this->canCreate && !$this->editingId) {
            return;
        }

        $this->validate();

        try {
            if ($this->collection === 'superusers') {
                if ($this->editingId) {
                    $user = User::findOrFail($this->editingId);
                    $user->name = $this->data['name'];
                    $user->email = $this->data['email'];
                    if (!empty($this->data['password_new'])) {
                        $user->password = $this->data['password_new']; // Mutator handles hashing? Usually, yes.
                    }
                    $user->save();
                    $status = 'Updated';
                } else {
                    User::create([
                        'project_id' => 1,
                        'name' => $this->data['name'],
                        'email' => $this->data['email'],
                        'password' => $this->data['password'],
                    ]);
                    $status = 'Created';
                }
            } else {
                // Generic Save
                if ($this->editingId) {
                    $record = $this->modelClass::findOrFail($this->editingId);
                    foreach ($this->data as $key => $value) {
                        if (array_key_exists($key, $this->formConfig)) {
                            // Skip special fields or handle them
                            if ($this->formConfig[$key]['readonly'] ?? false) {
                                continue;
                            }
                            if ($this->formConfig[$key]['update_only'] ?? false) {
                                continue;
                            } // Actually update_only means it shows on update...

                            $record->{$key} = $value;
                        }
                    }
                    $record->save();
                    $status = 'Updated';
                } else {
                    $createData = [];
                    foreach ($this->data as $key => $value) {
                        if (array_key_exists($key, $this->formConfig)) {
                            if ($this->formConfig[$key]['readonly'] ?? false) {
                                continue;
                            }
                            $createData[$key] = $value;
                        }
                    }
                    $this->modelClass::create($createData);
                    $status = 'Created';
                }
            }
        } catch (\Exception $e) {
            $this->showError($e->getMessage());
            return;
        }

        $this->showRecordDrawer = false;
        $this->resetForm();
        unset($this->tableRows);
        $this->showSuccess("$status record successfully.");
    }

    // Toasts
    #[On('toast')]
    public function showToast($message = 'Ok', $timeout = 1500): void
    {
        $this->info(title: $message, position: 'toast-bottom toast-end', timeout: $timeout);
    }

    #[On('success')]
    public function showSuccess($message = 'Success'): void
    {
        $this->info(title: $message, position: 'toast-bottom toast-end', css: 'alert-success', timeout: 1500);
    }

    #[On('error')]
    public function showError($title = 'Error', $message = ''): void
    {
        $this->info(title: $title, description: $message, position: 'toast-bottom toast-end', css: 'alert-error', timeout: 4000);
    }
}; ?>

<div>
    <div class="flex justify-between flex-wrap">
        <div class="flex items-center gap-4">
            <x-breadcrumbs :items="$breadcrumbs"/>
            <div class="flex items-center gap-2">
                <x-button icon="o-arrow-path" tooltip-bottom="Refresh" class="btn-circle btn-ghost"
                          wire:click="$refresh"/>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if ($canCreate)
                <x-button label="New Record" class="btn-primary" icon="o-plus"
                          x-on:click="$wire.showRecordDrawer = true; $wire.resetForm()"/>
            @endif
        </div>
    </div>

    <div class="my-8"></div>

    <div class="flex gap-4 mb-4">
        <div class="flex-1">
            <x-input wire:model.live.debounce.250ms="filter" placeholder="Search..." icon="o-magnifying-glass"
                     clearable/>
        </div>

        @foreach ($enumColumns as $col => $enumClass)
            <div class="w-48">
                <x-select wire:model.live="enumFilters.{{ $col }}" :options="$enumClass::cases()"
                          placeholder="Filter {{ Str::title(str_replace('_', ' ', $col)) }}" option-label="name"
                          option-value="value"/>
            </div>
        @endforeach
    </div>

    <div class="flex justify-end">
        <x-dropdown>
            <x-slot:trigger>
                <x-button icon="o-table-cells" class="btn-sm"/>
            </x-slot:trigger>

            <x-menu-item title="Toggle Fields" disabled/>

            @foreach ($this->headersConfig as $field => $config)
                <x-menu-item :wire:key="$field" x-on:click.stop="$wire.toggleField('{{ $field }}')">
                    <x-toggle :label="$config['label']" :checked="$fieldsVisibility[$field] ?? false"/>
                </x-menu-item>
            @endforeach
        </x-dropdown>
    </div>

    <div class="my-4"></div>

    <x-table :headers="$this->tableHeaders" :rows="$this->tableRows" wire:model.live.debounce.250ms="selected"
             selectable striped
             with-pagination per-page="perPage" :per-page-values="[10, 15, 25, 50, 100, 250, 500]" :sort-by="$sortBy">
        <x-slot:empty>
            <div class="flex flex-col items-center my-4">
                <p class="text-gray-500 text-center mb-4">No results found.</p>
                @if ($canCreate)
                    <x-button label="New Record" class="btn-primary btn-soft btn-sm" icon="o-plus"
                              x-on:click="$wire.showRecordDrawer = true; $wire.resetForm()"/>
                @endif
            </div>
        </x-slot:empty>

        {{-- Custom Cells --}}

        @scope('cell_id', $row)
        <div class="badge badge-soft badge-sm flex items-center gap-2 py-3.5">
            <p>{{ Str::limit($row->id, 16) }}</p>
            <x-copy-button :text="$row->id"/>
        </div>
        @endscope

        @scope('cell_created_at', $row)
        @if ($row->created_at)
            <div class="flex flex-col w-20">
                <p>{{ $row->created_at->format('Y-m-d') }}</p>
                <p class="text-xs opacity-80">{{ $row->created_at->format('H:i:s') }}</p>
            </div>
        @else
            <p>-</p>
        @endif
        @endscope

        @scope('cell_updated_at', $row)
        @if ($row->updated_at)
            <div class="flex flex-col w-20">
                <p>{{ $row->updated_at->format('Y-m-d') }}</p>
                <p class="text-xs opacity-80">{{ $row->updated_at->format('H:i:s') }}</p>
            </div>
        @else
            <p>-</p>
        @endif
        @endscope

        @scope('cell_record_id', $row)
        @if (isset($row->record))
            <div class="badge badge-soft badge-sm flex items-center gap-2 py-3.5">
                <p>{{ $row->record->data['name'] ?? ($row->record->data['email'] ?? ($row->record->data['id'] ?? '-')) }}
                </p>
                <x-button class="btn-xs btn-ghost btn-circle"
                          link="{{ route('collections', ['collection' => $row->collection->name, 'recordId' => $row->record?->data['id']]) }}"
                          external>
                    <x-icon name="lucide.external-link" class="w-5 h-5"/>
                </x-button>
            </div>
        @else
            <p>-</p>
        @endif
        @endscope

        @scope('cell_token_hash', $row)
        <span class="text-gray-400">********</span>
        @endscope

        @scope('cell_password', $row)
        <span class="text-gray-400">********</span>
        @endscope

        @scope('cell_last_used_at', $row)
        @if ($row->last_used_at)
            <div class="flex flex-col w-20">
                <p>{{ $row->last_used_at->format('Y-m-d') }}</p>
                <p class="text-xs opacity-80">{{ $row->last_used_at->format('H:i:s') }}</p>
            </div>
        @else
            <p>-</p>
        @endif
        @endscope

        @scope('cell_expires_at', $row)
        @if ($row->expires_at)
            <div class="flex flex-col w-20">
                <p>{{ $row->expires_at->format('Y-m-d') }}</p>
                <p class="text-xs opacity-80">{{ $row->expires_at->format('H:i:s') }}</p>
            </div>
        @else
            <p>-</p>
        @endif
        @endscope

        @scope('cell_used_at', $row)
        @if ($row->used_at)
            <div class="flex flex-col w-20">
                <p>{{ $row->used_at->format('Y-m-d') }}</p>
                <p class="text-xs opacity-80">{{ $row->used_at->format('H:i:s') }}</p>
            </div>
        @else
            <p>-</p>
        @endif
        @endscope

        @if ($canEdit)
            @scope('actions', $row)
            <x-button icon="o-arrow-right" x-on:click="$wire.showRecord('{{ $row->id }}')"
                      spinner="showRecord('{{ $row->id }}')" class="btn-sm"/>
            @endscope
        @endif
    </x-table>

    <div class="fixed bottom-0 left-0 right-0" x-show="$wire.selected.length > 0" x-transition x-cloak>
        <div class="flex justify-center m-8">
            <x-card>
                <div class="flex flex-row items-center gap-4">
                    <p>Selected <span class="font-bold">{{ count($this->selected) }}</span>
                        {{ Str::plural('record', count($this->selected)) }}</p>
                    <x-button label="Reset" x-on:click="$wire.selected = []" class="btn-soft"/>
                    @if ($canDelete)
                        <x-button label="Delete Selected"
                                  wire:click="promptDeleteRecord('{{ implode(',', $selected) }}')"
                                  class="btn-error btn-soft"/>
                    @endif
                </div>
            </x-card>
        </div>
    </div>

    {{-- MODALS --}}

    <x-drawer wire:model="showRecordDrawer" class="w-full lg:w-2/5" right without-trap-focus>
        <div class="flex justify-between">
            <div class="flex items-center gap-2">
                <x-button icon="o-x-mark" class="btn-circle btn-ghost" x-on:click="$wire.showRecordDrawer = false"/>
                <p class="text-sm">{{ $editingId ? 'Update' : 'New' }} <span
                        class="font-bold">{{ $collectionName }}</span>
                    record</p>
            </div>
            <x-dropdown right>
                <x-slot:trigger>
                    <x-button icon="o-bars-2" class="btn-circle btn-ghost" :hidden="!$editingId"/>
                </x-slot:trigger>

                <x-menu-item title="Copy raw JSON" icon="o-document-text" x-data="{
                    copyJson() {
                        const data = $wire.data;
                        const json = JSON.stringify(data, null, 2);
                        window.copyText(json);
                        $wire.dispatchSelf('toast', { message: 'Copied raw JSON to your clipboard.' });
                    }
                }"
                             x-on:click="copyJson"/>

                @if ($canCreate)
                    <x-menu-item title="Duplicate" icon="o-document-duplicate"
                                 x-on:click="$wire.duplicateRecord($wire.editingId)"/>
                @endif

                <x-menu-separator/>

                @if ($canDelete)
                    <x-menu-item title="Delete" icon="o-trash" class="text-error"
                                 x-on:click="$wire.promptDeleteRecord($wire.editingId)"/>
                @endif
            </x-dropdown>
        </div>

        <div class="my-4"></div>

        <x-form wire:submit="saveRecord">
            @foreach ($formConfig as $field => $config)
                @if ($editingId && ($config['create_only'] ?? false))
                    @continue
                @endif
                @if (!$editingId && ($config['update_only'] ?? false))
                    @continue
                @endif

                @if (
                    ($config['type'] ?? 'text') === 'text' ||
                        ($config['type'] ?? 'text') === 'email' ||
                        ($config['type'] ?? 'text') === 'number')
                    <x-input label="{{ $config['label'] }}" wire:model="data.{{ $field }}"
                             type="{{ $config['type'] ?? 'text' }}" icon="{{ $config['icon'] ?? '' }}"
                             :readonly="$config['readonly'] ?? false"/>
                @elseif(($config['type'] ?? '') === 'password')
                    <x-password label="{{ $config['label'] }}" wire:model="data.{{ $field }}"
                                password-icon="{{ $config['icon'] ?? 'o-key' }}"/>
                @elseif(($config['type'] ?? '') === 'datetime-local')
                    <x-datetime label="{{ $config['label'] }}" wire:model="data.{{ $field }}"
                                type="datetime-local"/>
                @elseif(($config['type'] ?? '') === 'select')
                    <x-select label="{{ $config['label'] }}" wire:model="data.{{ $field }}"
                              :options="$config['options']" option-label="name" option-value="value"/>
                @endif
            @endforeach

            <x-slot:actions>
                <x-button label="Cancel" x-on:click="$wire.showRecordDrawer = false"/>
                <x-button label="Save" class="btn-primary" type="submit" spinner="saveRecord"/>
            </x-slot:actions>
        </x-form>
    </x-drawer>

    <x-modal wire:model="showConfirmDeleteDialog" title="Confirm Delete">
        Are you sure you want to delete {{ count($recordToDelete) > 1 ? count($recordToDelete) : 'this' }}
        {{ Str::plural('record', count($recordToDelete)) }}? This action cannot be undone.

        <x-slot:actions>
            <x-button label="Cancel" x-on:click="$wire.showConfirmDeleteDialog = false"/>
            <x-button class="btn-error" label="Delete" wire:click="confirmDeleteRecord"
                      spinner="confirmDeleteRecord"/>
        </x-slot:actions>
    </x-modal>

</div>
