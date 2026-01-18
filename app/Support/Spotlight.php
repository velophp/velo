<?php

namespace App\Support;

use App\Enums\CollectionType;
use App\Models\Collection;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;

class Spotlight
{
    public function search(Request $request): array|\Illuminate\Support\Collection
    {
        if (! auth()->user()) {
            return [];
        }

        $param = trim($request->input('search'));

        return collect()
            ->merge($this->collections($param));
    }

    private function collections(string $search): \Illuminate\Support\Collection
    {
        $systemCollections = collect([
            [
                'name' => 'superusers',
                'description' => 'System Collection',
                'link' => route('system.superusers'),
                'icon' => Blade::render("<x-icon name='o-users' />"),
            ],
            [
                'name' => 'authSessions',
                'description' => 'System Collection',
                'link' => route('system.sessions'),
                'icon' => Blade::render("<x-icon name='o-archive-box' />"),
            ],
            [
                'name' => 'passwordResets',
                'description' => 'System Collection',
                'link' => route('system.password.resets'),
                'icon' => Blade::render("<x-icon name='o-archive-box' />"),
            ],
            [
                'name' => 'otps',
                'description' => 'System Collection',
                'link' => route('system.otps'),
                'icon' => Blade::render("<x-icon name='o-archive-box' />"),
            ],
            [
                'name' => 'Logs',
                'description' => 'System Page',
                'link' => url(route('system.logs')),

                'icon' => Blade::render("<x-icon name='lucide.chart-line' />"),
            ],
        ])->filter(fn ($row) => str_contains(strtolower($row['name']), strtolower($search)))->values();

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
            });

        return collect()->merge($collections)->merge($systemCollections);
    }
}
