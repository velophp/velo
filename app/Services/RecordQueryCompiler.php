<?php

namespace App\Services;

use App\Enums\FieldType;
use App\Models\Collection;
use App\Models\Record;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection as DataCollection;
use Illuminate\Support\Facades\DB;

class RecordQueryCompiler
{
    protected Collection $collection;

    protected $filters = [];

    protected $sorts = [];

    protected $expands = [];

    protected int $perPage = 15;

    protected ?int $page = null;

    protected Builder|EloquentBuilder $query;

    public function __construct(Collection $collection)
    {
        $this->collection = $collection;
        $this->query = Record::query();
    }

    // Append new rule to the filters value
    public function filter(string $field, string $operator, string $value)
    {
        $this->filters[] = compact('field', 'value', 'operator');

        return $this;
    }

    /**
     * Build a safe filter string from field-value pairs.
     * This method properly escapes values to prevent injection attacks.
     *
     * @param array $conditions Array of ['field' => 'value'] or [['field' => 'name', 'value' => 'val', 'operator' => '=']]
     * @param string $logical 'AND' or 'OR'
     * @return string
     */
    public static function buildSaveFilterString(array $conditions, string $logical = 'AND'): string
    {
        $parts = [];

        foreach ($conditions as $key => $condition) {
            if (is_array($condition)) {
                $field = $condition['field'] ?? '';
                $value = $condition['value'] ?? '';
                $operator = $condition['operator'] ?? '=';
            } else {
                $field = $key;
                $value = $condition;
                $operator = '=';
            }

            // Sanitize field name (alphanumeric and underscore only)
            $field = preg_replace('/[^a-zA-Z0-9_]/', '', $field);

            // Escape the value - wrap in quotes and escape internal quotes
            $escapedValue = '"' . str_replace('"', '\\"', $value) . '"';

            $parts[] = "$field $operator $escapedValue";
        }

        return implode(" $logical ", $parts);
    }

    // Compiled the string and REPLACES filters with the new value
    public function filterFromString(string $filterString)
    {
        if (empty(trim($filterString))) {
            $this->filters = [];

            return $this;
        }

        // Split by AND/OR, keeping the logical operators
        // Example: "name = John AND age > 18" becomes ["name = John", "AND", "age > 18"]
        $segments = preg_split('/\s+(AND|OR)\s+/i', $filterString, -1, PREG_SPLIT_DELIM_CAPTURE);

        $filters = [];

        // Process every other segment (skipping AND/OR operators)
        for ($i = 0; $i < count($segments); $i += 2) {
            $segment = trim($segments[$i]);

            if (empty($segment)) {
                continue;
            }

            // Parse the segment into field, operator, and value
            $parsed = $this->parseFilterSegment($segment);

            if ($parsed) {
                // Get the logical operator (AND/OR) that came before this segment
                $logical = ($i > 0 && isset($segments[$i - 1]))
                    ? strtoupper($segments[$i - 1])
                    : 'AND';

                $filters[] = array_merge($parsed, ['logical' => $logical]);
            }
        }

        $this->filters = $filters;

        return $this;
    }

    // Parse a single filter segment like "name = John" or "age >= 18"
    protected function parseFilterSegment(string $segment): ?array
    {
        // Whitelist of allowed operators to prevent operator injection
        $allowedOperators = ['>=', '<=', '!=', '<>', '=', '>', '<', 'LIKE'];

        foreach ($allowedOperators as $op) {
            // Build regex pattern to find the operator
            $pattern = ($op === 'LIKE')
                ? '/\s+' . preg_quote($op, '/') . '\s+/i'
                : '/' . preg_quote($op, '/') . '/';

            // Check if this operator exists in the segment
            if (preg_match($pattern, $segment, $matches, PREG_OFFSET_CAPTURE)) {
                $operatorPosition = $matches[0][1];
                $operatorLength = strlen($matches[0][0]);

                // Split segment into field and value parts
                $field = trim(substr($segment, 0, $operatorPosition));
                $value = trim(substr($segment, $operatorPosition + $operatorLength));

                // Sanitize field name - only allow alphanumeric and underscore
                $field = preg_replace('/[^a-zA-Z0-9_]/', '', $field);

                if (empty($field)) {
                    return null;
                }

                // Remove surrounding quotes from value if present
                $value = $this->removeQuotes($value);

                // Sanitize value to prevent injection
                $value = $this->sanitizeValue($value);

                return [
                    'field' => $field,
                    'operator' => strtoupper($op),
                    'value' => $value,
                ];
            }
        }

        return null;
    }

    /**
     * Sanitize a filter value to prevent injection attacks.
     */
    protected function sanitizeValue(string $value): string
    {
        // Remove null bytes which can be used in some injection attacks
        $value = str_replace(chr(0), '', $value);

        // The value is used with parameterized queries in buildQuery(),
        // so SQL characters don't need escaping. The main protection is that
        // values are properly quoted when passed through buildFilterString()
        // and parameterized in the final query.

        return $value;
    }

