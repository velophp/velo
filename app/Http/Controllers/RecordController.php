<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordRequest;
use App\Http\Resources\RecordResource;
use App\Models\Collection;
use App\Services\EvaluateRuleExpression;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

class RecordController extends Controller
{
    public function list(RecordRequest $request, Collection $collection): JsonResponse
    {
        $perPage = $request->input('per_page', 100);
        $page = $request->input('page', 1);
        $filter = $request->input('filter', '');
        $sort = $request->input('sort', '');
        $expand = $request->input('expand', '');

        // Apply list API rule as additional filter (interpolate @variables with actual values)
        $listRule = $collection->api_rules['list'] ?? '';
        if (! empty($listRule)) {
            $context = [
                'sys_request' => (object) [
                    'auth' => $request->user(),
                ],
            ];
            $interpolatedRule = app(EvaluateRuleExpression::class)
                ->forExpression($listRule)
                ->withContext($context)
                ->interpolate();

            $filter = empty($filter) ? $interpolatedRule : "($filter) AND ($interpolatedRule)";
        }

        $records = $collection->records()
            ->filterFromString($filter ?? '')
            ->sortFromString($sort ?? '')
            ->expandFromString($expand ?? '')
            ->simplePaginate($perPage, $page);

        return RecordResource::collection($records)->response();
    }

    public function view(RecordRequest $request, Collection $collection, string $recordId): JsonResponse
    {
        $expand = $request->input('expand', '');

        $record = $collection->records()
            ->filter('id', '=', $recordId)
            ->expandFromString($expand)
            ->firstOrFail();

        $resource = new RecordResource($record);

        return $resource->response();
    }

    public function create(RecordRequest $request, Collection $collection)
    {
        $record = $collection->recordRelation()->create(['data' => $request->validated()]);
        $resource = new RecordResource($record);

        return $resource->response();
    }

    public function update(RecordRequest $request, Collection $collection, string $recordId): JsonResponse
    {
        $record = $collection->records()->filter('id', '=', $recordId)->firstRawOrFail();

        $record->update([
            'data' => [...$record->data->toArray(), ...$request->validated()],
        ]);

        $resource = new RecordResource($record);

        return $resource->response();
    }

    public function delete(RecordRequest $request, Collection $collection, string $recordId): JsonResponse
    {
        $record = $collection->records()->filter('id', '=', $recordId)->firstRawOrFail();
        $record->delete();

        return Response::json([], 204);
    }
}
