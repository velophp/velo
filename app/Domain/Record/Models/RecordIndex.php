<?php

namespace App\Domain\Record\Models;

use App\Domain\Collection\Models\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecordIndex extends Model
{
    protected $table = 'record_indexes';

    protected $fillable = [
        'collection_id',
        'record_id',
        'field',
        'value_string',
        'value_number',
        'value_datetime',
    ];

    protected function casts(): array
    {
        return [
            'value_datetime' => 'datetime',
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }
}
