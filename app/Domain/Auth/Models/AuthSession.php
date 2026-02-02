<?php

namespace App\Domain\Auth\Models;

use App\Domain\Collection\Models\Collection;
use App\Domain\Project\Models\Project;
use App\Domain\Record\Models\Record;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthSession extends Model
{
    protected $fillable = ['project_id', 'collection_id', 'record_id', 'token_hash', 'expires_at', 'last_used_at', 'device_name', 'ip_address'];

    protected function casts(): array
    {
        return [
            'expires_at'   => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }

    public static function generateToken(): array
    {
        $plainToken = \Str::random(64);
        $hashed = hash('sha256', $plainToken);

        return [$plainToken, $hashed];
    }
}
