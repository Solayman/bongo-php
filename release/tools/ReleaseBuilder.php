<?php

namespace System\Console;

use RuntimeException;

/**
 * php bongo release:build <folder>: a fresh Bongo project without the demo application.
 * The framework files below are copied as they are; release/skeleton/ adds the starter
 * files (welcome page, routes, User model, empty seeder, example tests, README).
 */
class ReleaseBuilder
{
    /** Copied from this repository: the framework and what every project needs */
    public const FRAMEWORK = [
        'system', 'bongo', 'config', 'lang', 'server.php',
        'public/index.php', 'public/.htaccess', 'public/storage/.htaccess',
        '.gitignore', '.env.example', 'DOCUMENTATION.md', 'WHY_BONGO.md', 'UPGRADE.md', 'LICENSE', 'CHANGELOG.md',
        'public/asset/js/bongo-socket.js',
        'app/Http/Controllers/Controller.php', 'app/Http/Middleware', 'app/Helpers/commonHelper.php',
        'resources/views/errors', 'resources/views/components/alert.php',
        'resources/views/partials/flash.php', 'resources/views/partials/debug-footer.php',
        'database/migrations/2026_10_01_000001_create_users_table.php',
        'database/migrations/2026_10_01_000006_create_login_attempts_table.php',
        'database/migrations/2026_10_02_000007_create_jwt_revocations_table.php',
        'database/migrations/2026_10_02_000009_create_password_resets_table.php',
        'database/migrations/2026_10_02_000010_create_jobs_tables.php',
        'database/migrations/2026_10_03_000011_add_remember_token_to_users_table.php',
        'database/migrations/2026_10_03_000012_add_email_verified_at_to_users_table.php',
    ];

    public function __construct(protected string $source)
    {
    }

    /** Build into $target (must not exist or be empty); returns the number of files written */
    public function build(string $target): int
    {
        $target = rtrim($target, '/\\');
        if (is_dir($target) && (new \FilesystemIterator($target))->valid()) {
            throw new RuntimeException("$target is not empty. Choose a new folder.");
        }
        // The new project must not end up inside this repository
        $parent = realpath(dirname($target));
        $absolute = str_replace('\\', '/', ($parent ?: dirname($target)) . '/' . basename($target)) . '/';
        if (str_starts_with(strtolower($absolute), strtolower(str_replace('\\', '/', (string) realpath($this->source)) . '/'))) {
            throw new RuntimeException('Build the release outside this repository, e.g. ../my-app');
        }

        $count = 0;
        foreach (self::FRAMEWORK as $path) {
            if (!file_exists("{$this->source}/$path")) {
                throw new RuntimeException("Missing framework file: $path");
            }
            $count += $this->copy("{$this->source}/$path", "$target/$path");
        }
        $count += $this->copy("{$this->source}/release/skeleton", $target);

        foreach (['storage/logs', 'storage/cache', 'storage/mail', 'public/storage', 'packages'] as $dir) {
            if (!is_dir("$target/$dir")) {
                mkdir("$target/$dir", 0775, true);
            }
        }
        return $count;
    }

    /** Copy a file or a whole folder; returns how many files were copied */
    protected function copy(string $from, string $to): int
    {
        if (is_file($from)) {
            if (!is_dir(dirname($to))) {
                mkdir(dirname($to), 0775, true);
            }
            copy($from, $to);
            return 1;
        }
        $count = 0;
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($items as $item) {
            $dest = $to . '/' . substr(str_replace('\\', '/', $item->getPathname()), strlen(str_replace('\\', '/', $from)) + 1);
            if ($item->isDir()) {
                if (!is_dir($dest)) {
                    mkdir($dest, 0775, true);
                }
            } else {
                $count += $this->copy($item->getPathname(), $dest);
            }
        }
        return $count;
    }
}
