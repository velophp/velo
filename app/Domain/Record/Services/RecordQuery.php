<?php

namespace App\Domain\Record\Services;

use App\Domain\Collection\Models\Collection;
use App\Domain\Field\Enums\FieldType;
use App\Domain\Record\Models\Record;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection as DataCollection;
use Illuminate\Support\Facades\DB;

class RecordQuery
{
    protected Collection|int|string $collection;

    protected ?int $collectionId = null;

    protected array $filters = [];

    protected array $sorts = [];

    protected array $expands = [];

    protected int $perPage = 15;

    protected ?int $page = null;

    protected Builder|EloquentBuilder $query;

    /**
     * Create a new RecordQuery instance.
     *
     * @param  Collection|int|string  $collection  Collection instance or collection ID
     */
    public function __construct(Collection|int|string $collection)
    {
        if ($collection instanceof Collection) {
            $this->collection = $collection;
            $this->collectionId = $collection->id;
        } else {
            $this->collectionId = (int) $collection;
            $this->collection = $collection;
        }

        $this->query = Record::query();
    }

    /**
     * Static factory method for fluent API.
     *
     * @param  Collection|int|string  $collection  Collection instance or collection ID
     */
    public static function for(Collection|int|string $collection): self
    {
        return new self($collection);
    }

    /**
     * Get the Collection model, lazy-loading if only ID was provided.
     */
    protected function getCollection(): Collection
    {
        if (! ($this->collection instanceof Collection)) {
            $this->collection = Collection::findOrFail($this->collectionId);
        }

        return $this->collection;
    }

    /**
     * Add an AND filter condition.
     */
    public function filter(string $field, string $operator, mixed $value): self
    {
        $this->filters[] = [
            'field'    => $field,
            'operator' => $operator,
            'value'    => $value,
            'logical'  => 'AND',
        ];

        return $this;
    }

    /**
     * Add an OR filter condition.
     */
    public function orFilter(string $field, string $operator, mixed $value): self
    {
        $this->filters[] = [
            'field'    => $field,
            'operator' => $operator,
            'value'    => $value,
            'logical'  => 'OR',
        ];

        return $this;
    }

    /**
     * Add filters from a filter string (appends to existing filters).
     */
    public function filterFromString(string $filterString): self
    {
        if (empty(trim($filterString))) {
            return $this;
        }

        $segments = preg_split('/\s+(AND|OR)\s+/i', $filterString, -1, PREG_SPLIT_DELIM_CAPTURE);

        for ($i = 0; $i < count($segments); $i += 2) {
            $segment = trim($segments[$i]);

            if (empty($segment)) {
                continue;
            }

            $parsed = $this->parseFilterSegment($segment);

            if ($parsed) {
                $logical = ($i > 0 && isset($segments[$i - 1]))
                    ? strtoupper($segments[$i - 1])
                    : 'AND';

                $this->filters[] = array_merge($parsed, ['logical' => $logical]);
            }
        }

        return $this;
    }

    /**
     * Add a WHERE IN filter.
     */
    public function whereIn(string $field, array $values): self
    {
        $this->filters[] = [
            'field'    => $field,
            'operator' => 'IN',
            'value'    => $values,
            'logical'  => 'AND',
        ];

        return $this;
    }

    /**
     * Clear all filters.
     */
    public function clearFilters(): self
    {
        $this->filters = [];

        return $this;
    }

    /**
     * Add a sort condition.
     */
    public function sort(string $field, string $direction = 'asc'): self
    {
        $this->sorts[] = compact('field', 'direction');

        return $this;
    }

    /**
     * Add sorts from a sort string (e.g., "-created,name").
     */
    public function sortFromString(string $sortString): self
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

    /**
     * Add a relation to expand.
     */
    public function expand(string $field): self
    {
        $this->expands[] = $field;

        return $this;
    }

    /**
     * Add expands from a comma-separated string.
     */
    public function expandFromString(string $expandString): self
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

    /**
     * Set the base query.
     */
    public function fromQuery(Builder|EloquentBuilder $query): self
    {
        $this->query = $query;

        return $this;
    }

    /**
     * Set pagination parameters.
     */
    public function perPage(int $perPage): self
    {
        $this->perPage = $perPage;

        return $this;
    }

