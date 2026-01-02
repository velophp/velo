<?php

use App\Enums\FieldType;
use App\Models\{Collection, Record};
use App\Services\{RecordQueryCompiler,RecordRulesCompiler};
use Livewire\Attributes\{Computed, Title};
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
    public array $recordToDelete = [];
    public array $form = [];

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

            if ($f->type == FieldType::Timestamp) {
                $headers['format'] = ['date', 'Y-m-d H:i:s'];
            } elseif ($f->type == FieldType::Date) {
                $headers['format'] = ['date', 'Y-m-d'];
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

        Record::create([
            'collection_id' => $this->collection->id,
            'data' => $this->form
        ]);

        $this->showRecordDetailDrawer = false;

        foreach ($this->fields as $field) {
            $this->form[$field->name] = $field->type === FieldType::Bool ? false : '';
        }
        
        unset($this->tableRows);

        $this->success(
            title: 'Success!',
            description: "Created new {$this->collection->name} record",
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000,
        );
    }

    public function show(string $id): void 
    {
        $compiler = new RecordQueryCompiler($this->collection);
        $result = $compiler->filter('id', '=', $id)->firstWithModel();

        if (!$result) {
            $this->error(
                title: 'Cannot show record.',
                description: "Record not found.",
                position: 'toast-bottom toast-end',
                icon: 'o-information-circle',
                css: 'alert-error',
                timeout: 3000,
            );
            return;
        }

        $this->form = $result->data->all();
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
                    timeout: 3000,
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
            timeout: 3000,
        );
    }

    public function openRecordDrawer()
    {
        foreach ($this->fields as $field) {
            $this->form[$field->name] = $field->type === FieldType::Bool ? false : '';
        }
        
        $this->showRecordDetailDrawer = true;
    }
}
?>

<div class="relative">
    
    <div class="flex justify-between flex-wrap">
        <div class="flex items-center gap-4">
            <x-breadcrumbs :items="$breadcrumbs" />
            <div class="flex items-center gap-2">
                <x-button icon="o-cog-6-tooth" tooltip-bottom="Configure Collection" class="btn-circle btn-ghost" x-on:click="$wire.showRecordDetailDrawer = false" />
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
                <x-button icon="o-document-duplicate" class="btn-circle btn-ghost btn-xs" x-on:click="window.copyText('{{ $row->id }}')" />
            </div>
        @endscope

        @scope('cell_created', $row)
            <div class="flex flex-col">
                <p>{{ Carbon::parse($row->created)->format('Y-m-d') }}</p>
                <p class="text-xs opacity-80">{{ Carbon::parse($row->created)->format('H:i:s') }}</p>
            </div>
        @endscope

        @scope('cell_updated', $row)
            <div class="flex flex-col">
                <p>{{ Carbon::parse($row->created)->format('Y-m-d') }}</p>
                <p class="text-xs opacity-80">{{ Carbon::parse($row->created)->format('H:i:s') }}</p>
            </div>
        @endscope

        @scope('actions', $row)
            <x-button icon="o-arrow-right" wire:click="show('{{ $row->id }}')" spinner class="btn-sm" />
        @endscope
    </x-table>

    <div class="fixed bottom-0 left-0 right-0" x-show="$wire.selected.length > 0" x-transition>
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
            
                <x-menu-item title="Copy raw JSON" icon="o-document-text" />
                <x-menu-item title="Duplicate" icon="o-document-duplicate" />

                <x-menu-separator />

                <x-menu-item title="Delete" icon="o-trash" class="text-error" wire:click="promptDelete('{{ $form['id'] }}')" />
            </x-dropdown>
        </div>
        
        <div class="my-4"></div>

        <x-form wire:submit="save">
            @foreach($fields as $field)
                @if ($field->name === 'id')
                    <x-input :label="$field->name" type="text" wire:model="form.id" icon="o-key" placeholder="Leave blank to auto generate..." />
                    @continue
                @elseif ($field->name === 'created' || $field->name === 'updated')
                    <x-input :label="$field->name" type="datetime" wire:model="form.{{ $field->name }}" icon="o-calendar-days" readonly />
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
                    @case(\App\Enums\FieldType::Timestamp)
                        <x-input :label="$field->name" type="datetime" wire:model="form.{{ $field->name }}" icon="o-calendar-days" :required="$field->required"  />
                        @break
                    @case(\App\Enums\FieldType::Password)
                        <x-password :label="$field->name" wire:model="form.{{ $field->name }}" password-icon="o-lock-closed" :required="$field->required"  />
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

    <x-modal wire:model="showConfirmDeleteDialog" title="Confirm Delete">
        Are you sure you want to delete {{ count($recordToDelete) > 1 ? count($recordToDelete) : 'this' }} {{ str('record')->plural(count($recordToDelete)) }}? This action cannot be undone.
    
        <x-slot:actions>
            <x-button label="Cancel" x-on:click="$wire.showConfirmDeleteDialog = false" />
            <x-button class="btn-error" label="Delete" wire:click="confirmDelete" spinner="confirmDelete" />
        </x-slot:actions>
    </x-modal>
</div>