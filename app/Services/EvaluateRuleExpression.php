<?php

namespace App\Services;

use App\Exceptions\InvalidRuleException;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class EvaluateRuleExpression
{
    protected ?string $expression;

    protected $context = [];

    public function __construct(
        protected ExpressionLanguage $expressionLanguage,
    ) {}

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
        if ($this->expression === null) {
            throw new InvalidRuleException('Cannot normalize a NULL expression.');
        }

        $normalized = str_replace(['@', '='], ['sys_', '=='], $this->expression);
        // Replaces '.' with '?.' only when followed by an alphanumeric character
        $normalized = preg_replace('/(?<!\?)\.(?=[a-zA-Z_])/', '?.', $normalized);

        return $normalized;
    }

    /**
     * Interpolates @variable references with actual values from context.
     * Useful for converting rules like "@request.auth.id = id" to "id = \"abc123\""
     * for use in filter strings.
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
            $parts = explode('.', $path);

            // Navigate through context using dot notation
            $value = $this->context['sys_'.$parts[0]] ?? null;
            for ($i = 1; $i < count($parts); $i++) {
                if ($value === null) {
                    break;
                }
                if (is_object($value)) {
                    $value = $value->{$parts[$i]} ?? null;
                } elseif (is_array($value)) {
                    $value = $value[$parts[$i]] ?? null;
                } else {
                    $value = null;
                }
            }

            // Return quoted string or empty string if null
            if ($value === null) {
                return '""';
            }

            return '"'.str_replace('"', '\\"', (string) $value).'"';
        }, $result);

        // Flip expressions where quoted value is on the left side (e.g., "val" = field -> field = "val")
        // RecordQueryCompiler expects: field operator value
        $result = preg_replace_callback(
            '/("(?:[^"\\\\]|\\\\.)*")\s*(=|!=|>=|<=|>|<|LIKE)\s*([a-zA-Z_][a-zA-Z0-9_]*)/i',
            function ($matches) {
                $value = $matches[1];
                $operator = $matches[2];
                $field = $matches[3];

                return "$field $operator $value";
            },
            $result
        );

        return $result;
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

        return (bool) $this->expressionLanguage->evaluate($expression, $this->context);
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
            return $this->withContext([
                'sys_request' => (object) ['auth' => null],
            ])->evaluate();
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function contextFrom($request): array
    {
        return [
            'sys_request' => (object) [
                'auth' => $request->user(),
                'body' => $request->post(),
                'param' => $request->route()->parameters(),
                'query' => $request->query(),
            ],
        ];
    }
}
