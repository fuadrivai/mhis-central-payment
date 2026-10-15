<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_home(): void
    {
        $response = $this->get('/home');

        $response->assertRedirect(route('login'));
    }

    public function test_active_super_admin_can_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'is_active' => false,
        ]);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_non_super_admin_is_forbidden_from_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => 'finance_pusat',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertForbidden();
    }

    public function test_authenticated_user_can_change_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->put(route('password.change.update'), [
            'current_password' => 'current-password',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_super_admin_can_view_dashboard_and_sidebar_structure(): void
    {
        $user = User::factory()->create([
            'name' => 'Super Admin MHIS',
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $response->assertSee('MHIS Central Payment');
        $response->assertSee('Super Admin');
        $response->assertSee('Invoices');
        $response->assertSee('Virtual Accounts');
        $response->assertSee('Transactions');
        $response->assertSee('Reconciliation');
        $response->assertSee('Reports');
        $response->assertSee('Users');
        $response->assertSee('Roles &amp; Permissions', false);
        $response->assertSee('Settings');
        $response->assertSee('Audit Logs');
    }
}
