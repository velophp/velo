<?php

namespace App\Rules;

use Closure;
use Exception;
use Illuminate\Contracts\Validation\ValidationRule;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\ExpressionLanguage\Parser;

class RuleExpression implements ValidationRule
{
    private $el;
    private array $varNames = [];

    public function __construct(?array $varNames)
    {
        $this->el = new ExpressionLanguage();
        $this->varNames = $varNames ?? [];
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            if (!\is_string($value)) $fail('Rule must be a string.');

            $rawRule = $value;
            $normalized = str_replace(['@', '='], ['sys_', '=='], $rawRule);
            $normalized = $this->addNullSafeOperators($normalized);
            $this->el->lint($normalized, $this->varNames);
        } catch (SyntaxError $e) {
            $msg = str_replace(['sys_', '=='], ['@', '='], $e->getMessage());
            $fail($msg);
        } catch (Exception $e) {
            $fail("Something went wrong when parsing rule. Try again later.");
        }
    }

    private function addNullSafeOperators(string $expression): string
    {
        // Replaces '.' with '?.' only when followed by an alphanumeric character
        return preg_replace('/(?<!\?)\.(?=[a-zA-Z_])/', '?.', $expression);
    }
}
