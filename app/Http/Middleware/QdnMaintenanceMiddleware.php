<?php

namespace App\Http\Middleware;

use App\Support\CurrentEmployee;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Maintenance pages (lookup lists) are owned by QA and QMS
 * (CurrentEmployee::DEPARTMENT_QA). App admins (the `admin` table, same
 * check as AdminMiddleware) are let through too so the system owner is
 * never locked out.
 */
class QdnMaintenanceMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!static::allowed()) {
            if ($request->expectsJson()) {
                abort(403);
            }

            return redirect()->route('qdn.dashboard')
                ->with('error', 'Maintenance is limited to the QA and QMS departments.');
        }

        return $next($request);
    }

    public static function allowed(): bool
    {
        $empData = session('emp_data');
        if (!$empData) {
            return false;
        }

        if (CurrentEmployee::isQa()) {
            return true;
        }

        $empId = $empData['emp_id'] ?? null;

        return $empId !== null
            && rescue(fn() => DB::table('admin')->where('emp_id', $empId)->exists(), false, report: false);
    }
}
