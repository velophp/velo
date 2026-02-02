<?php

namespace App\Delivery\Services;

use App\Domain\Record\Models\Record;
use App\Domain\Record\Services\RecordQuery;
use Illuminate\Support\Arr;

class FilterMatchingService
{
    /**
     * Check if a record matches the given filter string.
     * Value is case sensitive.
     * Filter string format: "status = Active AND category = Tech"
     */
    public function match(Record $record, ?string $filter): bool
    {
        if (! $filter) {
            return true;
        }

        $recordData = $record->data->toArray();
        $conditions = RecordQuery::parseFilterString($filter);

        $orGroupMatched = false;
        $hasOrConditions = false;

        foreach ($conditions as $condition) {
            $actualValue = Arr::get($recordData, $condition['field']);
            $matches = $this->compare($actualValue, $condition['operator'], $condition['value']);

            if ($condition['logical'] === 'OR') {
                $hasOrConditions = true;
                if ($matches) {
                    $orGroupMatched = true;
                }
            } else {
                if (! $matches) {
                    return false;
                }
            }
        }

        return $hasOrConditions ? $orGroupMatched : true;
    }

    protected function compare($actual, $operator, $expected): bool
    {
        return match ($operator) {
            '='     => $actual == $expected,
            '!='    => $actual != $expected,
            '>'     => $actual > $expected,
            '<'     => $actual < $expected,
            '>='    => $actual >= $expected,
            '<='    => $actual <= $expected,
            'LIKE'  => str_contains((string) $actual, (string) $expected),
            'IN'    => in_array($actual, (array) $expected),
            default => false,
        };
    }
}
