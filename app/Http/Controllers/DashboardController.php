<?php
namespace App\Http\Controllers;

use System\Database\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $connection = config('database.connections.' . config('database.default'));

        $db = [
            'connected' => false,
            'error'     => null,
            'version'   => null,
            'tables'    => [],
        ];

        try {
            // Raw query
            $db['version'] = DB::raw('SELECT VERSION() AS version')[0]['version'];

            // Query builder (read-only)
            $db['tables'] = DB::table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', $connection['database'])
                ->orderBy('TABLE_NAME')
                ->get();

            $db['connected'] = true;
        } catch (\Throwable $e) {
            $db['error'] = $e->getMessage();
        }

        $totalRows = array_sum(array_map(fn($t) => (int) $t->TABLE_ROWS, $db['tables']));
        $totalSize = array_sum(array_map(fn($t) => (int) $t->DATA_LENGTH + (int) $t->INDEX_LENGTH, $db['tables']));

        echo $this->view('dashboard.index', [
            'appName'    => config('app.name'),
            'connection' => $connection,
            'db'         => $db,
            'totalRows'  => $totalRows,
            'totalSizeMb' => round($totalSize / 1048576, 2),
            'checks'     => [
                'Routing'     => true,
                'Environment' => env('APP_ENV') !== null,
                'Config'      => config('app.name') !== null,
                'View engine' => true,
                'Database'    => $db['connected'],
            ],
        ]);
    }
}
