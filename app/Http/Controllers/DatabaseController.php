<?php

namespace App\Http\Controllers;

use App\Services\OCCleanup;
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
        $deleted = 'panel';
        try {
            // Example: handle different actions
            switch ($action) {
                case 'resetYapiTokens':
                    // Call your cleanup logic
//                    $deleted = $this->clearLogs($panel);

                    echo json_encode([
                        'status' => 'success',
                        'message' => "✅ Cleared Yapi Tokens data deleted for : $panel ($deleted)"
                    ]);
                    break;

                case 'resetTmpYapiReports':
                    // Your logic here
                    echo json_encode([
                        'status' => 'success',
                        'message' => "✅ Cleared reset Tmp Yapi Reports data deleted for : $panel ($deleted)"
                    ]);
                    break;

                case 'resetWallets':
                    // Your logic here
                    echo json_encode([
                        'status' => 'success',
                        'message' => "✅ Wallets data reset for : $panel ($deleted)"
                    ]);
                    break;

                case 'resetMerchants':
                    // Your logic here
                    echo json_encode([
                        'status' => 'success',
                        'message' => "✅ Merchants data reset for : $panel ($deleted)"
                    ]);
                    break;

                case 'runFullCleanup':
                    // Your logic here
                    echo json_encode([
                        'status' => 'success',
                        'message' => "✅ Full Database Cleanup for : $panel ($deleted)"
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