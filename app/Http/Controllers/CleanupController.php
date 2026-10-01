<?php
namespace App\Http\Controllers;

use App\Services\OCCleanup;
use System\Database\DB;

class CleanupController extends Controller
{

    public function dashboard()
    {

        echo $this->view('admin.extends.dashboard', [
            'title' => 'OC Cleanup Dashboard',
            'user' => 'Solayman'
        ]);

    }

    public function clearViewCache() : void
    {
        $panel = $_POST['panel'] ?? 'all';
        $cleanup = new OCCleanup();
        $output = $cleanup->clearViewCache($panel);
        echo json_encode(['status' => 'success', 'message' => $output]);
    }

    public function ajaxHandler()
    {

        header('Content-Type: application/json');

        $action = $_POST['action'] ?? null;
        $panel  = $_POST['panel'] ?? 'all';

        if (!$action) {
            echo $this->respondError('No action specified.');
        }

        try {
            $cleanup = new OCCleanup;

            $result  = $cleanup->run($action, $panel);

            echo $this->respondSuccess("✅ Action executed: {$action}", $result);
        } catch (\Exception $e) {

            echo $this->respondError($e->getMessage());
        }
    }

}
