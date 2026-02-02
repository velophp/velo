<?php

namespace App\Delivery\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;

class ValidRuleExpression implements ValidationRule
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
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        try {
            if (! \is_string($value)) {
                $fail('Rule must be a string.');
            }

            $rawRule = trim($value);

            // Allow all
            if ($rawRule === '') {
                return;
            }

            if ($rawRule === 'SUPERUSER_ONLY') {
                return;
            }

            // Check these first before the operator check
            if (preg_match('/^[\d\.\-]+$/', $rawRule)) { // plain number like 0, 1, 123, -1.5
                $fail('Rule must be an expression with field and operator (e.g., "field = value").');

                return;
            }
            if (preg_match('/^"[^"]*"$/', $rawRule)) { // just a quoted string like "0", "foo"
                $fail('Rule must be an expression with field and operator (e.g., "field = value").');

                return;
            }
            if (preg_match('/^\'[^\']*\'$/', $rawRule)) { // just a single-quoted string
                $fail('Rule must be an expression with field and operator (e.g., "field = value").');

                return;
            }

            // Rule must contain at least one comparison operator
            if (! preg_match('/(=|!=|>=|<=|>|<|LIKE)/i', $rawRule)) {
                $fail('Rule must be an expression with field and operator (e.g., "field = value").');

                return;
            }

            $normalized = str_replace(['@', '='], ['sys_', '=='], $rawRule);
            $normalized = $this->addNullSafeOperators($normalized);
            $this->el->lint($normalized, $this->varNames);
        } catch (SyntaxError $e) {
            $msg = str_replace(['sys_', '=='], ['@', '='], $e->getMessage());
            $fail($msg);
        } catch (\Exception $e) {
            $fail('Something went wrong when parsing rule. Try again later.');
        }
    }

    private function addNullSafeOperators(string $expression): string
    {
        // Replaces '.' with '?.' only when followed by an alphanumeric character
        return preg_replace('/(?<!\?)\.(?=[a-zA-Z_])/', '?.', $expression);
    }
}
