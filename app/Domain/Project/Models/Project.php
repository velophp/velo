<?php

namespace App\Domain\Project\Models;

use App\Domain\Collection\Models\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $table = 'projects';

    protected $fillable = ['name'];

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }
}
