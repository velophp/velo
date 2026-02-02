<?php

namespace App\Delivery\Models;

use App\Domain\Collection\Models\Collection;
use App\Domain\Project\Models\Project;
use App\Domain\Record\Models\Record;
use Illuminate\Database\Eloquent\Model;

class RealtimeConnection extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'project_id',
        'collection_id',
        'record_id',
        'socket_id',
        'channel_name',
        'filter',
        'is_public',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'is_public'    => 'boolean',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }

    public function record()
    {
        return $this->belongsTo(Record::class);
    }

    public static function pruneStale()
    {
        $threshold = config('velo.realtime_connection_threshold') ?? 5;
        static::where('last_seen_at', '<', now()->subMinutes($threshold))->delete();
    }
}
