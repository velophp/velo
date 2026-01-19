<?php

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;
    use WithPagination;

    // Core
    public array $breadcrumbs = [];

    // Table State
    public int $perPage = 15;
    public string $filter = '';
    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];
    public array $selected = [];
    public array $fieldsVisibility = [
        'id' => true,
        'name' => true,
        'email' => true,
        'password' => false,
        'created_at' => true,
        'updated_at' => true,
    ];

    // Record Form State
    public bool $showRecordDrawer = false;
    public ?int $editingUserId = null;

    #[Validate]
    public string $name = '';

    #[Validate]
    public string $email = '';

    #[Validate]
    public string $password = '';

    #[Validate]
    public string $password_new = '';

    // UI/UX State
    public bool $showConfirmDeleteDialog = false;
    public array $recordToDelete = [];

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->editingUserId),
            ],
            'password' => $this->editingUserId
                ? ['nullable']
                : ['required', Password::min(8)],
            'password_new' => ['nullable', Password::min(8)],
        ];
    }

    public function mount(): void
    {
        $this->breadcrumbs = [
            ['link' => route('home'), 'icon' => 's-home'],
            ['label' => 'System'],
            ['label' => 'superusers'],
        ];
    }

    public function updatedFilter(): void
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

        $fieldLabels = [
            'id' => 'ID',
            'name' => 'Name',
            'email' => 'Email',
            'password' => 'Password',
            'created_at' => 'Created',
            'updated_at' => 'Updated',
        ];

        foreach ($this->fieldsVisibility as $field => $visible) {
            if ($visible) {
                $headers[] = [
                    'key' => $field,
                    'label' => $fieldLabels[$field] ?? $field,
                    'format' => null,
                ];
            }
        }

        return $headers;
    }

    #[Computed]
    public function tableRows()
    {
        $query = User::query();

        if (!empty($this->filter)) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->filter}%")
                    ->orWhere('email', 'like', "%{$this->filter}%")
                    ->orWhere('id', 'like', "%{$this->filter}%");
            });
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
        $this->reset(['name', 'email', 'password', 'password_new']);
        $this->editingUserId = null;
    }

    public function showRecord(int $id): void
    {
        $user = User::find($id);

        if (!$user) {
            $this->showError('Record not found.');
            return;
        }

        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '********';
        $this->password_new = '';

        $this->showRecordDrawer = true;
    }

    public function duplicateRecord(int $id): void
    {

        $user = User::find($id);

        if (!$user) {
            $this->showError('Record not found.');
            return;
        }

        $this->resetForm();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->showRecordDrawer = true;
    }

    public function promptDeleteRecord(string $ids): void
    {
        $this->recordToDelete = array_filter(explode(',', $ids));
        $this->showConfirmDeleteDialog = true;
    }

    public function confirmDeleteRecord(): void
    {
        if (in_array(Auth::user()->id, $this->recordToDelete)) {
            $this->showError('Cannot delete logged in superuser.');
            return;
        }

        $count = count($this->recordToDelete);
        User::destroy($this->recordToDelete);

        $this->showRecordDrawer = false;
        $this->showConfirmDeleteDialog = false;
        $this->recordToDelete = [];
        $this->selected = [];
        unset($this->tableRows);

        $this->showSuccess("Deleted $count " . str('user')->plural($count) . ".");
    }

    public function saveRecord(): void
    {
        $this->validate();

        if ($this->editingUserId) {
            $user = User::findOrFail($this->editingUserId);

            $user->name = $this->name;
            $user->email = $this->email;

            if (!empty($this->password_new)) {
                $user->password = $this->password_new;
            }

            $user->save();
            $status = 'Updated';
        } else {
            User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
            ]);
            $status = 'Created';
        }

        $this->showRecordDrawer = false;
        $this->resetForm();
        unset($this->tableRows);
        $this->showSuccess("$status user successfully.");
    }

    /* === END RECORD OPERATIONS === */

    #[On('toast')]
    public function showToast($message = 'Ok', $timeout = 1500): void
    {
        $this->info(
            title: $message,
            position: 'toast-bottom toast-end',
            timeout: $timeout,
        );
    }

    #[On('success')]
    public function showSuccess($message = 'Success'): void
    {
        $this->info(
            title: $message,
            position: 'toast-bottom toast-end',
            css: 'alert-success',
            timeout: 1500,
        );
    }

    #[On('error')]
    public function showError($title = 'Error', $message = ''): void
    {
        $this->info(
            title: $title,
            description: $message,
            position: 'toast-bottom toast-end',
            css: 'alert-error',
            timeout: 4000,
        );
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
            <x-button label="New Record" class="btn-primary" icon="o-plus"
                      x-on:click="$wire.showRecordDrawer = true; $wire.resetForm()"/>
        </div>
    </div>

    <div class="my-8"></div>

    <x-input wire:model.live.debounce.250ms="filter" placeholder="Search by name, email, or ID..."
             icon="o-magnifying-glass" clearable/>

    <div class="my-4"></div>

    <div class="flex justify-end">
        <x-dropdown>
            <x-slot:trigger>
                <x-button icon="o-table-cells" class="btn-sm"/>
            </x-slot:trigger>

            <x-menu-item title="Toggle Fields" disabled/>

            @foreach (['id' => 'ID', 'name' => 'Name', 'email' => 'Email', 'password' => 'Password', 'created_at' => 'Created', 'updated_at' => 'Updated'] as $field => $label)
                <x-menu-item :wire:key="$field" x-on:click.stop="$wire.toggleField('{{ $field }}')">
                    <x-toggle :label="$label"
                              :checked="$fieldsVisibility[$field] ?? false"/>
                </x-menu-item>
            @endforeach
        </x-dropdown>
    </div>

    <div class="my-4"></div>

    <x-table :headers="$this->tableHeaders" :rows="$this->tableRows"
             wire:model.live.debounce.250ms="selected"
             selectable striped with-pagination per-page="perPage" :per-page-values="[10, 15, 25, 50, 100, 250, 500]"
             :sort-by="$sortBy">
        <x-slot:empty>
            <div class="flex flex-col items-center my-4">
                <p class="text-gray-500 text-center mb-4">No results found.</p>
                <x-button label="New Record" class="btn-primary btn-soft btn-sm" icon="o-plus"
                          x-on:click="$wire.showRecordDrawer = true; $wire.resetForm()"/>
            </div>
        </x-slot:empty>

        @scope('header_id', $header)
        <x-icon name="lucide.key" class="w-3 opacity-80"/> {{ $header['label'] }}
        @endscope

        @scope('header_name', $header)
        <x-icon name="lucide.user" class="w-3 opacity-80"/> {{ $header['label'] }}
        @endscope

        @scope('header_email', $header)
        <x-icon name="lucide.mail" class="w-3 opacity-80"/> {{ $header['label'] }}
        @endscope

        @scope('header_password', $header)
        <x-icon name="lucide.lock" class="w-3 opacity-80"/> {{ $header['label'] }}
        @endscope

        @scope('header_created_at', $header)
        <x-icon name="lucide.calendar-clock" class="w-3 opacity-80"/> {{ $header['label'] }}
        @endscope

        @scope('header_updated_at', $header)
        <x-icon name="lucide.calendar-clock" class="w-3 opacity-80"/> {{ $header['label'] }}
        @endscope

        @scope('cell_id', $row)
        <div class="badge badge-soft badge-sm flex items-center gap-2 py-3.5">
            <p>{{ str($row->id)->limit(16) }}</p>
            <x-copy-button :text="$row->id"/>
        </div>
        @endscope

        @scope('cell_password', $row)
        <span class="text-gray-400">********</span>
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

        @scope('actions', $row)
        <x-button icon="o-arrow-right" x-on:click="$wire.showRecord('{{ $row->id }}')"
                  spinner="showRecord('{{ $row->id }}')" class="btn-sm"/>
        @endscope
    </x-table>

    <div class="fixed bottom-0 left-0 right-0" x-show="$wire.selected.length > 0" x-transition x-cloak>
        <div class="flex justify-center m-8">
            <x-card>
                <div class="flex flex-row items-center gap-4">
                    <p>Selected <span class="font-bold">{{ count($this->selected) }}</span>
                        {{ str('record')->plural(count($this->selected)) }}</p>
                    <x-button label="Reset" x-on:click="$wire.selected = []" class="btn-soft"/>
                    <x-button label="Delete Selected" wire:click="promptDeleteRecord('{{ implode(',', $selected) }}')"
                              class="btn-error btn-soft"/>
                </div>
            </x-card>
        </div>
    </div>

    {{-- MODALS --}}

    <x-drawer wire:model="showRecordDrawer" class="w-full lg:w-2/5" right without-trap-focus>
        <div class="flex justify-between">
            <div class="flex items-center gap-2">
                <x-button icon="o-x-mark" class="btn-circle btn-ghost" x-on:click="$wire.showRecordDrawer = false"/>
                <p class="text-sm">{{ $editingUserId ? 'Update' : 'New' }} <span
                        class="font-bold">superusers</span> record</p>
            </div>
            <x-dropdown right>
                <x-slot:trigger>
                    <x-button icon="o-bars-2" class="btn-circle btn-ghost" :hidden="!$editingUserId"/>
                </x-slot:trigger>

                <x-menu-item title="Copy raw JSON" icon="o-document-text" x-data="{
                    copyJson() {
                        const data = {
                            name: $wire.name,
                            email: $wire.email
                        };
                        const json = JSON.stringify(data, null, 2);
                        window.copyText(json);
                        $wire.dispatchSelf('toast', { message: 'Copied raw JSON to your clipboard.' });
                    }
                }"
                             x-on:click="copyJson"/>
                <x-menu-item title="Duplicate" icon="o-document-duplicate"
                             x-on:click="$wire.duplicateRecord($wire.editingUserId)"/>

                <x-menu-separator/>

                <x-menu-item title="Delete" icon="o-trash" class="text-error"
                             x-on:click="$wire.promptDeleteRecord($wire.editingUserId)"/>
            </x-dropdown>
        </div>

        <div class="my-4"></div>

        <x-form wire:submit="saveRecord">
            <x-input label="Name" wire:model="name" icon="o-user" required/>

            <x-input label="Email" type="email" wire:model="email" icon="o-envelope" required autocomplete="email"/>

            @if (!$editingUserId)
                <x-password label="Password" wire:model="password" password-icon="o-key"
                            required autocomplete="new-password"/>
            @else
                <x-password label="New Password" wire:model="password_new" password-icon="o-key"
                            placeholder="Fill to change password..." autocomplete="new-password"/>
            @endif

            <x-slot:actions>
                <x-button label="Cancel" x-on:click="$wire.showRecordDrawer = false"/>
                <x-button label="Save" class="btn-primary" type="submit" spinner="saveRecord"/>
            </x-slot:actions>
        </x-form>

    </x-drawer>

    <x-modal wire:model="showConfirmDeleteDialog" title="Confirm Delete">
        Are you sure you want to delete {{ count($recordToDelete) > 1 ? count($recordToDelete) : 'this' }}
        {{ str('record')->plural(count($recordToDelete)) }}? This action cannot be undone.

        <x-slot:actions>
            <x-button label="Cancel" x-on:click="$wire.showConfirmDeleteDialog = false"/>
            <x-button class="btn-error" label="Delete" wire:click="confirmDeleteRecord" spinner="confirmDeleteRecord"/>
        </x-slot:actions>
    </x-modal>

</div>