    /**
     * Set the page number.
     */
    public function page(int $page): self
    {
        $this->page = $page;

        return $this;
    }

    // ========== EXECUTION METHODS ==========

    /**
     * Get all matching records (with expansion and casting).
     */
    public function get(): \Illuminate\Database\Eloquent\Collection
    {
        $results = $this->buildQuery(Record::query())->get();

        foreach ($results as $record) {
            if ($record->data) {
                $this->applyCasts($record->data);
            }
        }

        return $this->applyExpansion($results);
    }

    /**
     * Get all matching records (raw, no expansion/casting).
     */
    public function getRaw(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->buildQuery(Record::query())->get();
    }

    /**
     * Get the first matching record (with expansion and casting).
     */
    public function first(): ?Record
    {
        $result = $this->buildQuery(Record::query())->first();

        if ($result?->data) {
            $this->applyCasts($result->data);
            $this->applyExpansionToRecord($result);
        }

        return $result;
    }

    /**
     * Get the first matching record or throw (with expansion and casting).
     */
    public function firstOrFail(): Record
    {
        $result = $this->first();

        if (! $result) {
            throw new ModelNotFoundException('Resource not found.');
        }

        return $result;
    }

    /**
     * Get the first matching record (raw, no expansion/casting).
     */
    public function firstRaw(): ?Record
    {
        return $this->buildQuery(Record::query())->first();
    }

    /**
     * Get the first matching record or throw (raw).
     */
    public function firstRawOrFail(): Record
    {
        return $this->buildQuery(Record::query())->firstOrFail();
    }

    /**
     * Get the count of matching records.
     */
    public function count(): int
    {
        return $this->buildQuery()->count();
    }

    /**
     * Check if any matching records exist.
     */
    public function exists(): bool
    {
        return $this->buildQuery()->exists();
    }

    /**
     * Get the SQL query string (for debugging).
     */
    public function toSql(): string
    {
        return $this->buildQuery()->toSql();
    }

    /**
     * Paginate results with length aware paginator.
     */
    public function paginate(?int $perPage = null, ?int $page = null): LengthAwarePaginator
    {
        if ($perPage !== null) {
            $this->perPage = $perPage;
        }

        if ($page !== null) {
            $this->page = $page;
        }

        $query = $this->buildQuery();
        $total = $query->count();
        $currentPage = $this->page ?? LengthAwarePaginator::resolveCurrentPage();

        $results = $query
            ->offset(($currentPage - 1) * $this->perPage)
            ->limit($this->perPage)
            ->get()
            ->map(fn ($d) => json_decode($d->data));

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

    /**
     * Simple paginate results.
     */
    public function simplePaginate(?int $perPage = null, ?int $page = null): Paginator
    {
        if ($perPage !== null) {
            $this->perPage = $perPage;
        }

        $currentPage = $page ?? Paginator::resolveCurrentPage();

        $query = $this->buildQuery();
        $items = $query
            ->offset(($currentPage - 1) * $this->perPage)
            ->limit($this->perPage + 1)
            ->get();

        $items = $this->applyExpansion($items);

        return new Paginator(
            $items,
            $this->perPage,
            $currentPage,
            [
                'path'     => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        );
    }

    // ========== STATIC HELPERS ==========

    /**
     * Build a safe filter string from field-value pairs.
     */
    public static function buildFilterString(array $conditions, string $logical = 'AND'): string
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

            $field = preg_replace('/[^a-zA-Z0-9_]/', '', $field);
            $escapedValue = '"' . str_replace('"', '\"', $value) . '"';
            $parts[] = "$field $operator $escapedValue";
        }

        return implode(" $logical ", $parts);
    }

    /**
     * Check if a filter string is valid (can be parsed without errors).
     */
    public static function isValidFilterString(?string $filterString): bool
    {
        if ($filterString === null || empty(trim($filterString))) {
            return true; // Empty/null is considered valid (no filter)
        }

        $segments = preg_split('/\s+(AND|OR)\s+/i', $filterString, -1, PREG_SPLIT_DELIM_CAPTURE);

        for ($i = 0; $i < count($segments); $i += 2) {
            $segment = trim($segments[$i]);

            if (empty($segment)) {
                continue;
            }

            $parsed = self::parseFilterSegmentStatic($segment);

            if ($parsed === null) {
                return false; // Invalid segment
            }
        }

        return true;
    }

