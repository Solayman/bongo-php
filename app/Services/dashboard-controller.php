<?php

namespace App\Services;
class DashboardController
{
    private $cleanup;

    public function __construct()
    {
        require_once 'OCCleanup.php';
        $this->cleanup = new \CleanupManager\app\Services\OCCleanup();
    }

    public function handleRequest()
    {
        $action = $_GET['action'] ?? '';
        $panel = $_GET['panel'] ?? 'all';

        header('Content-Type: application/json');

        switch ($action) {
            case 'clear_cache':
                $result = $this->cleanup->clearViewCache($panel);
                break;
            case 'clear_logs':
                $result = $this->cleanup->clearLogs($panel);
                break;
            case 'clear_sessions':
                $result = $this->cleanup->clearSessions($panel);
                break;
            case 'clear_debugbar':
                $result = $this->cleanup->clearDebugbar($panel);
                break;
            case 'clear_all':
                $result = $this->cleanup->clearAll($panel);
                break;
            case 'system_info':
                $result = $this->cleanup->showSystemInfo();
                break;
            default:
                $result = ['error' => 'Unknown action'];
        }

        echo json_encode([
            'success' => true,
            'action' => $action,
            'panel' => $panel,
            'result' => $result,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}