<?php

namespace App\Domain\Record\Authorization;

use App\Delivery\Services\EvaluateRuleExpression;
use App\Domain\Project\Exceptions\InvalidRuleException;
use Illuminate\Foundation\Http\FormRequest;

class RecordAuthorizer
{
    /**
     * @throws InvalidRuleException
     */
    public function authorize(FormRequest $request): bool
    {
        $collection = $request->route()->parameter('collection');
        $rules = $collection->api_rules;

        $operation = $request->route()->getActionMethod();

        if (!isset($rules[$operation])) {
            return false;
        }

        // For list operation, the rule is applied as a filter in the controller
        if ($operation === 'list') {
            return true;
        }

        $recordId = $request->route()->parameter('recordId');
        $record = null;

        if ($recordId) {
            $record = $collection->records()
                ->filter('id', '=', $recordId)
                ->first();
        }

        $fields = $collection->fields->pluck('name')->all();

        $recordData = collect($record?->data ?? array_fill_keys($fields, null))
            ->replace($request->only($fields))
            ->all();

        $context = RuleContext::fromRequest($request, $recordData);

        return app(EvaluateRuleExpression::class)->forExpression($rules[$operation])->withContext($context)->evaluate();
    }
}
