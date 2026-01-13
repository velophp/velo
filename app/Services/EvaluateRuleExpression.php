<?php

namespace App\Services;

use stdClass;
use Illuminate\Support\Collection;
use App\Exceptions\InvalidRuleException;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class EvaluateRuleExpression
{
    protected ?string $expression;
    protected $context = [];

    public function __construct(
        protected ExpressionLanguage $expressionLanguage
    ) {
    }

    public function forExpression(string $expression)
    {
        $this->expression = $expression;
        return $this;
    }

    public function withContext($context)
    {
        $this->context = $context;
        return $this;
    }

    public function normalize(): string
    {
        if ($this->expression === null)
            throw new InvalidRuleException('Cannot normalize a NULL expression.');

        $normalized = str_replace(['@', '='], ['sys_', '=='], $this->expression);
        // Replaces '.' with '?.' only when followed by an alphanumeric character
        $normalized = preg_replace('/(?<!\?)\.(?=[a-zA-Z_])/', '?.', $normalized);

        return $normalized;
    }

    /**
     * Returns the evaluated result of an expression
     * If the expression is '' an empty string the evaluating result will always be true
     * @throws InvalidRuleException
     * @return bool
     */
    public function evaluate(): bool
    {
        if ($this->expression === null)
            throw new InvalidRuleException('Cannot evaluate a NULL expression.');

        $expression = $this->normalize();

        if (empty(trim($expression)))
            return true;
        if (str_contains($expression, 'SUPERUSER_ONLY'))
            return false;

        return (bool) $this->expressionLanguage->evaluate($expression, $this->context);
    }
}
