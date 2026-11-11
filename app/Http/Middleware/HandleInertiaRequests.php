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
                // Fixed key (pe/ee/qa/prod), or "other" for a Supervisor+ of
                // another department while a Customer: Others QDN it raised
                // is at this step.
                'crossApprovalDepartment' => CurrentEmployee::crossApprovalDepartmentKey()
                    ?? (rescue(fn () => ($dept = CurrentEmployee::otherIssuingDepartment())
                        && \App\Models\QdnCrossApproval::scopeIssuedByDepartment(
                            \App\Models\Qdn::where('status', \App\Models\Qdn::STATUS_FOR_CROSS_APPROVAL), $dept
                        )->exists(), false, report: true) ? \App\Models\QdnCrossApproval::DEPT_OTHER : null),
                // Maintenance (lookup lists): QA / QMS, plus app admins.
                'canMaintain' => \App\Http\Middleware\QdnMaintenanceMiddleware::allowed(),
            ] : null,
            'qdnCounts' => fn() => session('emp_data')
                ? rescue(fn() => QdnNotificationCounts::forCurrentEmployee(), null, report: true)
                : null,
            'system_status' => fn() => session('emp_data')
                ? app(SystemStatusService::class)->getCurrent()->only(['status', 'message'])
                : null,
            'appName' => config('app.name'), // This pulls from .env
            // Company logo in public/telford_logo.jpg (same file as the browser tab
            // icon). asset() builds the full URL, including the /qdn_v2
            // sub-folder, so it loads on every page. null when the file is
            // missing, so the sidebar shows text instead of a broken image.
            'logo_url' => file_exists(public_path('telford_logo.jpg')) ? asset('telford_logo.jpg') : null,
            'display_name' => env('APP_DISPLAY_NAME', ''),
        ];
    }
}
