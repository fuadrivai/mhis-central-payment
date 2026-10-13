<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use App\Models\VirtualAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_branch_settings(): void
    {
        $response = $this->get(route('settings.branches.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_non_super_admin_cannot_manage_branches(): void
    {
        $user = User::factory()->create([
            'role' => 'finance_pusat',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('settings.branches.index'));

        $response->assertForbidden();
    }

    public function test_super_admin_can_view_branch_settings_without_exposing_api_keys(): void
    {
        $user = $this->createSuperAdmin();
        $branch = $this->createBranch(['api_key' => str_repeat('a', 64)]);

        $response = $this->actingAs($user)->get(route('settings.branches.index'));

        $response->assertOk();
        $response->assertSee('Pengaturan Cabang');
        $response->assertSee($branch->name);
        $response->assertSee($branch->va_fixed_prefix);
        $response->assertSee($branch->va_dynamic_prefix);
        $response->assertDontSee($branch->api_key);
    }

    public function test_super_admin_can_open_branch_forms(): void
    {
        $user = $this->createSuperAdmin();
        $branch = $this->createBranch();

        $this->actingAs($user)
            ->get(route('settings.branches.create'))
            ->assertOk()
            ->assertSee('Tambah Cabang')
            ->assertSee('va_dynamic_prefix');

        $this->get(route('settings.branches.edit', $branch))
            ->assertOk()
            ->assertSee('Edit Cabang')
            ->assertSee($branch->name)
            ->assertSee('API Key Cabang')
            ->assertSee($branch->api_key)
            ->assertSee('readonly', false)
            ->assertSee('data-copy-target="branch-api-key"', false);
    }

    public function test_super_admin_can_create_a_branch_and_receives_its_api_key_once(): void
    {
        $user = $this->createSuperAdmin();

        $response = $this->actingAs($user)->post(route('settings.branches.store'), [
            'name' => 'Semarang',
            'code' => 'SMG',
            'va_fixed_prefix' => '85765030',
            'va_dynamic_prefix' => '85765993',
            'is_active' => '1',
        ]);

        $branch = Branch::query()->where('code', 'SMG')->firstOrFail();

        $response->assertRedirect(route('settings.branches.index'));
        $response->assertSessionHas('branch_api_key', $branch->api_key);
        $this->get(route('settings.branches.index'))->assertSee($branch->api_key);
        $this->assertSame(64, strlen((string) $branch->api_key));
        $this->assertTrue($branch->is_active);
    }

    public function test_branch_prefixes_must_contain_only_digits(): void
    {
        $user = $this->createSuperAdmin();

        $response = $this->actingAs($user)->from(route('settings.branches.create'))->post(route('settings.branches.store'), [
            'name' => 'Semarang',
            'code' => 'SMG',
            'va_fixed_prefix' => '85765B',
            'va_dynamic_prefix' => '85765993',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('settings.branches.create'));
        $response->assertSessionHasErrors('va_fixed_prefix');
        $this->assertDatabaseMissing('branches', ['code' => 'SMG']);
    }

    public function test_fixed_va_prefix_must_contain_exactly_eight_digits(): void
    {
        $user = $this->createSuperAdmin();

        $response = $this->actingAs($user)->from(route('settings.branches.create'))
            ->post(route('settings.branches.store'), [
                'name' => 'Semarang',
                'code' => 'SMG',
                'va_fixed_prefix' => '8576503',
                'va_dynamic_prefix' => '85765993',
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('settings.branches.create'));
        $response->assertSessionHasErrors('va_fixed_prefix');
        $this->assertDatabaseMissing('branches', ['code' => 'SMG']);
    }

    public function test_super_admin_can_update_branch_settings(): void
    {
        $user = $this->createSuperAdmin();
        $branch = $this->createBranch();

        $response = $this->actingAs($user)->put(route('settings.branches.update', $branch), [
            'name' => 'Bintaro Utama',
            'code' => $branch->code,
            'va_fixed_prefix' => $branch->va_fixed_prefix,
            'va_dynamic_prefix' => $branch->va_dynamic_prefix,
            'is_active' => '0',
        ]);

        $response->assertRedirect(route('settings.branches.index'));
        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'name' => 'Bintaro Utama',
            'is_active' => false,
        ]);
    }

    public function test_branch_prefix_cannot_be_changed_after_virtual_accounts_exist(): void
    {
        $user = $this->createSuperAdmin();
        $branch = $this->createBranch();
        VirtualAccount::query()->create([
            'branch_id' => $branch->id,
            'va_type' => 'dynamic',
            'partner_service_id' => '85765',
            'customer_no' => '85765991000000001',
            'virtual_account_no' => '85765991000000001',
            'virtual_account_name' => 'Test Customer',
            'current_amount' => '1000.00',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->put(route('settings.branches.update', $branch), [
            'name' => $branch->name,
            'code' => $branch->code,
            'va_fixed_prefix' => '85765110',
            'va_dynamic_prefix' => '857659911',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors(['va_fixed_prefix', 'va_dynamic_prefix']);
        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'va_fixed_prefix' => $branch->va_fixed_prefix,
            'va_dynamic_prefix' => $branch->va_dynamic_prefix,
        ]);
    }

    public function test_super_admin_can_rotate_branch_api_key(): void
    {
        $user = $this->createSuperAdmin();
        $branch = $this->createBranch(['api_key' => str_repeat('a', 64)]);

        $response = $this->actingAs($user)->post(route('settings.branches.rotate-api-key', $branch));

        $newApiKey = $branch->fresh()->api_key;
        $response->assertRedirect(route('settings.branches.index'));
        $response->assertSessionHas('branch_api_key', $newApiKey);
        $this->get(route('settings.branches.index'))->assertSee($newApiKey);
        $this->assertNotSame(str_repeat('a', 64), $newApiKey);
    }

    public function test_branch_name_is_escaped_in_the_branch_list(): void
    {
        $user = $this->createSuperAdmin();
        $this->createBranch(['name' => '<script>alert(1)</script>']);

        $response = $this->actingAs($user)->get(route('settings.branches.index'));

        $response->assertOk();
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createBranch(array $attributes = []): Branch
    {
        return Branch::query()->create(array_merge([
            'name' => 'Bintaro',
            'code' => 'BTR',
            'va_fixed_prefix' => '85765010',
            'va_dynamic_prefix' => '85765991',
            'api_key' => str_repeat('b', 64),
            'is_active' => true,
        ], $attributes));
    }

    private function createSuperAdmin(): User
    {
        return User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
    }
}
