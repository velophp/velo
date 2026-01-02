<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection as DataCollection;
use App\Models\Collection;
use App\Models\Record;

class RecordQueryCompiler
{
    protected Collection $collection;
    protected $filters = [];
    protected $sorts = [];
    protected int $perPage = 15;
    protected ?int $page = null;

    public function __construct(Collection $collection)
    {
        $this->collection = $collection;
    }

    // Append new rule to the filters value
    public function filter(string $field, string $operator = '=', string $value)
    {
        $this->filters[] = compact('field', 'value', 'operator');
        return $this;
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
        // Check operators from longest to shortest to avoid partial matches
        $operators = ['>=', '<=', '!=', '<>', '=', '>', '<', 'LIKE', 'like'];

        foreach ($operators as $op) {
            // Build regex pattern to find the operator
            $pattern = in_array($op, ['LIKE', 'like'])
                ? '/\s+' . preg_quote($op, '/') . '\s+/i'
                : '/' . preg_quote($op, '/') . '/';

            // Check if this operator exists in the segment
            if (preg_match($pattern, $segment, $matches, PREG_OFFSET_CAPTURE)) {
                $operatorPosition = $matches[0][1];
                $operatorLength = strlen($matches[0][0]);

                // Split segment into field and value parts
                $field = trim(substr($segment, 0, $operatorPosition));
                $value = trim(substr($segment, $operatorPosition + $operatorLength));

                // Remove surrounding quotes from value if present
                $value = $this->removeQuotes($value);

                return [
                    'field' => $field,
                    'operator' => strtoupper($op),
                    'value' => $value,
                ];
            }
        }

        return null;
    }

    protected function removeQuotes(string $value): string
    {
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    public function sort(string $field, string $direction = 'asc')
    {
        $this->sorts[] = compact('field', 'direction');
        return $this;
    }

    protected function buildQuery($baseQuery = null, $select = ['data'])
    {
        $query = ($baseQuery ?? DB::table('records')
            ->select($select))
            ->where('collection_id', $this->collection?->id);

        // Manual JSON extraction for using mysql, enclosed to avoid bypassing collection_id where clause
        $query->where(function ($q) {
            foreach ($this->filters as $f) {
                $rawSql = "JSON_UNQUOTE(JSON_EXTRACT(data, '$.\"{$f['field']}\"')) {$f['operator']} ?";

                if (isset($f['logical']) && strtoupper($f['logical']) === 'OR') {
                    $q->orWhereRaw($rawSql, [$f['value']]);
                } else {
                    $q->whereRaw($rawSql, [$f['value']]);
                }
            }
        });

        foreach ($this->sorts as $s) {
            $query->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.\"{$s['field']}\"')) {$s['direction']}");
        }

        return $query;
    }

    public function casts(array|DataCollection $data): array|DataCollection
    {
        foreach ($data as $key => $value) {
            if ($key === 'created' || $key === 'updated') {
                $data[$key] = Carbon::parse($value)->format('Y-m-d H:i:s');
            }
        }

        return $data;
    }

    public function firstRaw(): Record|null
    {
        $result = $this->buildQuery(Record::query())->first();
        return $result;
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
            ]
        );
    }
}