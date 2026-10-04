<?php

namespace System\Database;

use Throwable;

/**
 * Turns "table doesn't exist" errors into an actionable hint when migrations are pending.
 */
class MigrationHint
{
    /** Names of migrations that have not run ([] if none, or if the database is unreachable) */
    public static function pending(): array
    {
        try {
            $status = (new Migrator(BASE_PATH . '/database/migrations'))->status();
            return array_values(array_map(fn($m) => $m['migration'], array_filter($status, fn($m) => !$m['ran'])));
        } catch (Throwable $e) {
            return [];
        }
    }

    /** A hint for this exception, or null when it is not a missing-table/column problem */
    public static function for(Throwable $e): ?string
    {
        $missingSchema = $e instanceof \PDOException
            && (str_contains($e->getMessage(), '42S02') || str_contains($e->getMessage(), '42S22')
                || str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'Unknown column'));
        if (!$missingSchema) {
            return null;
        }

        $pending = self::pending();
        if (!$pending) {
            return null;
        }
        return count($pending) . ' migration(s) have not run: ' . implode(', ', $pending) . '. Run: php bongo migrate';
    }
}
