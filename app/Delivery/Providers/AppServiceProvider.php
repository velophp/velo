<?php

namespace App\Delivery\Providers;

use App\Delivery\Entity\SafeCollection;
use App\Delivery\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerCustomScopeDirective();

        \Gate::define('viewPulse', function (User $user) {
            return $user !== null;
        });

        SafeCollection::macro('recursive', function () {
            return $this->map(function ($value) {
                if (is_array($value)) {
                    return new SafeCollection($value);
                }

                return $value;
            });
        });
    }

    public function registerCustomScopeDirective(): void
    {
        /*
         * All credits from this blade directive goes to Konrad Kalemba.
         * Just copied and modified for my very specific use case.
         *
         * https://github.com/konradkalemba/blade-components-scoped-slots
         *
         * Original code snippet from maryUI @scope directive, modified to allow passing evaluated value
         */
        Blade::directive('cscope', function ($expression) {
            // Split the expression by top-level commas (not in parentheses)
            $directiveArguments = preg_split("/,(?![^\\(\\(]*[\\)\\)])/", $expression);
            $directiveArguments = array_map('trim', $directiveArguments);
            $name = $directiveArguments[0];
            $functionArguments = $directiveArguments[1] ?? '';

            // Build function "uses" to inject extra external variables
            $uses = array_slice($directiveArguments, 2);
            array_push($uses, '$__env');
            array_push($uses, '$__bladeCompiler');
            $uses = implode(',', $uses);

            /**
             * Handle dynamic slot names with concatenation.
             *
             * If the name contains concatenation (.), we evaluate it as PHP expression
             * Otherwise, treat it as a static string
             *
             * Examples:
             * - @cscope('cell_' . $var, $item) -> evaluates the concatenation
             * - @cscope('static_name', $item) -> uses as static string
             */
            $slotName = trim($name, "'\"");

            // Check if this is a concatenated expression (contains . outside quotes)
            if (preg_match('/[\.\$]/', $name)) {
                // It's an expression, use it directly (already has proper syntax)
                $evaluatedName = $name;
            } else {
                // It's a simple string, wrap in quotes
                $evaluatedName = "'{$slotName}'";
            }

            /**
             * Convert dots in final evaluated names to triple underscores
             * This happens at runtime in the generated PHP code.
             *
             * Example: 'user.city' becomes 'user___city'
             * Later, on component it will be replaced back.
             */
            $nameProcessing = "str_replace('.', '___', {$evaluatedName})";

            return "<?php \$__bladeCompiler = \$__bladeCompiler ?? null; \$loop = null; \$__env->slot({$nameProcessing}, function({$functionArguments}) use ({$uses}) { \$loop = (object) (\$__env->getLoopStack()[0] ?? []); ?>";
        });

        Blade::directive('endcscope', function () {
            return '<?php }); ?>';
        });
    }
}
