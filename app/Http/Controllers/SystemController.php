<?php
namespace App\Http\Controllers;

use System\Auth\Auth;
use System\Database\DB;
use System\Database\Migrator;
use System\Http\Request;
use System\Log\Log;
use System\Log\Logger;
use System\Security\Crypt;
use System\Security\Hash;
use System\Security\Key;

class SystemController extends Controller
{
    public function index()
    {
        $connection = config('database.connections.' . config('database.default'));

        $db = ['connected' => false, 'error' => null, 'version' => null, 'tables' => []];

        try {
            $db['version'] = DB::raw('SELECT VERSION() AS version')[0]['version'];
            $db['tables'] = DB::table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', $connection['database'])
                ->orderBy('TABLE_NAME')
                ->get();
            $db['connected'] = true;
        } catch (\Throwable $e) {
            $db['error'] = $e->getMessage();
        }

        $logLevel = Request::query('level');
        if (!isset(Logger::LEVELS[$logLevel ?? ''])) {
            $logLevel = null;
        }

        echo $this->view('system.index', [
            'connection' => $connection,
            'db'         => $db,
            'checks'     => [
                'Routing'      => true,
                'Environment'  => env('APP_ENV') !== null,
                'Config'       => config('app.name') !== null,
                'View engine'  => true,
                'Session'      => session_status() === PHP_SESSION_ACTIVE,
                'Database'     => $db['connected'],
                'Composer (optional)' => file_exists(base_path('vendor/autoload.php')),
            ],
            'security'   => $this->securityChecks(),
            'deployment' => \System\Security\SecurityCheck::run(),
            'queue'      => \System\Queue\Queue::stats() + ['driver' => \System\Queue\Queue::driver()],
            'tasks'      => array_map(fn($t) => [
                'name' => $t->getName(), 'cron' => $t->expression(),
                'next' => $t->nextRun(new \DateTimeImmutable())?->format('Y-m-d H:i'),
            ], \System\Scheduling\Schedule::load()->tasks()),
            'migrations' => $this->migrationStatus(),
            'logFile'    => $this->relativePath(Logger::instance()->file()),
            'logLevel'   => $logLevel,
            'logs'       => Logger::instance()->recent(50, $logLevel),
        ]);
    }

    /**
     * Writes one entry so a person can see logging working end to end
     */
    public function logTest()
    {
        Log::info('Test log entry written from the System status page by user {user_id}', ['user_id' => Auth::id()]);
        flash('success', 'A test entry was written to the log. It is at the top of "Recent log entries".');
        redirect(url('/system#logs'));
    }

    /**
     * Debug tools for a person to try (only while APP_DEBUG is on)
     */
    public function errorTest()
    {
        if (!config('app.debug')) {
            abort(404);
        }
        $order = ['id' => 42, 'total' => 99.5];
        throw new \RuntimeException('Test exception from the System status page (order ' . $order['id'] . ')');
    }

    public function dumpTest()
    {
        if (!config('app.debug')) {
            abort(404);
        }
        $user = clone Auth::user();
        unset($user->password);
        dd($user, ['request' => Request::all(), 'config.app' => config('app')], new \ArrayObject([1, 2, 3]));
    }

    /** Live checks a person can read: [label => [ok, detail]] */
    protected function securityChecks(): array
    {
        $checks = [];

        try {
            Key::get();
            $checks['APP_KEY is set'] = [true, 'Used to pepper passwords and to encrypt data'];
        } catch (\Throwable $e) {
            $checks['APP_KEY is set'] = [false, 'Run: php bongo key:generate'];
            return $checks;
        }

        $hash = Hash::make('check-password');
        $checks['Password hashing'] = [
            Hash::check('check-password', $hash) && !Hash::check('wrong', $hash) && !str_contains($hash, 'check-password'),
            'One-way ' . (str_starts_with($hash, '$argon2id$') ? 'Argon2id' : 'bcrypt') . ' hash; right password passes, wrong one fails',
        ];

        $encrypted = Crypt::encrypt('round-trip check');
        $checks['Encryption round trip'] = [
            Crypt::decrypt($encrypted) === 'round-trip check',
            'AES-256-GCM: encrypt then decrypt returns the original text',
        ];

        $checks['HTTPS'] = [
            Request::isSecure(),
            'Recommended in production (not required for local development)',
        ];

        return $checks;
    }

    protected function relativePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $base = str_replace('\\', '/', BASE_PATH) . '/';
        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }

    protected function migrationStatus(): array
    {
        try {
            return (new Migrator(base_path('database/migrations')))->status();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
