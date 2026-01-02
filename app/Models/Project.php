<?php

namespace App\Models;

use App\Models\Collection;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $table = 'projects';

    protected $fillable = ['name'];

    public function collections() {
        return $this->hasMany(Collection::class);
    }
}
