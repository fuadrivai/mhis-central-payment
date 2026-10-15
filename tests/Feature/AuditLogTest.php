<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_page_reads_are_recorded_with_actor_route_and_status(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->actingAs($admin)->get(route('home'))->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'web.read',
            'actor_type' => 'user',
            'actor_id' => $admin->id,
            'actor_name' => $admin->name,
            'route_name' => 'home',
            'method' => 'GET',
            'status' => 200,
        ]);
    }

    public function test_failed_api_authentication_is_logged_without_payload_values_or_secrets(): void
    {
        $this->postJson(route('api.v1.virtual-accounts.fixed.store'), [
            'nis' => '01125014',
            'api_key' => 'payload-secret-value',
            'password' => 'payload-password-value',
        ], ['X-API-KEY' => 'header-secret-value'])->assertUnauthorized();

        $log = AuditLog::query()->sole();

        $this->assertSame('api.request', $log->event);
        $this->assertSame('guest', $log->actor_type);
        $this->assertSame(401, $log->status);
        $this->assertSame(['nis'], $log->properties['input_fields']);
        $this->assertStringNotContainsString('payload-secret-value', $log->toJson());
        $this->assertStringNotContainsString('payload-password-value', $log->toJson());
        $this->assertStringNotContainsString('header-secret-value', $log->toJson());
    }

    public function test_api_validation_failure_is_logged_with_authenticated_branch_actor(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Audit Test Branch',
            'code' => 'AUD',
            'va_fixed_prefix' => '85765010',
            'va_dynamic_prefix' => '85765991',
            'api_key' => 'audit-test-branch-api-key',
            'is_active' => true,
        ]);

        $this->postJson(route('api.v1.virtual-accounts.fixed.store'), [], [
            'X-API-KEY' => $branch->api_key,
        ])->assertUnprocessable();

        $log = AuditLog::query()->sole();

        $this->assertSame('branch', $log->actor_type);
        $this->assertSame($branch->id, $log->actor_id);
        $this->assertSame(422, $log->status);
        $this->assertSame('api.v1.virtual-accounts.fixed.store', $log->route_name);
    }

    public function test_failed_login_attempt_is_logged_without_credentials(): void
    {
        $this->from(route('login'))->post(route('login.store'), [
            'email' => 'unknown@example.test',
            'password' => 'incorrect-secret',
        ])->assertRedirect(route('login'));

        $log = AuditLog::query()->sole();

        $this->assertSame('auth.login', $log->event);
        $this->assertSame('guest', $log->actor_type);
        $this->assertSame('failure', $log->properties['outcome']);
        $this->assertSame(['email'], $log->properties['input_fields']);
        $this->assertStringNotContainsString('incorrect-secret', $log->toJson());
    }

    public function test_successful_login_and_logout_are_recorded_for_the_user(): void
    {
        $user = User::factory()->create([
            'email' => 'audit.admin@example.test',
            'password' => 'correct-password',
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('home'));

        $this->post(route('logout'))->assertRedirect(route('login'));

        $login = AuditLog::query()->where('event', 'auth.login')->sole();
        $logout = AuditLog::query()->where('event', 'auth.logout')->sole();

        $this->assertSame('user', $login->actor_type);
        $this->assertSame($user->id, $login->actor_id);
        $this->assertSame('success', $login->properties['outcome']);
        $this->assertSame('user', $logout->actor_type);
        $this->assertSame($user->id, $logout->actor_id);
        $this->assertSame('success', $logout->properties['outcome']);
    }

    public function test_mandiri_callback_is_logged_with_bank_actor_without_callback_payload(): void
    {
        $bank = Bank::query()->where('code', 'mandiri')->firstOrFail();
        Bank::query()->whereKey($bank->id)->update([
            'callback_api_key_hash' => hash('sha256', 'callback-secret'),
        ]);

        $this->postJson(route('api.v1.callbacks.mandiri.virtual-account-paid'), [], [
            'X-API-KEY' => 'callback-secret',
        ])->assertUnprocessable();

        $log = AuditLog::query()->sole();

        $this->assertSame('bank', $log->actor_type);
        $this->assertSame($bank->id, $log->actor_id);
        $this->assertSame(422, $log->status);
        $this->assertSame([], $log->properties['input_fields']);
        $this->assertStringNotContainsString('callback-secret', $log->toJson());
    }

    public function test_browser_validation_redirect_is_logged_as_a_failed_write(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->from(route('roles.create'))
            ->post(route('roles.store'), ['name' => ''])
            ->assertRedirect(route('roles.create'));

        $log = AuditLog::query()->sole();

        $this->assertSame('web.write', $log->event);
        $this->assertSame(302, $log->status);
        $this->assertSame('failure', $log->properties['outcome']);
        $this->assertSame(['name'], $log->properties['input_fields']);
    }

    public function test_audit_log_page_requires_view_permission_and_can_filter_routes(): void
    {
        $operator = User::factory()->create(['role' => 'finance_pusat', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->actingAs($admin)->get(route('home'))->assertOk();
        $this->actingAs($operator)->get(route('audit-logs.index'))->assertForbidden();

        Role::query()->where('slug', 'finance_pusat')->firstOrFail()
            ->permissions()->attach(Permission::query()->where('key', 'audit_logs.view')->firstOrFail());
        $operator->unsetRelation('roleModel');

        $this->actingAs($operator)->get(route('audit-logs.index'))->assertOk();
        $this->actingAs($admin)->get(route('audit-logs.index', ['route' => 'home']))
            ->assertOk()
            ->assertSee('home');
    }
}
