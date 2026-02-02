<?php

namespace App\Domain\Auth\Models;

use App\Domain\Auth\Enums\OtpType;
use App\Domain\Collection\Models\Collection;
use App\Domain\Project\Models\Project;
use App\Domain\Record\Models\Record;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthOtp extends Model
{
    protected $fillable = [
        'project_id',
        'collection_id',
        'record_id',
        'token_hash',
        'action',
        'expires_at',
        'used_at',
        'last_used_at',
        'ip_address',
        'device_name',
    ];

    protected $casts = [
        'expires_at'   => 'datetime',
        'used_at'      => 'datetime',
        'last_used_at' => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
        'action'       => OtpType::class,
    ];

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
}
