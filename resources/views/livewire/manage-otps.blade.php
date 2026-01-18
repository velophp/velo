<?php

use App\Models\AuthOtp;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
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
        'project.name' => false,
        'collection.name' => true,
        'record_id' => true,
        'email' => true,
        'token_hash' => true,
        'expires_at' => true,
        'used_at' => true,
        'created_at' => true,
        'updated_at' => false,
    ];

    // UI/UX State
    public bool $showConfirmDeleteDialog = false;
    public array $recordToDelete = [];

    public function mount(): void
    {
        $this->breadcrumbs = [
            ['link' => route('home'), 'icon' => 's-home'],
            ['label' => 'System'],
            ['label' => 'OTPs'],
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
            'project.name' => 'Project',
            'collection.name' => 'Collection',
            'record_id' => 'Record',
            'email' => 'Email',
            'token_hash' => 'Token',
            'expires_at' => 'Expires',
            'used_at' => 'Used',
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
        $query = AuthOtp::query();

        if (!empty($this->filter)) {
            $query->where(function ($q) {
                $q->where('email', 'like', "%{$this->filter}%")
                    ->orWhere('record_id', 'like', "%{$this->filter}%")
                    ->orWhere('collection_id', 'like', "%{$this->filter}%");
            });
        }

        if (!empty($this->sortBy['column'])) {
            $query->orderBy($this->sortBy['column'], $this->sortBy['direction']);
        }

        return $query->paginate($this->perPage);
    }

    /* === RECORD OPERATIONS === */

    public function promptDeleteRecord(string $ids): void
    {
        $this->recordToDelete = array_filter(explode(',', $ids));
        $this->showConfirmDeleteDialog = true;
    }

    public function confirmDeleteRecord(): void
    {
        $count = count($this->recordToDelete);
        AuthOtp::destroy($this->recordToDelete);

        $this->showConfirmDeleteDialog = false;
        $this->recordToDelete = [];
        $this->selected = [];
        unset($this->tableRows);

        $this->showSuccess("Deleted $count " . str('record')->plural($count) . ".");
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
    </div>

    <div class="my-8"></div>

    <x-input wire:model.live.debounce.250ms="filter" placeholder="Search by email, record ID, or collection..."
             icon="o-magnifying-glass" clearable/>

    <div class="my-4"></div>

    <div class="flex justify-end">
        <x-dropdown>
            <x-slot:trigger>
                <x-button icon="o-table-cells" class="btn-sm"/>
            </x-slot:trigger>

            <x-menu-item title="Toggle Fields" disabled/>

            @foreach ($fieldsVisibility as $field => $label)
                <x-menu-item :wire:key="$field" x-on:click.stop="$wire.toggleField('{{ $field }}')">
                    <x-toggle :label="str($field)->before('.')->value()"
                              :checked="$label ?? false"/>
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
            </div>
        </x-slot:empty>

        @scope('header_created_at', $header)
        <x-icon name="lucide.calendar-clock" class="w-3 opacity-80"/> {{ $header['label'] }}
        @endscope

        @scope('header_expires_at', $header)
        <x-icon name="lucide.clock" class="w-3 opacity-80"/> {{ $header['label'] }}
        @endscope
        
        @scope('header_used_at', $header)
        <x-icon name="lucide.check-circle" class="w-3 opacity-80"/> {{ $header['label'] }}
        @endscope

        @scope('cell_id', $row)
        <div class="badge badge-soft badge-sm flex items-center gap-2 py-3.5">
            <p>{{ str($row->id)->limit(16) }}</p>
            <x-copy-button :text="$row->id"/>
        </div>
        @endscope

        @scope('cell_record_id', $row)
        <div class="badge badge-soft badge-sm flex items-center gap-2 py-3.5">
            <p>{{ $row->record->data['name'] ?? $row->record->data['email'] ?? $row->record->data['id'] ?? $row->record_id }}</p>
            <x-button class="btn-xs btn-ghost btn-circle" link="{{ route('collections', ['collection' => $row->collection->name, 'recordId' => $row->record->data['id']]) }}" external>
                <x-icon name="lucide.external-link" class="w-5 h-5" />
            </x-button>
        </div>
        @endscope

        @scope('cell_token_hash', $row)
        <span class="text-gray-400">********</span>
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
            <p class="text-gray-400">-</p>
        @endif
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

    <x-modal wire:model="showConfirmDeleteDialog" title="Confirm Delete">
        Are you sure you want to delete {{ count($recordToDelete) > 1 ? count($recordToDelete) : 'this' }}
        {{ str('record')->plural(count($recordToDelete)) }}? This action cannot be undone.

        <x-slot:actions>
            <x-button label="Cancel" x-on:click="$wire.showConfirmDeleteDialog = false"/>
            <x-button class="btn-error" label="Delete" wire:click="confirmDeleteRecord" spinner="confirmDeleteRecord"/>
        </x-slot:actions>
    </x-modal>

</div>
