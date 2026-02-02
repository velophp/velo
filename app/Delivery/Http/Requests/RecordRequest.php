<?php

namespace App\Delivery\Http\Requests;

use App\Domain\Project\Exceptions\InvalidRuleException;
use App\Domain\Record\Authorization\RecordAuthorizer;
use App\Domain\Record\Services\RecordRulesCompiler;
use Illuminate\Foundation\Http\FormRequest;

class RecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @throws InvalidRuleException
     */
    public function authorize(): bool
    {
        return app(RecordAuthorizer::class)->authorize($this);
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

        $collection = $this->route()->parameter('collection');

        $rules = app(RecordRulesCompiler::class)
            ->forCollection($collection)
            ->withForm($this->all())
            ->ignoreId($this->route()->parameter('recordId'))
            ->nullableWhen(fn () => $this->route()->getActionMethod() == 'update')
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
