<?php
require_once '../../OCCleanup.php';

use App\Services\OCCleanup;

// Always force JSON output
header('Content-Type: application/json; charset=utf-8');

// Hide PHP warnings/notices from corrupting JSON output
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');

$action = $_POST['action'] ?? null;
$panel  = $_POST['panel'] ?? 'all';

if (!$action) {
    echo json_encode(['status' => 'error', 'message' => 'No action specified']);
    exit;
}

try {
    $cleanup = new OCCleanup();

    if (!method_exists($cleanup, $action)) {
        echo json_encode(['status' => 'error', 'message' => "❌ Method '{$action}' not found"]);
        exit;
    }

    // Capture all echo output from your OCCleanup class
    ob_start();
    $cleanup->$action($panel);
    $output = ob_get_clean();

    // Return clean JSON
    echo json_encode([
        'status' => 'success',
        'message' => nl2br(trim($output))
    ]);

} catch (Throwable $e) {
    if (ob_get_length()) ob_end_clean();
    echo json_encode([
        'status' => 'error',
        'message' => '❌ Exception: ' . htmlspecialchars($e->getMessage())
    ]);
}
