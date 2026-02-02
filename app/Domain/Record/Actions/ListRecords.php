<?php

namespace App\Domain\Record\Actions;

use App\Delivery\Services\EvaluateRuleExpression;
use App\Domain\Collection\Models\Collection;
use App\Domain\Project\Exceptions\InvalidRuleException;
use App\Domain\Record\Authorization\RuleContext;
use App\Domain\Record\Resources\RecordResource;

class ListRecords
{
    /**
     * @throws InvalidRuleException
     */
    public function execute(
        Collection $collection,
        int $perPage,
        int $page,
        ?string $filter,
        ?string $sort,
        ?string $expand,
        ?RuleContext $context,
    ): \Illuminate\Http\Resources\Json\AnonymousResourceCollection {
        // Apply list API rule as additional filter (interpolate @variables with actual values)
        $listRule = $collection->api_rules['list'];
        if ($listRule == 'SUPERUSER_ONLY') {
            $filter = '1!=1';
        } else {
            $interpolatedRule = app(EvaluateRuleExpression::class)
                ->forExpression($listRule)
                ->withContext($context ?? RuleContext::empty())
                ->interpolate();

            $filter = empty($filter) ? $interpolatedRule : "($filter) AND ($interpolatedRule)";
        }

        $records = $collection->records()
            ->filterFromString($filter ?? '')
            ->sortFromString($sort ?? '')
            ->expandFromString($expand ?? '')
            ->simplePaginate($perPage, $page);

        $records->getCollection()->each->setRelation('collection', $collection);

        return RecordResource::collection($records);
    }
}