    /**
     * Parse a filter string into an array of conditions.
     * Returns an array of ['field' => '...', 'operator' => '...', 'value' => '...', 'logical' => 'AND|OR']
     *
     * @TODO refactor to its own service later
     */
    public static function parseFilterString(string $filterString): array
    {
        if (empty(trim($filterString))) {
            return [];
        }

        $filters = [];
        $segments = preg_split('/\s+(AND|OR)\s+/i', $filterString, -1, PREG_SPLIT_DELIM_CAPTURE);

        for ($i = 0; $i < count($segments); $i += 2) {
            $segment = trim($segments[$i]);

            if (empty($segment)) {
                continue;
            }

            $parsed = self::parseFilterSegmentStatic($segment);

            if ($parsed) {
                $logical = ($i > 0 && isset($segments[$i - 1]))
                    ? strtoupper($segments[$i - 1])
                    : 'AND';

                $filters[] = array_merge($parsed, ['logical' => $logical]);
            }
        }

        return $filters;
    }

    /**
     * Static version of parseFilterSegment for use outside instance context.
     */
    protected static function parseFilterSegmentStatic(string $segment): ?array
    {
        $allowedOperators = ['>=', '<=', '!=', '<>', '=', '>', '<', 'LIKE'];

        foreach ($allowedOperators as $op) {
            $pattern = ($op === 'LIKE')
                ? '/\s+' . preg_quote($op, '/') . '\s+/i'
                : '/' . preg_quote($op, '/') . '/';

            if (preg_match($pattern, $segment, $matches, PREG_OFFSET_CAPTURE)) {
                $operatorPosition = $matches[0][1];
                $operatorLength = strlen($matches[0][0]);

                $field = trim(substr($segment, 0, $operatorPosition));
                $value = trim(substr($segment, $operatorPosition + $operatorLength));

                $field = preg_replace('/[^a-zA-Z0-9_]/', '', $field);

                if (empty($field)) {
                    return null;
                }

                // Remove quotes
                if (
                    (str_starts_with($value, '"') && str_ends_with($value, '"'))
                    || (str_starts_with($value, "'") && str_ends_with($value, "'"))
                ) {
                    $value = substr($value, 1, -1);
                }

                // Sanitize
                $value = str_replace(chr(0), '', $value);

                return [
                    'field'    => $field,
                    'operator' => strtoupper($op),
                    'value'    => $value,
                ];
            }
        }

        return null;
    }

    // ========== INTERNAL METHODS ==========