    protected function removeQuotes(string $value): string
    {
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    public function whereIn(string $field, array $values)
    {
        $this->filters[] = [
            'field' => $field,
            'operator' => 'IN',
            'value' => $values,
        ];

        return $this;
    }

    public function sort(string $field, string $direction = 'asc')
    {
        $this->sorts[] = compact('field', 'direction');

        return $this;
    }

    public function sortFromString(string $sortString)
    {
        foreach (explode(',', $sortString) as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            $direction = str_starts_with($part, '-') ? 'desc' : 'asc';
            $field = ltrim($part, '-');

            $this->sort($field, $direction);
        }

        return $this;
    }

    public function expand(string $field)
    {
        $this->expands[] = $field;

        return $this;
    }

    public function expandFromString(string $expandString)
    {
        foreach (explode(',', $expandString) as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            $this->expand($part);
        }

        return $this;
    }

    public function fromQuery(Builder|EloquentBuilder $query)
    {
        $this->query = $query;

        return $this;
    }

    public function buildQuery($baseQuery = null, $select = ['data'])
    {
        $query = ($this->query ?? $baseQuery ?? DB::table('records')
            ->select($select))
            ->where('collection_id', $this->collection?->id);

        // Manual JSON extraction for using mysql, enclosed to avoid bypassing collection_id where clause
        $query->where(function ($q) {
            foreach ($this->filters as $f) {
                $virtualCol = \App\Helper::generateVirtualColumnName($this->collection, $f['field']);
                $isIndexed = $this->isFieldIndexed($f['field']);
                $isOr = isset($f['logical']) && strtoupper($f['logical']) === 'OR';

                if ($f['operator'] === 'IN') {
                    if (empty($f['value'])) {
                        if (!$isOr) {
                            $q->whereRaw('1 = 0');
                        }

                        continue;
                    }

                    if ($isIndexed) {
                        $method = $isOr ? 'orWhereIn' : 'whereIn';
                        $q->$method($virtualCol, $f['value']);
                    } else {
                        // Slow json extraction
                        $placeholders = implode(',', array_fill(0, count($f['value']), '?'));
                        $rawSql = "JSON_UNQUOTE(JSON_EXTRACT(data, '$.\"{$f['field']}\"')) IN ($placeholders)";

                        if ($isOr) {
                            $q->orWhereRaw($rawSql, $f['value']);
                        } else {
                            $q->whereRaw($rawSql, $f['value']);
                        }
                    }

                    continue;
                }

                if ($isIndexed) {
                    $method = $isOr ? 'orWhere' : 'where';
                    $q->$method($virtualCol, $f['operator'], $f['value']);

                    continue;
                }

                // Slow json extraction as fallback
                $rawSql = "JSON_UNQUOTE(JSON_EXTRACT(data, '$.\"{$f['field']}\"')) {$f['operator']} ?";
                if ($isOr) {
                    $q->orWhereRaw($rawSql, [$f['value']]);
                } else {
                    $q->whereRaw($rawSql, [$f['value']]);
                }
            }
        });

        foreach ($this->sorts as $s) {
            $virtualCol = \App\Helper::generateVirtualColumnName($this->collection, $s['field']);
            if ($this->isFieldIndexed($s['field'])) {
                $query->orderBy($virtualCol, $s['direction']);

                continue;
            }

            // Fallback using json extract
            $query->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.\"{$s['field']}\"')) {$s['direction']}");
        }

        return $query;
    }

    public function casts(array|DataCollection &$data): array|DataCollection
    {
        foreach ($data as $key => $value) {
            if ($key === 'created' || $key === 'updated') {
                $data[$key] = Carbon::parse($value)->format('Y-m-d H:i:s');
            }
        }

        return $data;
    }

    public function firstRaw($casts = false): ?Record
    {
        $result = $this->buildQuery(Record::query())->first();
        if ($casts && $result?->data) {
            $this->casts($result->data);
        }

        return $result;
    }

    public function firstRawOrFail($casts = false): ?Record
    {
        $result = $this->buildQuery(Record::query())->firstOrFail();
        if ($casts && $result?->data) {
            $this->casts($result->data);
        }

        return $result;
    }

    public function first(): ?Record
    {
        $result = $this->buildQuery(Record::query())->first();
        if ($result?->data) {
            $this->casts($result->data);
        }

        if ($result) {
            $this->expandRecord($result);
        }

        return $result;
    }

    public function firstOrFail(): Record
    {
        $result = $this->buildQuery(Record::query())->first();

        if (!$result) {
            throw new ModelNotFoundException('Resource not found.');
        }

        if ($result?->data) {
            $this->casts($result->data);
        }

        $this->expandRecord($result);

        return $result;
    }

