<?php
namespace System\Scheduling;

use System\Database\DB;
use System\Database\Schema\Schema;

/**
 * Housekeeping the framework schedules on its own (when the tables exist).
 */
class Maintenance
{
    public static function register(Schedule $schedule): void
    {
        $schedule->call(function () {
            $prune = function (string $table, string $column, $before) {
                if (Schema::hasTable($table)) DB::table($table)->where($column, '<', $before)->delete();
            };
            $expire = (int) config('auth.passwords.expire', 60) * 60;
            $prune('password_resets', 'created_at', date('Y-m-d H:i:s', time() - $expire));
            $prune('jwt_revocations', 'expires_at', date('Y-m-d H:i:s'));
            $prune('login_attempts', 'attempted_at', date('Y-m-d H:i:s', time() - 86400));
        })->hourlyAt(17)->name('Prune expired tokens and login attempts');

        $schedule->call(fn() => \System\Cache\Cache::flush(true))->dailyAt('03:10')->name('Remove expired cache files');
    }
}
