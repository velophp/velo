<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Collection;

class RecordQueryCompiler
{
    protected Collection $collection;
    protected $filters = [];
    protected $sorts = [];

    public function __construct(Collection $collection)
    {
        $this->collection = $collection;
    }

    public function filter(string $field, $value, string $operator = '=')
    {
        $this->filters[] = compact('field', 'value', 'operator');
        return $this;
    }

    public function sort(string $field, string $direction = 'asc')
    {
        $this->sorts[] = compact('field', 'direction');
        return $this;
    }

    public function get()
    {
        $query = DB::table('records')->where('collection_id', $this->collection?->id);

        // Manual JSON extraction for using mysql
        foreach ($this->filters as $f) {
            $query->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(data, '$.\"{$f['field']}\"')) {$f['operator']} ?",
                [$f['value']]
            );
        }

        foreach ($this->sorts as $s) {
            $query->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.\"{$s['field']}\"')) {$s['direction']}");
        }

        return $query->get();
    }
}