    /**
     * Build the query with all filters and sorts applied.
     */
    public function buildQuery($baseQuery = null, $select = ['data']): Builder|EloquentBuilder
    {
        $query = ($this->query ?? $baseQuery ?? DB::table('records')
            ->select($select))
            ->where('collection_id', $this->collectionId);

        $query->where(function ($q) {
            foreach ($this->filters as $f) {
                $virtualCol = \App\Support\Helper::generateVirtualColumnName($this->getCollection(), $f['field']);
                $isIndexed = $this->isFieldIndexed($f['field']);
                $isOr = isset($f['logical']) && strtoupper($f['logical']) === 'OR';

                if ($f['operator'] === 'IN') {
                    if (empty($f['value'])) {
                        if (! $isOr) {
                            $q->whereRaw('1 = 0');
                        }

                        continue;
                    }

                    if ($isIndexed) {
                        $method = $isOr ? 'orWhereIn' : 'whereIn';
                        $q->$method($virtualCol, $f['value']);
                    } else {
                        $placeholders = implode(',', array_fill(0, count($f['value']), '?'));
                        $rawSql = "JSON_UNQUOTE(JSON_EXTRACT(data, '$.\"" . $f['field'] . "\"')) IN ($placeholders)";

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

                $rawSql = "JSON_UNQUOTE(JSON_EXTRACT(data, '$.\"" . $f['field'] . "\"')) {$f['operator']} ?";
                if ($isOr) {
                    $q->orWhereRaw($rawSql, [$f['value']]);
                } else {
                    $q->whereRaw($rawSql, [$f['value']]);
                }
            }
        });

        foreach ($this->sorts as $s) {
            $virtualCol = \App\Support\Helper::generateVirtualColumnName($this->getCollection(), $s['field']);
            if ($this->isFieldIndexed($s['field'])) {
                $query->orderBy($virtualCol, $s['direction']);

                continue;
            }

            $query->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.\"" . $s['field'] . "\"')) {$s['direction']}");
        }

        return $query;
    }

    protected function parseFilterSegment(string $segment): ?array
    {
        $allowedOperators = ['>=', '<=', '!=', '<>', '=', '>', '<', 'LIKE'];

        foreach ($allowedOperators as $op) {
            $pattern = ($op === 'LIKE')
                ? '/\s+' . preg_quote($op, '/') . '\s+/i'
                : '/' . preg_quote($op, '/') . '/';

            if (preg_match($pattern, $segment, $matches, PREG_OFFSET_CAPTURE)) {
                $operatorPosition = $matches[0][1];
                $operatorLength = strlen($matches[0][0]);

                $field = trim(substr($segment, 0, $operatorPosition));
                $value = trim(substr($segment, $operatorPosition + $operatorLength));

                $field = preg_replace('/[^a-zA-Z0-9_]/', '', $field);

                if (empty($field)) {
                    return null;
                }

                $value = $this->removeQuotes($value);
                $value = $this->sanitizeValue($value);

                return [
                    'field'    => $field,
                    'operator' => strtoupper($op),
                    'value'    => $value,
                ];
            }
        }

        return null;
    }

    protected function sanitizeValue(string $value): string
    {
        return str_replace(chr(0), '', $value);
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

    protected function applyCasts(array|DataCollection &$data): void
    {
        foreach ($data as $key => $value) {
            if ($key === 'created' || $key === 'updated') {
                $data[$key] = Carbon::parse($value)->format('Y-m-d H:i:s');
            }
        }
    }

    protected function isFieldIndexed(string $fieldName): bool
    {
        return DB::table('collection_indexes')
            ->where('collection_id', $this->collectionId)
            ->whereJsonContains('field_names', $fieldName)
            ->exists();
    }

    protected function applyExpansionToRecord(Record $record): void
    {
        if (empty($this->expands)) {
            return;
        }

        $collection = $this->getCollection();
        $relationFields = $collection->fields()
            ->where('type', FieldType::Relation)
            ->whereIn('name', $this->expands)
            ->get()
            ->keyBy('name');

        foreach ($this->expands as $fieldName) {
            if (! $relationFields->has($fieldName)) {
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
            if (! $relatedCollection) {
                continue;
            }

            $expandedRecords = RecordQuery::for($relatedCollection)
                ->whereIn('id', $idsToFetch->toArray())
                ->buildQuery()
                ->get()
                ->pluck('data')
                ->keyBy('id');

            $expand = $record->data->get('expand', []);
            $idsFromRelation = collect($fieldValue);

            if ($relationField->options?->multiple) {
                $expand[$relationField->name] = $idsFromRelation
                    ->map(fn ($id) => $expandedRecords->get($id))
                    ->filter()
                    ->values();
            } else {
                $id = is_array($idsFromRelation->first()) ? null : $idsFromRelation->first();
                $expand[$relationField->name] = $id ? $expandedRecords->get($id) : null;
            }

            $record->data->put('expand', $expand);
        }
    }

    protected function applyExpansion(\Illuminate\Database\Eloquent\Collection $results): \Illuminate\Database\Eloquent\Collection
    {
        if (empty($this->expands) || $results->isEmpty()) {
            return $results;
        }

        $collection = $this->getCollection();
        $relationFields = $collection->fields()
            ->where('type', FieldType::Relation)
            ->whereIn('name', $this->expands)
            ->get()
            ->keyBy('name');

        foreach ($this->expands as $fieldName) {
            if (! $relationFields->has($fieldName)) {
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
            if (! $relatedCollection) {
                continue;
            }

            $expandedRecords = RecordQuery::for($relatedCollection)
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
                        ->map(fn ($id) => $expandedRecords->get($id))
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
}
