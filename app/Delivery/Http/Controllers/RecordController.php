<?php

namespace App\Delivery\Http\Controllers;

use App\Delivery\Entity\FileObject;
use App\Delivery\Entity\SafeCollection;
use App\Delivery\Http\Requests\RecordRequest;
use App\Domain\Collection\Enums\CollectionType;
use App\Domain\Collection\Models\Collection;
use App\Domain\Field\Enums\FieldType;
use App\Domain\Project\Exceptions\InvalidRuleException;
use App\Domain\Record\Actions\ListRecords;
use App\Domain\Record\Authorization\RuleContext;
use App\Domain\Record\Resources\RecordResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Response;

class RecordController extends Controller
{
    /**
     * @throws InvalidRuleException
     */
    public function list(RecordRequest $request, Collection $collection): JsonResponse
    {
        $perPage = $request->input('per_page', 100);
        $page = $request->input('page', 1);
        $filter = $request->input('filter', '');
        $sort = $request->input('sort', '');
        $expand = $request->input('expand', '');

        $context = RuleContext::fromRequest($request);

        $resources = app(ListRecords::class)->execute(
            $collection,
            $perPage,
            $page,
            $filter,
            $sort,
            $expand,
            $context,
        );

        return $this->success($resources);
    }

    public function view(RecordRequest $request, Collection $collection, string $recordId): JsonResponse
    {
        $expand = $request->input('expand', '');

        $record = $collection->records()
            ->filter('id', '=', $recordId)
            ->expandFromString($expand)
            ->firstOrFail();

        $record->setRelation('collection', $collection);

        $resource = new RecordResource($record);

        return $resource->response();
    }

    public function create(RecordRequest $request, Collection $collection)
    {
        $data = new SafeCollection($request->validated());
        $fields = $collection->fields;

        $fileFields = $fields->filter(fn ($field) => $field->type === FieldType::File);
        $fileUploadService = app(\App\Delivery\Services\HandleFileUpload::class)->forCollection($collection);

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
                'message' => 'Use request update email endpoint for updating email',
            ], 400);
        }

        if ($collection->type === CollectionType::Auth && array_key_exists('password', $request->validated())) {
            return response()->json([
                'message' => 'Use reset password endpoint for updating password',
            ], 400);
        }

        $data = new SafeCollection($request->validated());
        $record = $collection->records()->filter('id', '=', $recordId)->firstRawOrFail();
        $fields = $collection->fields;

        $fileFields = $fields->filter(fn ($field) => $field->type === FieldType::File);
        $fileUploadService = app(\App\Delivery\Services\HandleFileUpload::class)->forCollection($collection);

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