    public function simplePaginate(?int $perPage = null, ?int $page = null)
    {
        $perPage ??= 15;
        $currentPage = $page ?? Paginator::resolveCurrentPage();

        $query = $this->buildQuery();
        $items = $query
            ->offset(($currentPage - 1) * $this->perPage)
            ->limit($this->perPage + 1)
            ->get();

        $items = $this->expandRecords($items);

        return new Paginator(
            $items,
            $perPage,
            $currentPage,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        );
    }

    public function paginate(?int $perPage = null, ?int $page = null)
    {
        if ($perPage !== null) {
            $this->perPage = $perPage;
        }

        if ($page !== null) {
            $this->page = $page;
        }

        $query = $this->buildQuery();

        // Get total count
        $total = $query->count();

        // Get current page
        $currentPage = $this->page ?? LengthAwarePaginator::resolveCurrentPage();

        // Get paginated results
        $results = $query
            ->offset(($currentPage - 1) * $this->perPage)
            ->limit($this->perPage)
            ->get()
            ->map(fn($d) => json_decode($d->data));

        return new LengthAwarePaginator(
            $results,
            $total,
            $this->perPage,
            $currentPage,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
            ],
        );
    }

    protected function expandRecord(Record $record): void
    {
        if (empty($this->expands)) {
            return;
        }

        $relationFields = $this->collection->fields()
            ->where('type', FieldType::Relation)
            ->whereIn('name', $this->expands)
            ->get()
            ->keyBy('name');

        foreach ($this->expands as $fieldName) {
            if (!$relationFields->has($fieldName)) {
                continue;
            }

            $relationField = $relationFields->get($fieldName);
            $fieldValue = $record->data->get($relationField->name);

            if (empty($fieldValue)) {
                continue;
            }

            $idsToFetch = collect($fieldValue)->flatten()->unique()->filter()->values();
            if ($idsToFetch->isEmpty()) {
                continue;
            }

            $relatedCollection = Collection::find($relationField->options?->collection);
            if (!$relatedCollection) {
                continue;
            }

            $expandedRecords = $relatedCollection->recordQueryCompiler()
                ->whereIn('id', $idsToFetch->toArray())
                ->buildQuery()
                ->get()
                ->pluck('data')
                ->keyBy('id');

            $expand = $record->data->get('expand', []);
            $idsFromRelation = collect($fieldValue);

            if ($relationField->options?->multiple) {
                $expand[$relationField->name] = $idsFromRelation
                    ->map(fn($id) => $expandedRecords->get($id))
                    ->filter()
                    ->values();
            } else {
                $id = is_array($idsFromRelation->first()) ? null : $idsFromRelation->first();
                $expand[$relationField->name] = $id ? $expandedRecords->get($id) : null;
            }

            $record->data->put('expand', $expand);
        }
    }

    protected function expandRecords(\Illuminate\Database\Eloquent\Collection $results)
    {
        if (empty($this->expands) || $results->isEmpty()) {
            return $results;
        }

        $relationFields = $this->collection->fields()
            ->where('type', FieldType::Relation)
            ->whereIn('name', $this->expands)
            ->get()
            ->keyBy('name');

        foreach ($this->expands as $fieldName) {
            if (!$relationFields->has($fieldName)) {
                continue;
            }

            $relationField = $relationFields->get($fieldName);

            $idsToFetch = $results
                ->pluck('data')
                ->pluck($relationField->name)
                ->flatten()
                ->unique()
                ->filter()
                ->values();
            if ($idsToFetch->isEmpty()) {
                continue;
            }

            $relatedCollection = Collection::find($relationField->options?->collection);
            if (!$relatedCollection) {
                continue;
            }

            $expandedRecords = $relatedCollection->recordQueryCompiler()
                ->whereIn('id', $idsToFetch->toArray())
                ->buildQuery()
                ->get()
                ->pluck('data')
                ->keyBy('id');

            $results->each(function (Record $record) use ($relationField, $expandedRecords) {
                $expand = $record->data->get('expand', []);
                $idsFromRelation = collect($record->data->get($relationField->name, []));

                if ($relationField->options?->multiple) {
                    $expand[$relationField->name] = $idsFromRelation
                        ->map(fn($id) => $expandedRecords->get($id))
                        ->filter()
                        ->values();
                } else {
                    $id = is_array($idsFromRelation->first()) ? null : $idsFromRelation->first();
                    $expand[$relationField->name] = $id ? $expandedRecords->get($id) : null;
                }

                $record->data->put('expand', $expand);
            });
        }

        return $results;
    }

    protected function isFieldIndexed(string $fieldName): bool
    {
        // Fast check for now, might check for the actual vcol later
        return DB::table('collection_indexes')
            ->where('collection_id', $this->collection->id)
            ->whereJsonContains('field_names', $fieldName)
            ->exists();
    }
}
