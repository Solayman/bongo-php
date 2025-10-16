<?php

namespace App\Services;

use Exception;

class OCCleanup
{
    private $projectPath;
    private $panels = [
        'main'      => '',
        'admin'     => '/admin',
        'ccpayment' => '/ccpayment',
        'merchant'  => '/merchant'
    ];
    private $baseDirectory = 'D:\\Laravel\\app.softrobotics.com.tr';
    public function __construct()
    {

        $this->projectPath = rtrim($this->baseDirectory, '/\\');
    }

    public function run(string $action, string $panel = 'all')
    {
        if (!method_exists($this, $action)) {
            throw new Exception("Unknown action: {$action}");
        }
        return $this->$action($panel);
    }

    public function clearViewCache($panel)
    {
        return $this->clearDirectory('storage/framework/views', $panel, 'View cache cleared');
    }

    public function clearConfigCache($panel)
    {
        $results = [];
        foreach ($this->getPanels($panel) as $name => $path) {
            $fullPath = $this->projectPath . $path . '/bootstrap/cache';
            $files = ['config.php', 'services.php', 'packages.php'];
            $deleted = 0;

            foreach ($files as $file) {
                $f = $fullPath . '/' . $file;
                if (file_exists($f)) {
                    unlink($f);
                    $deleted++;
                }
            }
            $results[$name] = "✅ {$deleted} config files deleted";
        }
        return $results;
    }

    public function clearLogs($panel)
    {
        return $this->clearDirectory('storage/logs', $panel, 'Logs cleared');
    }

    public function clearSessions($panel)
    {
        return $this->clearDirectory('storage/framework/sessions', $panel, 'Sessions cleared');
    }

    public function clearDebugbar($panel)
    {
        return $this->clearDirectory('storage/debugbar', $panel, 'Debugbar cleared');
    }

    public function clearAll($panel)
    {
        return [
            'views'   => $this->clearViewCache($panel),
            'config'  => $this->clearConfigCache($panel),
            'logs'    => $this->clearLogs($panel),
            'sessions'=> $this->clearSessions($panel),
            'debugbar'=> $this->clearDebugbar($panel)
        ];
    }

    public function optimize($panel)
    {
        // Run artisan optimize for each panel
        $results = [];
        foreach ($this->getPanels($panel) as $name => $path) {
            $artisan = $this->projectPath . $path . '/artisan';
            if (file_exists($artisan)) {
                exec("php " . escapeshellarg($artisan) . " optimize", $output, $code);
                $results[$name] = "✅ Optimized ({$code})";
            } else {
                $results[$name] = "❌ Artisan not found";
            }
        }
        return $results;
    }

    public function showSystemInfo()
    {
        return [
            'php_version' => PHP_VERSION,
            'os' => PHP_OS,
            'project_path' => $this->projectPath,
            'panels' => array_map(function($path) {
                return is_dir($this->projectPath . $path) ? '✅ Found' : '❌ Missing';
            }, $this->panels)
        ];
    }

    // -------------------------
    // Helper methods
    // -------------------------

    private function clearDirectory(string $relativePath, $panel, $message)
    {
        $results = [];
        foreach ($this->getPanels($panel) as $name => $path) {
            $fullPath = $this->projectPath . $path . '/' . $relativePath;
            if (!is_dir($fullPath)) {
                $results[$name] = "⚠ Directory not found: {$fullPath}";
                continue;
            }

            $deleted = 0;
            foreach (glob($fullPath . '/*') as $file) {
                if (is_file($file) && basename($file)[0] !== '.') {
                    unlink($file);
                    $deleted++;
                }
            }
            $results[$name] = "✅ {$message} ({$deleted} files)";
        }
        return $results;
    }

    private function getPanels($panel)
    {
        if ($panel === 'all') return $this->panels;

        if (!isset($this->panels[$panel])) {
            throw new Exception("Invalid panel: {$panel}");
        }

        return [$panel => $this->panels[$panel]];
    }
}
