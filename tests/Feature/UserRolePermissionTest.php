<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserRolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_a_role_with_module_permissions(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $permission = Permission::query()->where('key', 'transactions.view')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('roles.store'), [
            'name' => 'Finance Read Only',
            'permissions' => [$permission->id],
        ]);

        $role = Role::query()->where('slug', 'finance_read_only')->firstOrFail();
        $response->assertRedirect(route('roles.index'));
        $this->assertDatabaseHas('permission_role', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
    }

    public function test_invalid_permission_payload_returns_errors_and_form_can_be_rendered_again(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->from(route('roles.create'))
            ->post(route('roles.store'), [
                'name' => 'Invalid Role',
                'permissions_submitted' => '1',
                'permissions' => 'not-an-array',
            ])
            ->assertRedirect(route('roles.create'))
            ->assertSessionHasErrors('permissions');

        $this->get(route('roles.create'))->assertOk();
    }

    public function test_super_admin_can_view_user_and_role_management_pages(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Tambah Pengguna');

        $this->get(route('roles.index'))
            ->assertOk()
            ->assertSee('Tambah Role');

        $this->get(route('roles.create'))
            ->assertOk()
            ->assertSee('Ulangi callback')
            ->assertSee('Hapus Fixed VA');
    }

    public function test_super_admin_can_create_an_active_user_with_a_role(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Finance Operator',
            'email' => 'finance.operator@example.test',
            'password' => 'secure-password-123',
            'password_confirmation' => 'secure-password-123',
            'role' => 'finance_pusat',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'finance.operator@example.test',
            'role' => 'finance_pusat',
            'is_active' => true,
        ]);
        $this->assertTrue(Hash::check(
            'secure-password-123',
            User::query()->where('email', 'finance.operator@example.test')->value('password'),
        ));
    }

    public function test_role_permissions_grant_only_the_selected_actions(): void
    {
        $role = Role::query()->where('slug', 'finance_pusat')->firstOrFail();
        $role->permissions()->attach(
            Permission::query()->where('key', 'invoices.view')->value('id'),
        );
        $user = User::factory()->create(['role' => 'finance_pusat', 'is_active' => true]);

        $this->actingAs($user)->get(route('invoices.index'))->assertOk();
        $this->actingAs($user)->get(route('settings.branches.index'))->assertForbidden();
        $this->actingAs($user)->post(route('reconciliations.store'))->assertForbidden();
    }

    public function test_granting_a_module_action_also_grants_its_view_permission(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $updatePermission = Permission::query()->where('key', 'transactions.update')->firstOrFail();

        $this->actingAs($admin)->post(route('roles.store'), [
            'name' => 'Callback Operator',
            'permissions' => [$updatePermission->id],
        ])->assertRedirect(route('roles.index'));

        $role = Role::query()->where('slug', 'callback_operator')->firstOrFail();
        $this->assertDatabaseHas('permission_role', [
            'role_id' => $role->id,
            'permission_id' => Permission::query()->where('key', 'transactions.view')->value('id'),
        ]);
    }

    public function test_non_super_admin_cannot_manage_users_or_roles(): void
    {
        $user = User::factory()->create(['role' => 'finance_pusat', 'is_active' => true]);

        $this->actingAs($user)->get(route('users.index'))->assertForbidden();
        $this->actingAs($user)->get(route('roles.index'))->assertForbidden();
    }

    public function test_role_assigned_user_can_log_in_and_is_limited_by_permissions(): void
    {
        $user = User::factory()->create([
            'email' => 'finance.login@example.test',
            'password' => Hash::make('secure-password-123'),
            'role' => 'finance_pusat',
            'is_active' => true,
        ]);
        Role::query()->where('slug', 'finance_pusat')->firstOrFail()
            ->permissions()->attach(Permission::query()->where('key', 'dashboard.view')->value('id'));

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'secure-password-123',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
        $this->get(route('home'))->assertOk();
        $this->get(route('roles.index'))->assertForbidden();
    }

    public function test_last_active_super_admin_cannot_be_demoted_or_deactivated(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->actingAs($admin)->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'finance_pusat',
            'is_active' => '0',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'super_admin',
            'is_active' => true,
        ]);
    }

    public function test_deactivated_user_cannot_continue_using_an_existing_session(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => false]);

        $this->actingAs($user)->get(route('home'))->assertForbidden();
    }
}
