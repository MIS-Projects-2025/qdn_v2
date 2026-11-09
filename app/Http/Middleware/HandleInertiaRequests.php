<?php

namespace App\Http\Middleware;

use App\Services\SystemStatusService;
use App\Support\CurrentEmployee;
use App\Support\QdnNotificationCounts;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'emp_data' => fn() => session('emp_data'),
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error'   => fn() => $request->session()->get('error'),
            ],
            // Identity comes from Authify via AuthMiddleware (emp_data above),
            // not Laravel's Auth guard, so there is no $request->user() model.
            'auth' => [
                'user' => fn() => session('emp_data'),
            ],
            // Sidebar: which QDN sections to show, and the badge counts.
            'qdnRoles' => fn() => session('emp_data') ? [
                'isPe'       => CurrentEmployee::isPe(),
                'isQa'       => CurrentEmployee::isQa(),
                'isApprover' => CurrentEmployee::isApprover(),
                'canAnswerRcaOrCapa' => CurrentEmployee::canAnswerRcaOrCapa(),
                'crossApprovalDepartment' => CurrentEmployee::crossApprovalDepartmentKey(),
            ] : null,
            'qdnCounts' => fn() => session('emp_data')
                ? rescue(fn() => QdnNotificationCounts::forCurrentEmployee(), null, report: true)
                : null,
            'system_status' => fn() => session('emp_data')
                ? app(SystemStatusService::class)->getCurrent()->only(['status', 'message'])
                : null,
            'appName' => config('app.name'), // This pulls from .env
            'display_name' => env('APP_DISPLAY_NAME', ''),
        ];
    }
}
