<?php

use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component {

    use Toast;

    public bool $showRelationPickerModal = false;

    public $collection;
    public array $relationPicker = [
        'collection' => null,
        'fieldName' => '',
        'multiple' => false,
        'search' => '',
        'records' => [],
        'selected' => null,
        'displayField' => 'id',
    ];

    #[On('open-relation-picker')]
    public function openRelationPicker(string $collectionId, string $fieldName, array|string|null $selected, bool $multiple = false): void
    {
        $collection = \App\Domain\Collection\Models\Collection::find($collectionId);

        if (!$collection) {
            $this->dispatch('toast', message: 'Collection not found.', css: 'alert-error');
            return;
        }

        $priority = config('velo.relation_display_fields', ['email', 'name', 'title', 'slug', 'username']);

        $displayField = $collection->fields
            ->whereIn('name', $priority)
            ->sortBy(fn($field) => array_search($field->name, $priority))
            ->first()?->name;

        if (!$displayField) {
            $displayField = 'id';
        }

        $this->collection = $collection;
        $this->relationPicker = [
            'collection' => $collection,
            'fieldName' => $fieldName,
            'multiple' => $multiple,
            'search' => '',
            'records' => [],
            'selected' => $selected,
            'displayField' => $displayField,
        ];

        $this->loadRelationRecords();
        $this->showRelationPickerModal = true;
    }

    public function loadRelationRecords(): void
    {
        if (!$this->relationPicker['collection']) {
            return;
        }

        $query = $this->relationPicker['collection']->records();

        if (!empty($this->relationPicker['search'])) {
            $query->filterFromString($this->relationPicker['search']);
        }

        // Limit to 50 for now, maybe pagination later
        $this->relationPicker['records'] = $query->buildQuery()->limit(50)->get();
    }

    public function updatedRelationPickerSearch(): void
    {
        $this->loadRelationRecords();
    }

    public function toggleRelationRecord(string $recordId): void
    {
        $selected = $this->relationPicker['selected'];

        if (!$this->relationPicker['multiple']) {
            $this->relationPicker['selected'] = $selected == $recordId ? null : $recordId;
            return;
        }

        if (in_array($recordId, $selected)) {
            $this->relationPicker['selected'] = array_values(array_filter($selected, fn($id) => $id !== $recordId));
            return;
        }

        $this->relationPicker['selected'][] = $recordId;
    }

    public function saveRelationSelection(): void
    {
        $this->dispatch('relation-selected',
            fieldName: $this->relationPicker['fieldName'],
            selected: $this->relationPicker['selected']
        );
        $this->showRelationPickerModal = false;
    }

};
?>

<x-modal wire:model="showRelationPickerModal"
         title="Select {{ $relationPicker['collection']->name ?? 'users' }} records">
    <div class="space-y-6">
        <x-input
            wire:model.live.debounce.300ms="relationPicker.search"
            placeholder="Filter records..."
            icon="o-magnifying-glass"
            clearable
        >
            <x-slot:append>
                <x-button
                    link="{{ route('collections', ['collection' => $relationPicker['collection'] ?? '--', 'recordId' => '--']) }}"
                    external>
                    New record
                </x-button>
            </x-slot:append>
        </x-input>

        <div class="border border-base-300 rounded-md overflow-hidden max-h-96 overflow-y-auto">
            @if(!empty($relationPicker['records']))
                @foreach($relationPicker['records'] as $record)
                    @php($isSelected = $relationPicker['multiple'] ? (in_array($record->data['id'], $relationPicker['selected'] ?? [])) : $record->data['id'] == $relationPicker['selected'])

                    <div
                        wire:key="relation-record-{{ $record->data['id'] }}"
                        class="group flex items-center justify-between p-4 border-b border-base-200 last:border-b-0 cursor-pointer hover:bg-base-300 transition-colors {{ $isSelected ? 'bg-base-300' : '' }}"
                        wire:click="toggleRelationRecord('{{ $record->data['id'] }}')">

                        <div class="flex items-center gap-4">
                            <div class="shrink-0">
                                <x-icon
                                    name="o-check-circle" @class(['size-6 stroke-primary transition-all duration-300', 'opacity-10 grayscale-100' => !$isSelected]) />
                            </div>

                            <div class="flex items-baseline gap-2">
                                <div>
                                    <p class="font-medium">
                                        {{ $record->data[$relationPicker['displayField']] }}
                                    </p>
                                    <p class="text-xs opacity-80">
                                        {{ $record->data['id'] }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="{{ $isSelected ? 'block' : 'hidden group-hover:block' }}">
                            {{--                            <x-button x-on:click.stop="" class="btn-ghost rounded-full btn-xs"--}}
                            {{--                                      wire:click.stop="editRecord('{{ $record->data['id'] }}')">--}}
                            {{--                                <x-icon name="o-pencil" class="w-4 h-4 text-gray-500"/>--}}
                            {{--                            </x-button>--}}
                            <x-button x-on:click.stop=""
                                      link="{{ route('collections', ['collection' => $relationPicker['collection'], 'recordId' => $record->data['id']]) }}"
                                      external class="btn-ghost rounded-full btn-xs">
                                <x-icon name="o-arrow-top-right-on-square" class="w-4 h-4 text-gray-400"/>
                            </x-button>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="p-8 text-center text-gray-500">
                    No records found
                </div>
            @endif
        </div>

        <div>
            <h4 class="font-bold text-gray-600 text-sm mb-2">Selected</h4>
            @if(empty($relationPicker['selected']))
                <p class="text-gray-400 text-sm">No selected records.</p>
            @else
                <div class="flex flex-wrap gap-2">
                    <span
                        class="text-sm text-gray-600">{{ !$relationPicker['multiple'] ? '1' : count($relationPicker['selected']) }} record(s) selected</span>
                </div>
            @endif
        </div>
    </div>

    <x-slot:actions>
        <x-button
            label="Cancel"
            x-on:click="$wire.showRelationPickerModal = false"/>

        <x-button
            label="Set selection"
            class="btn-primary"
            wire:click="saveRelationSelection"
            spinner="saveRelationSelection"/>
    </x-slot:actions>
</x-modal>
