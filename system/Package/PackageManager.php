<?php

namespace System\Package;

use System\Console\Command;
use System\Translation\Translator;
use System\View\ViewEngine;
use ReflectionClass;

/**
 * Manages installed packages, lockfile persistence, and runtime booting.
 * Zero runtime cost when no packages are installed.
 */
class PackageManager
{
    protected static ?array $installed = null;
    protected static string $lockFile = BASE_PATH . '/packages/.lock.json';

    /**
     * Boot all installed packages: register views, translations, and load routes.
     * Costs one is_file check when no packages are installed.
     */
    public static function boot(): void
    {
        if (!is_file(self::$lockFile)) {
            return;
        }

        $packages = self::installed();
        if (empty($packages)) {
            return;
        }

        $packagesDir = dirname(self::$lockFile);
        foreach ($packages as $name => $meta) {
            $pkgDir = $packagesDir . '/' . $name;
            if (!is_dir($pkgDir)) {
                continue;
            }

            // Register view namespace: @include('name::view')
            $viewsDir = $pkgDir . '/views';
            if (is_dir($viewsDir)) {
                ViewEngine::addNamespace($name, $viewsDir);
            }

            // Register translation namespace: __('name::key')
            $langDir = $pkgDir . '/lang';
            if (is_dir($langDir)) {
                Translator::addNamespace($name, $langDir);
            }
        }
    }

    /**
     * Load routes defined by installed packages into the router.
     */
    public static function loadRoutes(\System\Routing\Router $router): void
    {
        if (!is_file(self::$lockFile)) {
            return;
        }

        $packagesDir = dirname(self::$lockFile);
        foreach (self::installed() as $name => $meta) {
            $routesFile = $packagesDir . '/' . $name . '/routes.php';
            if (is_file($routesFile)) {
                $router->loadFile($routesFile);
            }
        }
    }

    /**
     * Get migration directories for all installed packages.
     *
     * @return string[]
     */
    public static function migrationPaths(): array
    {
        if (!is_file(self::$lockFile)) {
            return [];
        }

        $paths = [];
        $packagesDir = dirname(self::$lockFile);
        foreach (self::installed() as $name => $meta) {
            $dir = $packagesDir . '/' . $name . '/database/migrations';
            if (is_dir($dir)) {
                $paths[] = $dir;
            }
        }

        return $paths;
    }

    /**
     * Discover and instantiate console commands provided by installed packages.
     *
     * @return array<string, Command>
     */
    public static function commands(): array
    {
        if (!is_file(self::$lockFile)) {
            return [];
        }

        $commands = [];
        $packagesDir = dirname(self::$lockFile);

        foreach (self::installed() as $name => $meta) {
            $cmdDir = $packagesDir . '/' . $name . '/src/Commands';
            if (!is_dir($cmdDir)) {
                continue;
            }

            foreach (glob($cmdDir . '/*.php') ?: [] as $file) {
                require_once $file;
                $studly = str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));
                $class = 'Bongo\\Packages\\' . $studly . '\\Commands\\' . basename($file, '.php');

                if (class_exists($class) && is_subclass_of($class, Command::class)) {
                    $ref = new ReflectionClass($class);
                    if (!$ref->isAbstract()) {
                        /** @var Command $instance */
                        $instance = new $class();
                        if ($instance->getName() !== '') {
                            $commands[$instance->getName()] = $instance;
                        }
                    }
                }
            }
        }

        return $commands;
    }

    /**
     * Get array of all installed packages: name => meta
     */
    public static function installed(): array
    {
        if (self::$installed !== null) {
            return self::$installed;
        }

        if (!is_file(self::$lockFile)) {
            return self::$installed = [];
        }

        $content = @file_get_contents(self::$lockFile);
        $data = $content ? json_decode($content, true) : null;

        return self::$installed = (is_array($data) && isset($data['packages']) && is_array($data['packages']))
            ? $data['packages']
            : [];
    }

    public static function isInstalled(string $name): bool
    {
        return isset(self::installed()[$name]);
    }

    public static function lockFile(): string
    {
        return self::$lockFile;
    }

    public static function setLockFile(string $path): void
    {
        self::$lockFile = $path;
        self::$installed = null;
    }

    /**
     * Persist updated lock data to packages/.lock.json
     */
    public static function writeLock(array $packages): void
    {
        $dir = dirname(self::$lockFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $data = [
            'generated_at' => date('c'),
            'packages' => $packages,
        ];

        file_put_contents(
            self::$lockFile,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL
        );

        self::$installed = $packages;
    }
}
