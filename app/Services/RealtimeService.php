<?php

namespace App\Services;

use App\Entity\SafeCollection;
use App\Events\RealtimeMessage;
use App\Http\Resources\RecordResource;
use App\Models\RealtimeConnection;
use App\Models\Record;
use App\Services\EvaluateRuleExpression;

class RealtimeService
{
    public function __construct(
        protected FilterMatchingService $filterMatcher,
        protected EvaluateRuleExpression $ruleEvaluator
    ) {
    }

    public function dispatchUpdates(Record $record, string $action): void
    {
        $listRule = $record->collection->api_rules['list'] ?? '';
        
        $payload = [
            'action' => $action,
            'record' => (new RecordResource($record))->resolve()
        ];

        $requiresAuthContext = str_contains($listRule, '@request.auth');
        $staticInterpolatedRule = null;

        if (! $requiresAuthContext && ! empty($listRule) && $listRule !== 'SUPERUSER_ONLY') {
            $staticInterpolatedRule = $this->ruleEvaluator
                ->forExpression($listRule)
                ->withContext([])
                ->interpolate();
        }

        $query = RealtimeConnection::query()
            ->where('realtime_connections.collection_id', $record->collection_id)
            ->select(['channel_name', 'is_public', 'filter']);

        if (!empty($listRule) && $listRule !== 'SUPERUSER_ONLY') {
            $query->join('records', function ($join) {
                $join->on('records.id', '=', 'realtime_connections.record_id')
                     ->on('records.collection_id', '=', 'realtime_connections.collection_id');
            })->addSelect('records.data AS user_data');
        }

        $query->chunk(500, function ($connections) use ($record, $payload, $listRule, $requiresAuthContext, $staticInterpolatedRule) {
            foreach ($connections as $connection) {
                if (! $requiresAuthContext) {
                    $interpolatedRule = $staticInterpolatedRule;
                } else {
                    $interpolatedRule = $this->interpolateWithUser($connection->user_data, $listRule);
                }

                $combinedFilter = match (true) {
                    empty($connection->filter) => $interpolatedRule,
                    empty($interpolatedRule)   => $connection->filter,
                    default                    => "$connection->filter AND $interpolatedRule" // @TODO handle parentheses (...) AND (...) later
                };

                if ($this->filterMatcher->match($record, $combinedFilter)) {
                    RealtimeMessage::dispatch(
                        $connection->channel_name, 
                        $payload, 
                        $connection->is_public
                    );
                }
            }
        });
    }

    protected function interpolateWithUser(string $userJson, string $listRule): string
    {
        if (empty($listRule) || $listRule === 'SUPERUSER_ONLY') {
            return 'false';
        }

        return $this->ruleEvaluator
            ->forExpression($listRule)
            ->withContext([
                'sys_request' => (object) [
                    'auth' => new SafeCollection(json_decode($userJson, true)),
                    'body' => null,
                    'param' => null,
                    'query' => null,
                ],
            ])
            ->interpolate();
    }
}
