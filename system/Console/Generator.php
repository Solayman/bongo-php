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

    public function store(Request \$request)
    {
        \$data = \$request->validate([
            // 'name' => 'required|string|max:100',
        ]);

        return ['data' => \$data];
    }
PHP
            : <<<PHP
    public function index(Request \$request)
    {
        return \$this->view('{$this->viewName($class)}.index', []);
    }

    public function store(Request \$request)
    {
        \$data = \$request->validate([
            // 'name' => 'required|string|max:100',
        ]);

        // Model::create(\$data);
        redirect(url('/'));
    }
PHP;

        $use .= "use System\\Http\\Request;\n";
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

    /** make:request StoreProductRequest → app/Http/Requests/StoreProductRequest.php */
    public function request(string $name): string
    {
        [$namespace, $class, $path] = $this->locate($name, 'App\\Http\\Requests', 'app/Http/Requests', 'Request');

        return $this->write($path, <<<PHP
<?php
namespace $namespace;

use System\\Http\\FormRequest;

/**
 * Type-hint it in an action: public function store($class \$request) { \$request->validated(); }
 */
class $class extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // e.g. return auth()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            // 'name' => 'required|string|max:100',
        ];
    }
}

PHP);
    }

    /** make:policy ProductPolicy → app/Policies/ProductPolicy.php (used for App\Models\Product automatically) */
    public function policy(string $name): string
    {
        [$namespace, $class, $path] = $this->locate($name, 'App\\Policies', 'app/Policies', 'Policy');
        $model = preg_replace('/Policy$/', '', $class);
        return $this->write($path, <<<PHP
<?php
namespace $namespace;

use App\\Models\\$model;

/**
 * Who may do what with a $model. Found automatically for App\\Models\\$model:
 * \$this->authorize('update', \$item) in controllers, @can('update', \$item) in views.
 */
class $class
{
    public function update(object \$user, $model \$item): bool
    {
        return \$user->role === 'admin';
    }

    public function delete(object \$user, $model \$item): bool
    {
        return \$user->role === 'admin';
    }
}

PHP);
    }

    /** make:job SendInvoice → app/Jobs/SendInvoice.php */
    public function job(string $name): string
    {
        [$namespace, $class, $path] = $this->locate($name, 'App\\Jobs', 'app/Jobs');
        return $this->write($path, <<<PHP
<?php
namespace $namespace;

use System\\Queue\\Job;

/**
 * Runs in the background: dispatch(new $class(...)), worked off by php bongo queue:work
 * (QUEUE_DRIVER=database) or right away (QUEUE_DRIVER=sync).
 */
class $class extends Job
{
    public int \$tries = 3;           // attempts before it counts as failed
    public int|array \$backoff = 60;  // seconds between attempts

    public function __construct(public int \$id) {}

    public function handle(): void
    {
        // the work
    }
}

PHP);
    }

    /** make:component Alert → resources/views/components/alert.php, used as <x-alert> */
    public function component(string $name): string
    {
        $file = strtolower(preg_replace('/(?<!^|\/)[A-Z]/', '-$0', str_replace('\\', '/', $name)));
        $tag = str_replace('/', '.', $file);
        return $this->write("resources/views/components/$file.php", <<<PHP
{{-- <x-$tag type="info">Content</x-$tag> · attributes become variables, the content is \$slot --}}
<div class="$tag {{ \$type ?? '' }}">
    {{ \$slot }}
</div>

PHP);
    }

    /** make:seeder ProductSeeder → database/seeders/ProductSeeder.php */
    public function seeder(string $name): string
    {
        [, $class, ] = $this->locate($name, 'Database\\Seeders', 'database/seeders', 'Seeder');
        return $this->write("database/seeders/$class.php", <<<PHP
<?php

namespace Database\\Seeders;

use System\\Database\\DB;

class $class
{
    /** Call it from DatabaseSeeder::run(): (new $class())->run(); */
    public function run(): void
    {
        // DB::table('products')->insert(['name' => 'Demo', 'sku' => 'DEMO-1', 'price' => 9.99, 'stock' => 10]);
    }
}

PHP);
    }

    /** make:notification OrderShipped → app/Notifications/OrderShipped.php */
    public function notification(string $name): string
    {
        [$namespace, $class, $path] = $this->locate($name, 'App\\Notifications', 'app/Notifications');
        return $this->write($path, <<<PHP
<?php
namespace $namespace;

use System\\Mail\\Mail;
use System\\Mail\\Message;
use System\\Notifications\\Notification;

/**
 * Notify::send(\$user, new $class(...));
 */
class $class extends Notification
{
    public function via(object \$user): array
    {
        return ['mail'];   // also 'sms', 'slack'
    }

    public function toMail(object \$user): Message
    {
        return Mail::message()->subject('$class')->text('Hello ' . (\$user->name ?? '') . '!');
    }

    public function toSms(object \$user): string
    {
        return '$class';
    }
}

PHP);
    }

    /** make:mail order-shipped → resources/views/emails/order-shipped.php (an email template) */
    public function mail(string $name): string
    {
        $file = strtolower(trim(preg_replace('/(?<!^)[A-Z]/', '-$0', basename(str_replace('\\', '/', $name))), '-'));
        return $this->write("resources/views/emails/$file.php", <<<'HTML'
<!DOCTYPE html>
<html lang="{{ locale() }}">
<body style="margin:0;padding:24px;background:#f5f6f8;font-family:Arial,Helvetica,sans-serif;color:#111827;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
<table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;">
    <tr><td style="padding:28px;">
        <h1 style="font-size:20px;margin:0 0 16px;">{{ config('app.name') }}</h1>
        <p style="margin:0 0 12px;">Hello {{ $user->name ?? '' }},</p>
        <p style="margin:0;">Your message here.</p>
    </td></tr>
</table>
</td></tr></table>
</body>
</html>

HTML) . " (send it: Mail::to(\$email)->subject('...')->view('emails.$file', ['user' => \$user])->send())";
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

    /** make:command SendWeeklyReport → app/Commands/SendWeeklyReport.php */
    public function command(string $name): string
    {
        [$namespace, $class, $path] = $this->locate($name, 'App\\Commands', 'app/Commands');
        $cmdName = strtolower(preg_replace('/(?<!^)[A-Z]/', ':$0', preg_replace('/Command$/', '', $class)));

        $code = <<<PHP
<?php
namespace $namespace;

use System\Console\Command;

class $class extends Command
{
    protected string \$name = '$cmdName';
    protected string \$description = 'Command description for $cmdName';

    public function handle(array \$args, array \$options): int
    {
        \$this->info('Executing $cmdName...');
        return 0;
    }
}

PHP;
        return $this->write($path, $code);
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
