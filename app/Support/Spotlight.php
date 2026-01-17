<?php

namespace App\Support;

use App\Enums\CollectionType;
use App\Models\Collection;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;

class Spotlight
{
    public function search(Request $request)
    {
        if (!auth()->user()) {
            return [];
        }

        $param = trim($request->input('search'));

        return collect()
            ->merge($this->collections($param));
    }

    private function collections(string $search)
    {
        $collections = Collection::where('project_id', Project::first()->id)
            ->where('name', 'like', "%$search%")
            ->take(5)
            ->get()
            ->map(function (Collection $col) {
                $icon = match ($col->type) {
                    CollectionType::Auth => 'o-users',
                    CollectionType::View => 'o-table-cells',
                    default => 'o-archive-box',
                };

                return [
                    'name' => $col->name,
                    'description' => 'Collection | Updated '.$col->updated_at->diffForHUmans(),
                    'link' => route('collections', ['collection' => $col]),
                    'icon' => Blade::render("<x-icon name='$icon' />"),
                ];
            })->push([
                'name' => 'superusers',
                'description' => 'System Collection',
                'link' => route('collections', ['collection' => 'superusers']),
                'icon' => Blade::render("<x-icon name='o-users' />"),
            ]);

        return $collections;
    }
}
