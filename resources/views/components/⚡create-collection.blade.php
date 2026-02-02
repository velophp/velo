<?php

use App\Domain\Collection\Enums\CollectionType;
use App\Domain\Collection\Models\Collection;
use App\Domain\Field\Models\CollectionField;
use App\Domain\Project\Models\Project;
use Illuminate\Validation\Rules\Enum;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public bool $showCreateCollectionForm = false;
    public string $collectionName = '';
    public $collectionType;

    public function mount()
    {
        $this->collectionType = CollectionType::Base;
    }

    protected function rules()
    {
        return [
            'collectionName' => 'required|regex:/^[a-zA-Z_]+$/|unique:collections,name',
            'collectionType' => ['required', new Enum(CollectionType::class)]
        ];
    }

    protected function messages()
    {
        return [
            'collectionName.regex' => 'Collection name can only contain letters and underscores.',
        ];
    }

    public function save()
    {
        $this->validate();

        $collection = Collection::create([
            'project_id' => Project::first()->id,
            'name' => $this->collectionName,
            'type' => $this->collectionType
        ]);

        $collectionFields = CollectionField::createBaseFrom([]);

        foreach ($collectionFields as $f) {
            $collection->fields()->create($f);
        }

        return $this->redirect(route('collections', ['collection' => $collection]), navigate: true);
    }

    #[On('create-collection')]
    public function openModal()
    {
        $this->showCreateCollectionForm = true;
    }
}; ?>

<div>
    <x-modal wire:model="showCreateCollectionForm" title="New Collection">
        <x-form wire:submit="save" no-separator>
            <x-input label="Name" icon="o-archive-box" wire:model="collectionName" placeholder="Name"/>
            <x-select label="Type" icon="o-table-cells" wire:model.live="collectionType"
                      :options="CollectionType::toOptions()"/>

            <x-slot:actions>
                <x-button label="Cancel" x-on:click="$wire.showCreateCollectionForm = false"/>
                <x-button label="Create" type="submit" spinner class="btn-primary"/>
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
