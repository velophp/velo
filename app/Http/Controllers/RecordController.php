<?php

namespace App\Http\Controllers;

use App\Entity\FileObject;
use App\Entity\SafeCollection;
use App\Enums\CollectionType;
use App\Enums\FieldType;
use App\Http\Requests\RecordRequest;
use App\Http\Resources\RecordResource;
use App\Models\Collection;
use App\Services\EvaluateRuleExpression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
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
                    'body' => $request->post(),
                    'param' => $request->route()->parameters(),
                    'query' => $request->query(),
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
        $data = new SafeCollection($request->validated());
        $fields = $collection->fields;

        $fileFields = $fields->filter(fn ($field) => $field->type === FieldType::File);
        $fileUploadService = app(\App\Services\HandleFileUpload::class)->forCollection($collection);

        foreach ($fileFields as $field) {
            $value = $data->get($field->name);
            if (empty($value)) {
                continue;
            }

            $files = is_array($value) ? $value : [$value];
            $files = array_filter($files);
            $processedFiles = [];

            foreach ($files as $file) {
                if ($file instanceof FileObject || is_array($file) && isset($file['uuid'])) {
                    $processedFiles[] = $file;

                    continue;
                }

                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $fileUploadService->fromUpload($file);

                $processed = $fileUploadService->save();
                if ($processed) {
                    $processedFiles[] = $processed;
                }
            }

            $data->put($field->name, $processedFiles);
        }

        $record = $collection->recordRelation()->create(['data' => $data->toArray()]);
        $resource = new RecordResource($record);

        return $resource->response();
    }

    public function update(RecordRequest $request, Collection $collection, string $recordId): JsonResponse
    {
        if ($collection->type === CollectionType::Auth && array_key_exists('email', $request->validated())) {
            return response()->json([
                'message' => "Use request update email endpoint for updating email",
            ], 400);
        }

        if ($collection->type === CollectionType::Auth && array_key_exists('password', $request->validated())) {
            return response()->json([
                'message' => "Use reset password endpoint for updating password",
            ], 400);
        }

        $data = new SafeCollection($request->validated());
        $record = $collection->records()->filter('id', '=', $recordId)->firstRawOrFail();
        $fields = $collection->fields;

        $fileFields = $fields->filter(fn ($field) => $field->type === FieldType::File);
        $fileUploadService = app(\App\Services\HandleFileUpload::class)->forCollection($collection);

        foreach ($fileFields as $field) {
            $value = $data->get($field->name);
            if (empty($value)) {
                continue;
            }

            $files = is_array($value) ? $value : [$value];
            $files = array_filter($files);
            $processedFiles = [];

            foreach ($files as $file) {
                if ($file instanceof FileObject || is_array($file) && isset($file['uuid'])) {
                    $processedFiles[] = $file;

                    continue;
                }

                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $fileUploadService->fromUpload($file);

                $processed = $fileUploadService->save();
                if ($processed) {
                    $processedFiles[] = $processed;
                }
            }

            $data->put($field->name, $processedFiles);
        }

        $record->update([
            'data' => [...$record->data->toArray(), ...$data->toArray()],
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
