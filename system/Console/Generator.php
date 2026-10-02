<?php
namespace System\Console;

use RuntimeException;

/**
 * Writes starter files for `php bongo make:*`. Never overwrites an existing file.
 */
class Generator
{
    public function __construct(protected string $basePath) {}

    /** make:controller ProductController [--api] → app/Http/Controllers/ProductController.php */
    public function controller(string $name, bool $api = false): string
    {
        [$namespace, $class, $path] = $this->locate($name, 'App\\Http\\Controllers', 'app/Http/Controllers', 'Controller');
        $use = $namespace === 'App\\Http\\Controllers' ? '' : "use App\\Http\\Controllers\\Controller;\n";

        $body = $api
            ? <<<PHP
    public function index()
    {
        return ['data' => []];
    }

    public function show(\$id)
    {
        return ['data' => ['id' => (int) \$id]];
    }

    public function store()
    {
        \$data = \$this->validate([
            // 'name' => 'required|string|max:100',
        ]);

        return ['data' => \$data];
    }
PHP
            : <<<PHP
    public function index()
    {
        echo \$this->view('{$this->viewName($class)}.index', []);
    }
PHP;

        return $this->write($path, "<?php\nnamespace $namespace;\n\n$use\nclass $class extends Controller\n{\n$body\n}\n");
    }

    /** make:model Product → app/Models/Product.php */
    public function model(string $name): string
    {
        [$namespace, $class, $path] = $this->locate($name, 'App\\Models', 'app/Models');
        $table = $this->tableName($class);

        return $this->write($path, <<<PHP
<?php

namespace $namespace;

use System\Database\Model;

class $class extends Model
{
    protected static \$table = '$table';

    // Columns create()/fill() may set
    protected array \$fillable = [];

    // Columns left out of toArray()/JSON
    protected array \$hidden = [];

    // column => int|float|bool|string|array
    protected array \$casts = [];
}

PHP);
    }

    /** make:middleware EnsureIsAdmin → app/Http/Middleware/EnsureIsAdmin.php */
    public function middleware(string $name): string
    {
        [$namespace, $class, $path] = $this->locate($name, 'App\\Http\\Middleware', 'app/Http/Middleware');

        return $this->write($path, <<<PHP
<?php
namespace $namespace;

/**
 * Register in config/app.php → 'middleware' => ['alias' => \\$namespace\\$class::class]
 * and use it on routes: ->middleware('alias') or 'alias:param'
 */
class $class
{
    public function handle(string ...\$params): void
    {
        // Return to continue, or stop the request:
        // abort(403);  redirect(url('/login'));  response(['error' => '...'], 'json', 403);
    }
}

PHP);
    }

    /** make:test ProductApiTest [--unit] → tests/Feature/ProductApiTest.php */
    public function test(string $name, bool $unit = false): string
    {
        $class = $this->studly(basename(str_replace('\\', '/', $name)));
        if (!str_ends_with($class, 'Test')) $class .= 'Test';
        $dir = $unit ? 'Unit' : 'Feature';
        $base = $unit ? 'TestCase' : 'HttpTestCase';
        $body = $unit
            ? "    public function testExample(): void\n    {\n        \$this->assertSame(2, 1 + 1);\n    }"
            : "    public function testHomePageRedirectsGuests(): void\n    {\n        \$this->get('/')->assertRedirect('/login');\n    }";

        return $this->write("tests/$dir/$class.php", "<?php\nnamespace Tests\\$dir;\n\nuse System\\Testing\\$base;\n\nclass $class extends $base\n{\n$body\n}\n");
    }

    // ------------------------------------------------------------------

    /** "Admin/ReportController" → [namespace, class, relative path] */
    protected function locate(string $name, string $baseNamespace, string $baseDir, string $suffix = ''): array
    {
        $name = trim(str_replace('\\', '/', $name), '/');
        if (!preg_match('#^[A-Za-z][A-Za-z0-9_]*(/[A-Za-z][A-Za-z0-9_]*)*$#', $name)) {
            throw new RuntimeException("Invalid name [$name]: use letters, digits and / for sub-folders (e.g. Admin/ReportController)");
        }
        $parts = array_map([$this, 'studly'], explode('/', $name));
        $class = array_pop($parts);
        if ($suffix && !str_ends_with($class, $suffix)) {
            $class .= $suffix;
        }
        $namespace = $baseNamespace . ($parts ? '\\' . implode('\\', $parts) : '');
        $path = $baseDir . '/' . ($parts ? implode('/', $parts) . '/' : '') . $class . '.php';
        return [$namespace, $class, $path];
    }

    protected function write(string $relative, string $contents): string
    {
        $file = $this->basePath . '/' . $relative;
        if (file_exists($file)) {
            throw new RuntimeException("$relative already exists");
        }
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }
        file_put_contents($file, $contents);
        return $relative;
    }

    protected function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    /** ProductController → "products", OrderItem → "order_items" */
    protected function viewName(string $class): string
    {
        return $this->tableName(preg_replace('/Controller$/', '', $class));
    }

    public function tableName(string $class): string
    {
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $class));
        return str_ends_with($snake, 'y') && !preg_match('/[aeiou]y$/', $snake) ? substr($snake, 0, -1) . 'ies'
            : (preg_match('/(s|x|z|ch|sh)$/', $snake) ? $snake . 'es' : $snake . 's');
    }
}
