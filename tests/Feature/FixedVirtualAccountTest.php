<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Student;
use App\Models\User;
use App\Models\VirtualAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FixedVirtualAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_fixed_virtual_accounts(): void
    {
        $response = $this->get(route('virtual-accounts.fixed.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_non_super_admin_cannot_create_fixed_virtual_accounts(): void
    {
        Http::preventStrayRequests();

        $user = User::factory()->create([
            'role' => 'finance_pusat',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('virtual-accounts.fixed.create'));

        $response->assertForbidden();
    }

    public function test_super_admin_sees_parent_and_optional_additional_email_fields(): void
    {
        $user = $this->createSuperAdmin();
        $this->createBranch();

        $response = $this->actingAs($user)->get(route('virtual-accounts.fixed.create'));

        $response->assertOk();
        $response->assertSee('name="parent_email"', false);
        $response->assertSee('name="additional_email"', false);
        $response->assertSee('name="parent_email" type="email"', false);
        $response->assertSee('name="additional_email" type="email"', false);
        $response->assertSee('value="" required maxlength="255" autocomplete="email"', false);
    }

    public function test_super_admin_can_create_h2h_fixed_va_from_branch_prefix_and_nis(): void
    {
        $this->travelTo('2026-10-13 10:00:00');
        Http::preventStrayRequests();
        Http::fake([
            'https://h2h.test/api/internal/va/create' => Http::response(['status' => 'success'], 201),
        ]);
        config([
            'services.mandiri_h2h.base_url' => 'https://h2h.test',
            'services.mandiri_h2h.api_key' => 'test-h2h-key',
            'services.mandiri_h2h.partner_service_id' => '85765',
        ]);

        $user = $this->createSuperAdmin();
        $branch = $this->createBranch();

        $response = $this->actingAs($user)->post(route('virtual-accounts.fixed.store'), [
            'branch_id' => $branch->id,
            'va_number_mode' => 'automatic',
            'nis' => '01234567',
            'name' => 'Budi Santoso',
            'nisn' => '0099887766',
            'grade_class' => 'Primary 3A',
            'parent_name' => 'Wali Budi',
            'parent_email' => 'wali.budi@example.com',
            'parent_phone' => '081234567890',
            'expired_at' => '2027-06-30T23:59',
        ]);

        $response->assertRedirect(route('virtual-accounts.fixed.index'));
        $response->assertSessionHas('status');
        $this->assertDatabaseHas('students', [
            'branch_id' => $branch->id,
            'nis' => '01234567',
            'fixed_va_number' => '8576501001234567',
            'parent_email' => 'wali.budi@example.com',
            'additional_email' => null,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('virtual_accounts', [
            'branch_id' => $branch->id,
            'virtual_account_no' => '8576501001234567',
            'va_type' => 'fixed',
            'current_amount' => '0.00',
            'expired_at' => '2027-06-30 23:59:00',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('h2h_api_logs', [
            'branch_id' => $branch->id,
            'endpoint' => '/api/internal/va/create',
            'http_method' => 'POST',
            'response_status' => 201,
        ]);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://h2h.test/api/internal/va/create'
                && $request->hasHeader('X-API-KEY', 'test-h2h-key')
                && $request->hasHeader('Content-Type', 'application/json')
                && $request['partnerServiceId'] === '85765'
                && $request['customerNo'] === '8576501001234567'
                && $request['virtualAccountNo'] === '8576501001234567'
                && $request['virtualAccountName'] === 'Budi Santoso'
                && $request['totalAmount'] === '0.00'
                && $request['feeAmount'] === '0.00'
                && $request['billCode'] === 'FIXED-BTR-01234567'
                && $request['billName'] === 'Fixed VA SPP - Budi Santoso'
                && $request['expiredAt'] === '2027-06-30 23:59:00';
        });
    }

    public function test_super_admin_can_create_fixed_va_with_manually_entered_number(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://h2h.test/api/internal/va/create' => Http::response(['status' => 'success'], 200),
        ]);
        config([
            'services.mandiri_h2h.base_url' => 'https://h2h.test',
            'services.mandiri_h2h.api_key' => 'test-h2h-key',
        ]);

        $user = $this->createSuperAdmin();
        $branch = $this->createBranch();

        $this->actingAs($user)->post(route('virtual-accounts.fixed.store'), [
            'branch_id' => $branch->id,
            'va_number_mode' => 'manual',
            'nis' => '54321098',
            'manual_va_number' => '8576501099887766',
            'name' => 'Siti Aminah',
            'parent_email' => 'wali.siti@example.com',
            'additional_email' => 'keluarga.siti@example.com',
            'expired_at' => '2027-06-30T23:59',
        ])->assertRedirect(route('virtual-accounts.fixed.index'));

        $this->assertDatabaseHas('virtual_accounts', [
            'student_id' => Student::query()->where('nis', '54321098')->value('id'),
            'virtual_account_no' => '8576501099887766',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('students', [
            'nis' => '54321098',
            'parent_email' => 'wali.siti@example.com',
            'additional_email' => 'keluarga.siti@example.com',
        ]);
    }

    public function test_parent_email_is_required_to_create_a_fixed_va(): void
    {
        Http::preventStrayRequests();
        $user = $this->createSuperAdmin();
        $branch = $this->createBranch();

        $response = $this->actingAs($user)->from(route('virtual-accounts.fixed.create'))
            ->post(route('virtual-accounts.fixed.store'), [
                'branch_id' => $branch->id,
                'va_number_mode' => 'automatic',
                'nis' => '01234567',
                'name' => 'Budi Santoso',
                'expired_at' => '2027-06-30T23:59',
            ]);

        $response->assertRedirect(route('virtual-accounts.fixed.create'));
        $response->assertSessionHasErrors([
            'parent_email' => 'Email orang tua/wali wajib diisi.',
        ]);
        $this->assertDatabaseCount('students', 0);
    }

    public function test_invalid_parent_email_is_rejected(): void
    {
        Http::preventStrayRequests();
        $user = $this->createSuperAdmin();
        $branch = $this->createBranch();

        $response = $this->actingAs($user)->from(route('virtual-accounts.fixed.create'))
            ->post(route('virtual-accounts.fixed.store'), [
                'branch_id' => $branch->id,
                'va_number_mode' => 'automatic',
                'nis' => '01234567',
                'name' => 'Budi Santoso',
                'parent_email' => 'not-an-email',
                'expired_at' => '2027-06-30T23:59',
            ]);

        $response->assertRedirect(route('virtual-accounts.fixed.create'));
        $response->assertSessionHasErrors([
            'parent_email' => 'Email orang tua/wali harus berupa alamat email yang valid.',
        ]);
        $this->assertDatabaseCount('students', 0);
    }

    public function test_invalid_additional_email_is_rejected(): void
    {
        Http::preventStrayRequests();
        $user = $this->createSuperAdmin();
        $branch = $this->createBranch();

        $response = $this->actingAs($user)->from(route('virtual-accounts.fixed.create'))
            ->post(route('virtual-accounts.fixed.store'), [
                'branch_id' => $branch->id,
                'va_number_mode' => 'automatic',
                'nis' => '01234567',
                'name' => 'Budi Santoso',
                'parent_email' => 'wali.budi@example.com',
                'additional_email' => 'not-an-email',
                'expired_at' => '2027-06-30T23:59',
            ]);

        $response->assertRedirect(route('virtual-accounts.fixed.create'));
        $response->assertSessionHasErrors([
            'additional_email' => 'Email tambahan harus berupa alamat email yang valid.',
        ]);
        $this->assertDatabaseCount('students', 0);
    }

    public function test_manual_fixed_va_must_start_with_the_selected_branch_prefix(): void
    {
        Http::preventStrayRequests();
        $user = $this->createSuperAdmin();
        $branch = $this->createBranch();

        $response = $this->actingAs($user)->from(route('virtual-accounts.fixed.create'))
            ->post(route('virtual-accounts.fixed.store'), [
                'branch_id' => $branch->id,
                'va_number_mode' => 'manual',
                'nis' => '01234567',
                'manual_va_number' => '8576502001234567',
                'name' => 'Budi Santoso',
                'parent_email' => 'wali.budi@example.com',
                'expired_at' => '2027-06-30T23:59',
            ]);

        $response->assertRedirect(route('virtual-accounts.fixed.create'));
        $response->assertSessionHasErrors('manual_va_number');
        $this->assertDatabaseCount('students', 0);
    }

    public function test_nis_shorter_than_eight_digits_is_rejected(): void
    {
        Http::preventStrayRequests();
        $user = $this->createSuperAdmin();
        $branch = $this->createBranch();

        $response = $this->actingAs($user)->from(route('virtual-accounts.fixed.create'))
            ->post(route('virtual-accounts.fixed.store'), [
                'branch_id' => $branch->id,
                'va_number_mode' => 'automatic',
                'nis' => '1234567',
                'name' => 'Budi Santoso',
                'expired_at' => '2027-06-30T23:59',
            ]);

        $response->assertRedirect(route('virtual-accounts.fixed.create'));
        $response->assertSessionHasErrors('nis');
        $this->assertDatabaseCount('students', 0);
    }

    public function test_nis_longer_than_eight_digits_is_rejected(): void
    {
        Http::preventStrayRequests();
        $user = $this->createSuperAdmin();
        $branch = $this->createBranch();

        $response = $this->actingAs($user)->from(route('virtual-accounts.fixed.create'))
            ->post(route('virtual-accounts.fixed.store'), [
                'branch_id' => $branch->id,
                'va_number_mode' => 'automatic',
                'nis' => '123456789',
                'name' => 'Budi Santoso',
                'expired_at' => '2027-06-30T23:59',
            ]);

        $response->assertRedirect(route('virtual-accounts.fixed.create'));
        $response->assertSessionHasErrors('nis');
        $this->assertDatabaseCount('students', 0);
    }

    public function test_manual_va_with_other_than_sixteen_digits_is_rejected(): void
    {
        Http::preventStrayRequests();
        $user = $this->createSuperAdmin();
        $branch = $this->createBranch();

        $response = $this->actingAs($user)->from(route('virtual-accounts.fixed.create'))
            ->post(route('virtual-accounts.fixed.store'), [
                'branch_id' => $branch->id,
                'va_number_mode' => 'manual',
                'nis' => '01234567',
                'manual_va_number' => '857650100123456',
                'name' => 'Budi Santoso',
                'expired_at' => '2027-06-30T23:59',
            ]);

        $response->assertRedirect(route('virtual-accounts.fixed.create'));
        $response->assertSessionHasErrors('manual_va_number');
        $this->assertDatabaseCount('students', 0);
    }

    public function test_manual_va_longer_than_sixteen_digits_is_rejected(): void
    {
        Http::preventStrayRequests();
        $user = $this->createSuperAdmin();
        $branch = $this->createBranch();

        $response = $this->actingAs($user)->from(route('virtual-accounts.fixed.create'))
            ->post(route('virtual-accounts.fixed.store'), [
                'branch_id' => $branch->id,
                'va_number_mode' => 'manual',
                'nis' => '01234567',
                'manual_va_number' => '85765010012345678',
                'name' => 'Budi Santoso',
                'expired_at' => '2027-06-30T23:59',
            ]);

        $response->assertRedirect(route('virtual-accounts.fixed.create'));
        $response->assertSessionHasErrors('manual_va_number');
        $this->assertDatabaseCount('students', 0);
    }

    public function test_h2h_rejection_is_logged_and_does_not_mark_the_fixed_va_active(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://h2h.test/api/internal/va/create' => Http::response(['message' => 'Invalid request'], 422),
        ]);
        config([
            'services.mandiri_h2h.base_url' => 'https://h2h.test',
            'services.mandiri_h2h.api_key' => 'test-h2h-key',
        ]);
        $user = $this->createSuperAdmin();
        $branch = $this->createBranch();

        $response = $this->actingAs($user)->post(route('virtual-accounts.fixed.store'), [
            'branch_id' => $branch->id,
            'va_number_mode' => 'automatic',
            'nis' => '01234567',
            'name' => 'Budi Santoso',
            'parent_email' => 'wali.budi@example.com',
            'expired_at' => '2027-06-30T23:59',
        ]);

        $response->assertRedirect(route('virtual-accounts.fixed.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('virtual_accounts', [
            'virtual_account_no' => '8576501001234567',
            'status' => 'failed',
        ]);
        $this->assertDatabaseHas('h2h_api_logs', [
            'response_status' => 422,
            'endpoint' => '/api/internal/va/create',
        ]);
    }

    public function test_h2h_connection_failure_marks_fixed_va_for_verification_and_is_logged(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://h2h.test/api/internal/va/create' => Http::failedConnection(),
        ]);
        config([
            'services.mandiri_h2h.base_url' => 'https://h2h.test',
            'services.mandiri_h2h.api_key' => 'test-h2h-key',
        ]);
        $user = $this->createSuperAdmin();
        $branch = $this->createBranch();

        $response = $this->actingAs($user)->post(route('virtual-accounts.fixed.store'), [
            'branch_id' => $branch->id,
            'va_number_mode' => 'automatic',
            'nis' => '01234567',
            'name' => 'Budi Santoso',
            'parent_email' => 'wali.budi@example.com',
            'expired_at' => '2027-06-30T23:59',
        ]);

        $response->assertRedirect(route('virtual-accounts.fixed.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('virtual_accounts', [
            'virtual_account_no' => '8576501001234567',
            'status' => 'verification_required',
        ]);
        $this->assertDatabaseHas('h2h_api_logs', [
            'endpoint' => '/api/internal/va/create',
            'response_status' => null,
        ]);
    }

    public function test_fixed_va_list_displays_student_and_status(): void
    {
        $user = $this->createSuperAdmin();
        $branch = $this->createBranch();
        $student = Student::query()->create([
            'branch_id' => $branch->id,
            'nis' => '01234567',
            'name' => 'Budi Santoso',
            'parent_email' => 'wali.budi@example.com',
            'fixed_va_number' => '8576501001234567',
            'status' => 'active',
        ]);
        VirtualAccount::query()->create([
            'branch_id' => $branch->id,
            'student_id' => $student->id,
            'va_type' => 'fixed',
            'partner_service_id' => '85765',
            'customer_no' => '8576501001234567',
            'virtual_account_no' => '8576501001234567',
            'virtual_account_name' => $student->name,
            'current_amount' => '0.00',
            'expired_at' => '2027-06-30 23:59:00',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get(route('virtual-accounts.fixed.index'));

        $response->assertOk();
        $response->assertSee('Budi Santoso');
        $response->assertSee('8576501001234567');
        $response->assertSee('30 Jun 2027 23:59');
    }

    private function createSuperAdmin(): User
    {
        return User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
    }

    private function createBranch(): Branch
    {
        return Branch::query()->create([
            'name' => 'Bintaro',
            'code' => 'BTR',
            'va_fixed_prefix' => '85765010',
            'va_dynamic_prefix' => '85765991',
            'api_key' => str_repeat('b', 64),
            'is_active' => true,
        ]);
    }
}
