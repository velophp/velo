<?php

use App\Enums\FieldType;
use App\Models\Collection;
use App\Models\Record;
use App\Services\RecordQueryCompiler;
use function Livewire\Volt\{state, mount, title, usesFileUploads, protect};

usesFileUploads();

state([
    'collection',
    'fields',
    'breadcrumbs' => [],
    'search' => '',
    'tableHeaders' => [],
    'tableRows' => [],
    'showDetailDrawer' => false,
    'form' => []
]);

mount(function (Collection $collection) {
    $this->collection = $collection;
    $this->fields = $collection->fields;

    foreach ($this->fields as $field) {
        $this->form[$field->name] = $field->type === FieldType::Bool ? false : '';
    }

    $compiler = new RecordQueryCompiler($collection);

    $this->breadcrumbs = [
        ['link' => route('home'), 'icon' => 's-home'],
        ['label' => 'Collection'],
        ['label' => $this->collection->name]
    ];

    $this->tableHeaders = $this->fields->map(fn($f) => [
        'key' => $f->name,
        'label' => $f->name,
    ])->toArray();

    $this->tableRows = $compiler->get();
});

title(fn() => "Collection - {$this->collection->name}");

$validateFields = protect(function() {
    $rules = [];

    foreach ($this->fields as $field) {
        if (in_array($field->name, ['created', 'updated'])) {
            continue;
        }

        $fieldRules = [];

        if ($field->name === 'id') {
            $fieldRules[] = 'nullable';
        } elseif ($field->required) {
            $fieldRules[] = 'required';
        } else {
            $fieldRules[] = 'nullable';
        }

        if ($field->type === FieldType::Email) {
            $fieldRules[] = 'email';
        }
        
        if ($field->type === FieldType::Number) {
            $fieldRules[] = 'numeric';
        }

        if ($field->type === FieldType::Bool) {
            $fieldRules[] = 'boolean';
        }

        $rules['form.' . $field->name] = $fieldRules;
    }

    return $this->validate($rules);
});

$save = function() {
    $this->validateFields();

    Record::create([
        'collection_id' => $this->collection->id,
        'data' => $this->form
    ]);

    $this->showDetailDrawer = false;
    
    foreach ($this->fields as $field) {
        $this->form[$field->name] = $field->type === FieldType::Bool ? false : '';
    }

    $compiler = new RecordQueryCompiler($this->collection);
    $this->tableRows = $compiler->get();
};

?>

<div>
    
    <div class="flex justify-between flex-wrap">
        <div class="flex items-center gap-4">
            <x-breadcrumbs :items="$breadcrumbs" />
            <div class="flex items-center gap-2">
                <x-button icon="o-cog-6-tooth" class="btn-circle btn-ghost" x-on:click="$wire.showDetailDrawer = false" />
                <x-button icon="o-arrow-path" class="btn-circle btn-ghost" wire:click="$refresh" />
            </div>
        </div>
        <div class="flex items-center gap-2">
            <x-button label="New Record" class="btn-primary" icon="o-plus" wire:click="$toggle('showDetailDrawer')" />
        </div>
    </div>

    <div class="my-8"></div>

    <x-input wire:model.live.debounce.250ms="search" placeholder="Search term or filter using rules..." icon="o-magnifying-glass" clearable />

    <div class="my-8"></div>

    <x-table :headers="$tableHeaders" :rows="$tableRows" striped @row-click="alert($event.detail.name)" />

    @if($tableRows->count() < 1)

        <div class="flex flex-col items-center my-8">
            <p class="text-gray-500 text-center mb-4">No results found.</p>
            <x-button label="New Record" class="btn-primary btn-soft btn-sm" icon="o-plus" wire:click="$toggle('showDetailDrawer')" />
        </div>

    @endif

    <x-drawer wire:model="showDetailDrawer" class="w-11/12 lg:w-1/3" right>
        <div class="flex justify-between">
            <div class="flex items-center gap-2">
                <x-button icon="o-x-mark" class="btn-circle btn-ghost" x-on:click="$wire.showDetailDrawer = false" />
                <p class="text-sm">New <span class="font-bold">{{ $collection->name }}</span> record</p>
            </div>
            <x-button icon="o-bars-2" class="btn-circle btn-ghost" />
        </div>
        
        <div class="my-4"></div>

        <x-form wire:submit="save">
            @foreach($fields as $field)
                @if ($field->name === 'id')
                    <x-input :label="$field->name" type="number" wire:model="form.{{ $field->name }}" icon="o-key" placeholder="Leave blank to auto generate..." />
                    @continue
                @elseif ($field->name === 'created' || $field->name === 'updated')
                    <x-input :label="$field->name" type="date" wire:model="form.{{ $field->name }}" icon="o-calendar-days" readonly />
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
                <x-button label="Cancel" x-on:click="$wire.showDetailDrawer = false" />
                <x-button label="Save" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-form>

    </x-drawer>
</div>