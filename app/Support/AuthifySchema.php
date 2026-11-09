<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Lets qdn run against both Authify database versions:
 *
 *  - old: authify_sessions has token, emp_* and generated_at only
 *  - new: also portal_id, emp_job_level, emp_position_level,
 *         activity_at, locked_at, pending_logout_at
 *
 * Features that need a missing column (portal check, idle lock, activity
 * sync, close-beacon, sibling sessions) switch themselves off on the old
 * schema instead of breaking login. The column list is cached for 10
 * minutes in the file cache (CACHE_STORE=array would re-query the remote
 * Authify DB on every request); run `php artisan cache:clear` after
 * upgrading Authify.
 */
class AuthifySchema
{
    private static ?array $columns = null;

    public static function columns(): array
    {
        if (self::$columns !== null) {
            return self::$columns;
        }

        try {
            return self::$columns = Cache::store('file')->remember('authify_sessions_columns', 600, fn () =>
                Schema::connection('authify')->getColumnListing('authify_sessions'));
        } catch (\Throwable $e) {
            report($e);
            return self::$columns = [];
        }
    }

    public static function has(string $column): bool
    {
        return in_array($column, self::columns(), true);
    }

    /** Old schema has no portal_id, so any session counts for this portal. */
    public static function belongsToPortal(?object $row, ?string $portalId): bool
    {
        if (!$row) {
            return false;
        }

        return !self::has('portal_id') || ($row->portal_id ?? null) === $portalId;
    }

    public static function flush(): void
    {
        self::$columns = null;
        Cache::store('file')->forget('authify_sessions_columns');
    }
}
