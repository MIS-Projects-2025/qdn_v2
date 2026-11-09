<?php

namespace App\Http\Controllers;

use App\Services\SystemStatusService;
use Illuminate\Http\Request;

/**
 * Backs the Sidebar's "Set Maintenance" / "Set Online" buttons. While the
 * app is in maintenance, AuthMiddleware renders the Maintenance page for
 * every route except these two and logout (see isBypassRoute()).
 */
class SystemStatusController extends Controller
{
    public function __construct(
        protected SystemStatusService $systemStatusService
    ) {}

    public function maintenance(Request $request)
    {
        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $this->systemStatusService->setMaintenance(
            $validated['message'] ?? 'System is currently under maintenance.'
        );

        return back()->with('success', 'Maintenance mode enabled.');
    }

    public function online()
    {
        $this->systemStatusService->setOnline();

        return back()->with('success', 'System is back online.');
    }
}
