<?php
namespace App\Http\Controllers;

use System\Database\DB;

class SystemController extends Controller
{
    public function index()
    {
        $connection = config('database.connections.' . config('database.default'));

        $db = ['connected' => false, 'error' => null, 'version' => null, 'tables' => []];

        try {
            $db['version'] = DB::raw('SELECT VERSION() AS version')[0]['version'];
            $db['tables'] = DB::table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', $connection['database'])
                ->orderBy('TABLE_NAME')
                ->get();
            $db['connected'] = true;
        } catch (\Throwable $e) {
            $db['error'] = $e->getMessage();
        }

        echo $this->view('system.index', [
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
        ]);
    }
}
