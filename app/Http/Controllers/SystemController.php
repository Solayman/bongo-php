<?php

namespace App\Http\Controllers;

use System\Auth\Auth;
use System\Database\DB;
use System\Database\Migrator;
use System\Database\Schema\Schema;
use System\Http\Request;
use System\Log\Log;
use System\Log\Logger;
use System\Security\Crypt;
use System\Security\Hash;
use System\Security\Key;

class SystemController extends Controller
{
    public function index(Request $request)
    {
        $connection = config('database.connections.' . config('database.default'));

        $sqlite = $connection['driver'] === 'sqlite';
        $db = [
            'connected' => false, 'error' => null, 'version' => null, 'tables' => [],
            'product'   => $sqlite ? 'SQLite' : 'MySQL',
            'server'    => $sqlite ? 'Local file' : "{$connection['username']}@{$connection['host']}:{$connection['port']}",
            'name'      => $sqlite ? basename($connection['database']) : $connection['database'],
        ];

        try {
            if ($sqlite) {
                $db['version'] = DB::raw('SELECT sqlite_version() AS version')[0]['version'];
                foreach (Schema::tables() as $name) {
                    $rows = DB::table($name)->count();
                    $db['tables'][] = (object) ['name' => $name, 'rows' => $rows, 'kb' => null, 'engine' => 'SQLite'];
                }
            } else {
                $db['version'] = DB::raw('SELECT VERSION() AS version')[0]['version'];
                foreach (DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', $connection['database'])->orderBy('TABLE_NAME')->get() as $t) {
                    $db['tables'][] = (object) [
                        'name' => $t->TABLE_NAME, 'rows' => (int) $t->TABLE_ROWS,
                        'kb' => ((int) $t->DATA_LENGTH + (int) $t->INDEX_LENGTH) / 1024, 'engine' => $t->ENGINE ?? '',
                    ];
                }
            }
            $db['connected'] = true;
        } catch (\Throwable $e) {
            $db['error'] = $e->getMessage();
        }

        $logLevel = $request->level;
        if (!isset(Logger::LEVELS[$logLevel ?? ''])) {
            $logLevel = null;
        }

        return $this->view('system.index', [
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
            'logFile'    => relative_path(Logger::instance()->file()),
            'logLevel'   => $logLevel,
            'logs'       => Logger::instance()->recent(50, $logLevel),
            'mails'      => \System\Mail\Mailbox::latest(20),
        ]);
    }

    /** One email from the mailbox: its details, the HTML in a sandboxed frame, the text version */
    public function mail(string $id)
    {
        $mail = \System\Mail\Mailbox::find($id) ?? abort(404, 'Email not found');
        return $this->view('system.mail', ['mail' => $mail]);
    }

    /**
     * Writes one entry so a person can see logging working end to end
     */
    /**
     * Try multiple uploads and the file rules by hand: validates photos[] and reports
     * what it found. Nothing is stored.
     */
    public function uploadTest(Request $request)
    {
        $request->validate([
            'photos'   => 'required|array|max:5',
            'photos.*' => 'image|max:2048|dimensions:max_width=6000,max_height=6000',
        ], ['photos.required' => 'Choose at least one image.']);

        $lines = array_map(function ($file) {
            [$w, $h] = getimagesize($file->path()) ?: [0, 0];
            return sprintf('%s (%s, %d KB, %dx%d px)', $file->clientName(), $file->mimeType(), (int) ceil($file->size() / 1024), $w, $h);
        }, $request->files('photos'));

        flash('success', count($lines) . ' image(s) passed validation: ' . implode('; ', $lines) . '. Nothing was stored.');
        redirect(url('/system#uploads'));
    }

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

    public function dumpTest(Request $request)
    {
        if (!config('app.debug')) {
            abort(404);
        }
        $user = clone Auth::user();
        unset($user->password);
        dd($user, ['request' => $request->all(), 'config.app' => config('app')], new \ArrayObject([1, 2, 3]));
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


    protected function migrationStatus(): array
    {
        try {
            return (new Migrator(base_path('database/migrations')))->status();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
