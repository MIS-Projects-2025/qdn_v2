<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * End-to-end checks of the Authify SSO flow through AuthMiddleware,
 * with the authify and app databases swapped for in-memory SQLite.
 */
class AuthifyAuthMiddlewareTest extends TestCase
{
    private const TOKEN = 'tok-123';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.authify' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'services.authify.base_url' => 'https://authify.test',
            'services.authify.portal_id' => 'qdn',
        ]);

        Schema::connection('authify')->create('authify_sessions', function (Blueprint $t) {
            $t->string('token')->primary();
            $t->string('portal_id');
            $t->string('emp_id');
            $t->string('emp_name')->nullable();
            $t->string('emp_firstname')->nullable();
            $t->string('emp_jobtitle')->nullable();
            $t->string('emp_dept')->nullable();
            $t->string('emp_prodline')->nullable();
            $t->string('emp_station')->nullable();
            $t->string('emp_position')->nullable();
            $t->string('emp_job_level')->nullable();
            $t->string('emp_position_level')->nullable();
            $t->string('emp_from')->nullable();
            $t->timestamp('generated_at')->nullable();
            $t->timestamp('activity_at')->nullable();
            $t->timestamp('locked_at')->nullable();
            $t->timestamp('pending_logout_at')->nullable();
        });

        Schema::create('system_status', function (Blueprint $t) {
            $t->id();
            $t->string('status')->default('online');
            $t->text('message')->nullable();
            $t->timestamps();
        });

        $this->seedSession();
    }

    // Dashboard / logout live under the APP_NAME prefix (routes/general.php).
    private function home(): string
    {
        return '/' . env('APP_NAME', '');
    }

    private function cookieName(): string
    {
        return env('SSO_COOKIE_NAME', 'sso_token');
    }

    private function seedSession(array $overrides = []): void
    {
        DB::connection('authify')->table('authify_sessions')->updateOrInsert(
            ['token' => self::TOKEN],
            array_merge([
                'portal_id' => 'qdn',
                'emp_id' => '1705',
                'emp_name' => 'Juan Dela Cruz',
                'emp_firstname' => 'Juan',
                'emp_jobtitle' => 'Process Engineer',
                'emp_dept' => 'Process Engineering',
                'emp_position' => '3',
                'generated_at' => now(),
            ], $overrides)
        );
    }

    public function test_no_token_redirects_to_authify_login(): void
    {
        $this->get($this->home())
            ->assertRedirect('https://authify.test/login?redirect=' . urlencode('http://localhost' . $this->home()) . '&portal_id=qdn');
    }

    public function test_ajax_without_token_gets_401(): void
    {
        $this->getJson($this->home())->assertStatus(401);
    }

    public function test_key_query_param_sets_cookie_and_strips_key(): void
    {
        $this->get('/qdn/dashboard?key=' . self::TOKEN . '&tab=1')
            ->assertRedirect('http://localhost/qdn/dashboard?tab=1')
            ->assertCookie($this->cookieName(), self::TOKEN, false)
            ->assertSessionHas('emp_data.emp_id', '1705')
            ->assertSessionHas('emp_data.emp_dept', 'Process Engineering');
    }

    public function test_cookie_token_reaches_dashboard_with_shared_emp_data(): void
    {
        $this->withUnencryptedCookie($this->cookieName(), self::TOKEN)
            ->get($this->home())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('emp_data.emp_name', 'Juan Dela Cruz')
                ->where('system_status.status', 'online'));
    }

    public function test_token_for_another_portal_is_rejected(): void
    {
        $this->seedSession(['portal_id' => 'other-app']);

        $this->withUnencryptedCookie($this->cookieName(), self::TOKEN)
            ->get($this->home())
            ->assertRedirectContains('https://authify.test/login');
    }

    public function test_external_user_gets_403_unauthorized(): void
    {
        $this->seedSession(['emp_from' => 'agency']);

        $this->withUnencryptedCookie($this->cookieName(), self::TOKEN)
            ->get($this->home())
            ->assertStatus(403)
            ->assertInertia(fn (Assert $page) => $page->component('Unauthorized')->has('logoutUrl'));
    }

    public function test_maintenance_mode_returns_503(): void
    {
        DB::table('system_status')->insert(['id' => 1, 'status' => 'maintenance', 'message' => 'Back soon']);

        $this->withUnencryptedCookie($this->cookieName(), self::TOKEN)
            ->get($this->home())
            ->assertStatus(503)
            ->assertInertia(fn (Assert $page) => $page->component('Maintenance')->where('message', 'Back soon'));
    }

    public function test_logout_cascades_to_authify(): void
    {
        $this->withUnencryptedCookie($this->cookieName(), self::TOKEN)
            ->get($this->home() . '/logout')
            ->assertRedirectContains('https://authify.test/logout?token=' . self::TOKEN)
            ->assertRedirectContains('cascade=1')
            ->assertRedirectContains('portal_id=qdn');
    }

    public function test_session_check_reports_valid_token(): void
    {
        $this->withCredentials()
            ->withUnencryptedCookie($this->cookieName(), self::TOKEN)
            ->getJson('/session/check')
            ->assertOk()
            ->assertJson(['valid' => true, 'locked' => false]);
    }

    public function test_internal_key_bypasses_auth(): void
    {
        config(['services.internal.key' => 'secret']);

        $this->withHeader('X-Internal-Key', 'secret')->get($this->home())->assertOk();
    }
}
