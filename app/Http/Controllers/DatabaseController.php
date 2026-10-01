<?php

namespace App\Http\Controllers;

use App\Services\DatabaseCleaner;
use System\Database\DB;

class DatabaseController extends Controller
{
    public function index()
    {
        echo view('admin.extends.database_dashboard_cleanup', [
            'title' => 'OC Cleanup Dashboard',
            'user' => 'Solayman'
        ]);
    }

    public function cleanDatabase()
    {
        $action = $_POST['action'] ?? null;
        $panel = $_POST['panel'] ?? 'all';

        if (!$action) {
            echo json_encode([
                'status' => 'error',
                'message' => 'No action specified'
            ]);
            return;
        }

        try {
            $cleaner = new DatabaseCleaner();

            switch ($action) {
                case 'resetYapiTokens':
                    ob_start();
                    $cleaner->resetYapiTokens();
                    $output = ob_get_clean();
                    echo json_encode([
                        'status' => 'success',
                        'message' => "✅ Yapi Tokens reset successfully for $panel",
                        'output' => nl2br($output)
                    ]);
                    break;

                case 'resetTmpYapiReports':
                    ob_start();
                    $cleaner->resetTmpYapiReports();
                    $output = ob_get_clean();
                    echo json_encode([
                        'status' => 'success',
                        'message' => "✅ Tmp Yapi Reports reset successfully for $panel",
                        'output' => nl2br($output)
                    ]);
                    break;

                case 'resetWallets':
                    ob_start();
                    $cleaner->resetWallets();
                    $output = ob_get_clean();
                    echo json_encode([
                        'status' => 'success',
                        'message' => "✅ Wallets reset successfully for $panel",
                        'output' => nl2br($output)
                    ]);
                    break;

                case 'resetMerchants':
                    ob_start();
                    $cleaner->resetMerchants();
                    $output = ob_get_clean();
                    echo json_encode([
                        'status' => 'success',
                        'message' => "✅ Merchants reset successfully for $panel",
                        'output' => nl2br($output)
                    ]);
                    break;

                case 'runFullCleanup':
                    ob_start();
                    $cleaner->runFullCleanup();
                    $output = ob_get_clean();
                    echo json_encode([
                        'status' => 'success',
                        'message' => "✅ Full Database Cleanup completed for $panel",
                        'output' => nl2br($output)
                    ]);
                    break;

                case 'getTableContent':
                    $count = $cleaner->getTableCount();
                    echo json_encode([
                        'status' => 'success',
                        'message' => "There are $count tables in cleanup list."
                    ]);
                    break;

                default:
                    echo json_encode([
                        'status' => 'error',
                        'message' => "Unknown action: $action"
                    ]);
            }
        } catch (\Throwable $e) {
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
}
