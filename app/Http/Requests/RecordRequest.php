<?php

namespace App\Http\Requests;

use App\Helper;
use App\Services\EvaluateRuleExpression;
use App\Services\IndexStrategies\MysqlIndexStrategy;
use App\Services\RecordRulesCompiler;
use Illuminate\Foundation\Http\FormRequest;

class RecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $collection = $this->route()->parameter('collection');
        $rules = $collection->api_rules;

        $operation = $this->route()->getActionMethod();

        if (! isset($rules[$operation])) {
            return false;
        }

        // For list operation, the rule is applied as a filter in the controller
        // Authorization passes if a rule is defined (even if empty = allow all)
        if ($operation === 'list') {
            return true;
        }

        $recordId = $this->route()->parameter('recordId');
        $record = null;

        if ($recordId) {
            $record = $collection->records()
                ->filter('id', '=', $recordId)
                ->first();
        }

        $fields = $collection->fields->pluck('name')->toArray();
        $recordData = $record ? $record->data->toArray() : array_fill_keys($fields, null);

        $context = [
            'sys_request' => Helper::toObject([
                'auth' => $this->user(),
                'body' => $this->post(),
                'param' => $this->route()->parameters(),
                'query' => $this->query(),
            ]),
            ...$recordData,
            ...$this->only($fields),
        ];

        $rule = app(EvaluateRuleExpression::class)->forExpression($rules[$operation])->withContext($context);

        return $rule->evaluate();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if (\in_array($this->route()->getActionMethod(), ['list', 'view', 'delete'])) {
            return [];
        }

        $rules = app(RecordRulesCompiler::class)
            ->forCollection($this->route()->parameter('collection'))
            ->using(new MysqlIndexStrategy)
            ->withForm($this->all())
            ->ignoreId($this->route()->parameter('recordId'))
            ->compile();

        return $rules;
    }

    public function attributes(): array
    {
        $attributes = [];
        $rules = $this->validationRules();

        foreach ($rules as $ruleName => $rule) {
            if (str_ends_with($ruleName, '.*')) {
                $index = \Str::between($ruleName, 'fields.', '.options');
                $attributes[$ruleName] = "value on [{$index}]";

                continue;
            }

            $newName = explode('.', $ruleName);
            $newName = end($newName);
            $attributes[$ruleName] = \Str::lower(\Str::headline($newName));
        }

        return $attributes;
    }
}
