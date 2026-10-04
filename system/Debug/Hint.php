<?php

namespace System\Debug;

use Throwable;

/**
 * "How to fix" advice for common mistakes, shown on the error page and in the log.
 */
class Hint
{
    public static function for(Throwable $e): ?string
    {
        $message = $e->getMessage();

        // Missing tables while migrations are pending
        if ($hint = \System\Database\MigrationHint::for($e)) {
            return $hint;
        }

        // request->all() instead of $request->all(): PHP reads "request" as a constant
        if (preg_match('/^Undefined constant "(?:[\w\\\\]*\\\\)?([a-z_]\w*)"/', $message, $m)) {
            return "Did you mean \${$m[1]}? Variables start with \$: write \${$m[1]} instead of {$m[1]}.";
        }

        // $request->validate() on a variable that was never set
        if (preg_match('/^Undefined variable \$(\w+)/', $message, $m) && $m[1] === 'request') {
            return 'Add Request $request to the action\'s parameters: public function store(Request $request), with "use System\Http\Request;" at the top.';
        }

        // Category::all() without "use App\Models\Category;"
        if (preg_match('/^Class "([\w\\\\]+)" not found/', $message, $m)) {
            $short = substr(strrchr('\\' . $m[1], '\\'), 1);
            foreach (['App\\Models\\', 'App\\Services\\', 'System\\Http\\', 'System\\Database\\'] as $namespace) {
                if ($namespace . $short !== $m[1] && class_exists($namespace . $short)) {
                    return "Add \"use {$namespace}{$short};\" at the top of the file.";
                }
            }
        }

        // A typo in a method name
        if (preg_match('/^Call to undefined method ([\w\\\\]+)::(\w+)\(\)/', $message, $m) && class_exists($m[1])) {
            $best = null;
            $distance = 3;
            foreach (get_class_methods($m[1]) as $method) {
                $d = levenshtein(strtolower($m[2]), strtolower($method));
                if ($d < $distance) {
                    [$best, $distance] = [$method, $d];
                }
            }
            if ($best) {
                return "Did you mean {$best}()?";
            }
        }

        return null;
    }
}
