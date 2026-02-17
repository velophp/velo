<?php

namespace App\Delivery\Services;

use App\Domain\Project\Exceptions\InvalidRuleException;
use App\Domain\Record\Authorization\RuleContext;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class EvaluateRuleExpression
{
    protected ?string $expression;

    protected RuleContext $context;

    public function __construct(
        protected ExpressionLanguage $expressionLanguage,
    ) {
        $this->context = RuleContext::empty();
    }

    public function forExpression(string $expression): static
    {
        $this->expression = $expression;

        return $this;
    }

    public function withContext(RuleContext $context): static
    {
        $this->context = $context;

        return $this;
    }

    /**
     * @throws InvalidRuleException
     */
    public function normalize(): string
    {
        if ($this->expression === null) {
            throw new InvalidRuleException('Cannot normalize a NULL expression.');
        }

        $normalized = str_replace(['@', '='], ['sys_', '=='], $this->expression);
        // Replaces '.' with '?.' only when followed by an alphanumeric character
        return preg_replace('/(?<!\?)\.(?=[a-zA-Z_])/', '?.', $normalized);
    }

    /**
     * Interpolates @variable references with actual values from context.
     * Useful for converting rules like "@request.auth.id = id" to "id = \"abc123\""
     * for use in filter strings.
     * @throws InvalidRuleException
     */
    public function interpolate(): string
    {
        if ($this->expression === null) {
            throw new InvalidRuleException('Cannot interpolate a NULL expression.');
        }

        $result = $this->expression;

        // Find all @variable.path patterns and replace with actual values
        $result = preg_replace_callback('/@([a-zA-Z_][a-zA-Z0-9_\.]*)/u', function ($matches) {
            $path = $matches[1];

            if (str_starts_with($path, 'request.')) {
                $path = 'sys_' . $path;
            }

            $value = data_get($this->context->toArray(), $path);

            if ($value === null) {
                return '""';
            }

            return '"' . str_replace('"', '\"', (string) $value) . '"';
        }, $result);

        // Flip expressions where quoted value is on the left side (e.g., "val" = field -> field = "val")
        // RecordQueryCompiler expects: field operator value
        return preg_replace_callback(
            '/("(?:[^"\\\]|\\\.)*")\s*(=|!=|>=|<=|>|<|LIKE)\s*([a-zA-Z_][a-zA-Z0-9_]*)/i',
            function ($matches) {
                $value = $matches[1];
                $operator = $matches[2];
                $field = $matches[3];

                return "$field $operator $value";
            },
            $result
        );
    }

    /**
     * Returns the evaluated result of an expression
     * If the expression is '' an empty string the evaluating result will always be true.
     *
     * @throws InvalidRuleException
     */
    public function evaluate(): bool
    {
        if ($this->expression === null) {
            throw new InvalidRuleException('Cannot evaluate a NULL expression.');
        }

        $expression = $this->normalize();

        if (empty(trim($expression))) {
            return true;
        }
        if (str_contains($expression, 'SUPERUSER_ONLY')) {
            return false;
        }

        return (bool) $this->expressionLanguage->evaluate($expression, $this->context->toArray());
    }

    /**
     * Check if the rule allows guest (unauthenticated) access.
     * Fast path: if rule does not reference @request.auth, guests can't access.
     */
    public function allowsGuest(): bool
    {
        $expression = $this->expression ?? '';

        if (! str_contains($expression, '@request.auth')) {
            return true;
        }

        try {
            return $this->withContext(RuleContext::empty())->evaluate();
        } catch (\Exception $e) {
            return false;
        }
    }
}
