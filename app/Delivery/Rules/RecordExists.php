<?php

namespace App\Delivery\Rules;

use App\Domain\Collection\Models\Collection;
use App\Support\Helper;
use Illuminate\Contracts\Validation\ValidationRule;

class RecordExists implements ValidationRule
{
    protected ?Collection $collection = null;

    protected bool $isIdIndexed = false;

    public function __construct(string $collectionId)
    {
        $this->collection = Collection::find($collectionId);

        if ($this->collection) {
            $this->isIdIndexed = $this->collection->indexes()
                ->whereJsonContains('field_names', 'id')
                ->exists();
        }
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        if (! $this->collection) {
            $fail("The {$attribute} collection does not exist.");

            return;
        }

        if (! \is_string($value)) {
            $fail("The {$attribute} must be a valid record ID.");

            return;
        }

        $exists = $this->checkRecordExists($value);

        if (! $exists) {
            $fail("The selected {$attribute} does not exist in {$this->collection->name} collection.");
        }
    }

    protected function checkRecordExists(string $recordId): bool
    {
        if ($this->isIdIndexed) {
            // Use the fast indexed query
            $virtualCol = Helper::generateVirtualColumnName($this->collection, 'id');

            return \DB::table('records')
                ->where('collection_id', $this->collection->id)
                ->where($virtualCol, $recordId)
                ->exists();
        } else {
            // Use the slower JSON query
            return \DB::table('records')
                ->where('collection_id', $this->collection->id)
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.id')) = ?", [$recordId])
                ->exists();
        }
    }
}
