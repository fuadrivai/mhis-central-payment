<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\Student;
use App\Models\User;
use App\Models\VirtualAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Testing\File;
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

    public function test_super_admin_can_view_bulk_import_form_and_download_csv_template(): void
    {
        $user = $this->createSuperAdmin();
        $branch = $this->createBranch();
        $bank = $this->mandiri();

        $this->actingAs($user)
            ->get(route('virtual-accounts.fixed.bulk.create'))
            ->assertOk()
            ->assertSee('name="branch_id"', false)
            ->assertSee('name="bank_id"', false)
            ->assertSee('name="student_file"', false)
            ->assertSee('id="bulk-upload-overlay"', false)
            ->assertSee('Sedang memproses data')
            ->assertSee('bulkUploadStarted = true', false)
            ->assertSee($branch->name)
            ->assertSee($bank->name)
            ->assertSee('nis,nama_siswa,grade,email,tgl_expired_va');

        $template = $this->get(route('virtual-accounts.fixed.bulk.template'));

        $template->assertOk()
            ->assertDownload('fixed-va-students-template.csv');
        $this->assertSame("nis,nama_siswa,grade,email,tgl_expired_va\n", $template->streamedContent());
    }

    public function test_super_admin_can_import_fixed_va_csv_using_selected_branch_prefix(): void
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
        $bank = $this->mandiri();
        $csv = implode("\n", [
            'nis,nama_siswa,grade,email,tgl_expired_va',
            '01234567,Budi Santoso,Grade 3A,"wali.budi@example.com,keluarga.budi@example.com",2027-06-30',
            '76543210,Siti Aminah,Grade 5B,wali.siti@example.com,2027-07-31 23:59',
        ]);

        $response = $this->actingAs($user)->post(route('virtual-accounts.fixed.bulk.store'), [
            'branch_id' => $branch->id,
            'bank_id' => $bank->id,
            'student_file' => File::createWithContent('fixed-va-students.csv', $csv),
        ]);

        $response->assertRedirect(route('virtual-accounts.fixed.index'))
            ->assertSessionHas('status', 'Import selesai: 2 dari 2 Fixed VA berhasil aktif di backend bank.')
            ->assertSessionHas('bulk_fixed_va_failures', []);
        $this->assertDatabaseHas('students', [
            'branch_id' => $branch->id,
            'nis' => '01234567',
            'name' => 'Budi Santoso',
            'grade_class' => 'Grade 3A',
            'parent_email' => 'wali.budi@example.com',
            'additional_email' => 'keluarga.budi@example.com',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('virtual_accounts', [
            'bank_id' => $bank->id,
            'virtual_account_no' => '8576501001234567',
            'va_type' => 'fixed',
            'expired_at' => '2027-06-30 23:59:00',
            'status' => 'active',
        ]);
        $this->assertDatabaseCount('students', 2);
        $this->assertDatabaseCount('virtual_accounts', 2);
        $this->assertDatabaseCount('h2h_api_logs', 2);
        Http::assertSentCount(2);
    }

    public function test_bulk_import_continues_after_h2h_rejection_and_reports_failed_row(): void
    {
        $this->travelTo('2026-10-13 10:00:00');
        Http::preventStrayRequests();
        $attempt = 0;
        Http::fake([
            'https://h2h.test/api/internal/va/create' => function () use (&$attempt) {
                $attempt++;

                return Http::response(
                    ['status' => $attempt === 1 ? 'failed' : 'success'],
                    $attempt === 1 ? 422 : 201,
                );
            },
        ]);
        config([
            'services.mandiri_h2h.base_url' => 'https://h2h.test',
            'services.mandiri_h2h.api_key' => 'test-h2h-key',
            'services.mandiri_h2h.partner_service_id' => '85765',
        ]);

        $user = $this->createSuperAdmin();
        $branch = $this->createBranch();
        $bank = $this->mandiri();
        $csv = implode("\n", [
            'nis,nama_siswa,grade,email,tgl_expired_va',
            '01234567,Budi Santoso,Grade 3A,wali.budi@example.com,2027-06-30',
            '76543210,Siti Aminah,Grade 5B,wali.siti@example.com,2027-07-31',
        ]);

        $response = $this->actingAs($user)->post(route('virtual-accounts.fixed.bulk.store'), [
            'branch_id' => $branch->id,
            'bank_id' => $bank->id,
            'student_file' => File::createWithContent('fixed-va-students.csv', $csv),
        ]);

        $response->assertRedirect(route('virtual-accounts.fixed.index'))
            ->assertSessionHas('status', 'Import selesai: 1 dari 2 Fixed VA berhasil aktif di backend bank.')
            ->assertSessionHas('bulk_fixed_va_failures', [
                ['row' => 2, 'nis' => '01234567', 'outcome' => 'rejected'],
            ]);
        $this->assertDatabaseHas('virtual_accounts', [
            'virtual_account_no' => '8576501001234567',
            'status' => 'failed',
        ]);
        $this->assertDatabaseHas('virtual_accounts', [
            'virtual_account_no' => '8576501076543210',
            'status' => 'active',
        ]);
        Http::assertSentCount(2);
    }

    public function test_invalid_bulk_csv_does_not_create_students_or_call_h2h(): void
    {
        Http::preventStrayRequests();

        $user = $this->createSuperAdmin();
        $branch = $this->createBranch();
        $bank = $this->mandiri();
        $csv = implode("\n", [
            'nis,nama_siswa,grade,email,tgl_expired_va',
            '01234567,Budi Santoso,Grade 3A,wali.budi@example.com,2027-06-30',
            '01234567,Siti Aminah,Grade 5B,wali.siti@example.com,2027-07-31',
        ]);

        $response = $this->actingAs($user)->from(route('virtual-accounts.fixed.bulk.create'))
            ->post(route('virtual-accounts.fixed.bulk.store'), [
                'branch_id' => $branch->id,
                'bank_id' => $bank->id,
                'student_file' => File::createWithContent('fixed-va-students.csv', $csv),
            ]);

        $response->assertRedirect(route('virtual-accounts.fixed.bulk.create'))
            ->assertSessionHasErrors('student_file');
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('virtual_accounts', 0);
        Http::assertSentCount(0);
    }

    public function test_bulk_import_requires_fixed_va_create_permission(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create([
            'role' => 'finance_pusat',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('virtual-accounts.fixed.bulk.create'))
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('virtual-accounts.fixed.bulk.store'))
            ->assertForbidden();

        Http::assertSentCount(0);
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
            'parent_email' => 'wali.budi@example.com',
            'additional_email' => null,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('virtual_accounts', [
            'branch_id' => $branch->id,
            'bank_id' => $this->mandiri()->id,
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
            'status' => 'active',
        ]);
        VirtualAccount::query()->create([
            'branch_id' => $branch->id,
            'bank_id' => $this->mandiri()->id,
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

    public function test_fixed_va_pagination_uses_bootstrap_and_navigates_to_the_next_page(): void
    {
        $user = $this->createSuperAdmin();
        $branch = $this->createBranch();
        $bank = $this->mandiri();

        for ($index = 1; $index <= 16; $index++) {
            $nis = sprintf('%08d', $index);
            $vaNumber = $branch->va_fixed_prefix.$nis;
            $student = Student::query()->create([
                'branch_id' => $branch->id,
                'nis' => $nis,
                'name' => 'Siswa '.$index,
                'parent_email' => 'siswa'.$index.'@example.com',
                'status' => 'active',
            ]);

            VirtualAccount::query()->create([
                'branch_id' => $branch->id,
                'bank_id' => $bank->id,
                'student_id' => $student->id,
                'va_type' => 'fixed',
                'partner_service_id' => '85765',
                'customer_no' => $vaNumber,
                'virtual_account_no' => $vaNumber,
                'virtual_account_name' => $student->name,
                'current_amount' => '0.00',
                'expired_at' => '2027-06-30 23:59:00',
                'status' => 'active',
            ]);
        }

        $firstPage = $this->actingAs($user)->get(route('virtual-accounts.fixed.index', ['search' => 'Siswa']));

        $firstPage->assertOk()
            ->assertSee('class="pagination"', false)
            ->assertSee('class="page-link"', false)
            ->assertSee('page=2', false)
            ->assertSee('search=Siswa', false)
            ->assertSee('Siswa 16')
            ->assertDontSee('Siswa 1</span>');

        $this->get(route('virtual-accounts.fixed.index', ['search' => 'Siswa', 'page' => 2]))
            ->assertOk()
            ->assertSee('Siswa 1')
            ->assertDontSee('Siswa 16');
    }

    public function test_fixed_va_search_filters_by_student_name_nis_va_number_and_branch(): void
    {
        $user = $this->createSuperAdmin();
        $branch = $this->createBranch();
        $bank = $this->mandiri();
        $matchingVirtualAccount = $this->createFixedVirtualAccount($branch, $bank, '01234567', 'Budi Santoso');
        $this->createFixedVirtualAccount($branch, $bank, '76543210', 'Siti Aminah');

        $this->actingAs($user)
            ->get(route('virtual-accounts.fixed.index', ['search' => '01234567']))
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee($matchingVirtualAccount->virtual_account_no)
            ->assertDontSee('Siti Aminah');

        $this->get(route('virtual-accounts.fixed.index', ['search' => 'Budi Santoso']))
            ->assertOk()
            ->assertSee($matchingVirtualAccount->virtual_account_no)
            ->assertDontSee('Siti Aminah');

        $this->get(route('virtual-accounts.fixed.index', ['search' => $matchingVirtualAccount->virtual_account_no]))
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertDontSee('Siti Aminah');

        $this->get(route('virtual-accounts.fixed.index', ['search' => 'Bintaro']))
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('Siti Aminah');
    }

    public function test_super_admin_can_edit_active_fixed_va_and_sync_changes_to_h2h(): void
    {
        $this->travelTo('2026-10-13 10:00:00');
        Http::preventStrayRequests();
        Http::fake([
            'https://h2h.test/api/internal/va/update' => Http::response(['status' => 'success'], 200),
        ]);
        config([
            'services.mandiri_h2h.base_url' => 'https://h2h.test',
            'services.mandiri_h2h.api_key' => 'test-h2h-key',
            'services.mandiri_h2h.partner_service_id' => '85765',
        ]);

        $user = $this->createSuperAdmin();
        $branch = $this->createBranch();
        $bank = $this->mandiri();
        $virtualAccount = $this->createFixedVirtualAccount($branch, $bank, '01234567', 'Budi Santoso');

        $this->actingAs($user)
            ->get(route('virtual-accounts.fixed.edit', $virtualAccount))
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('name="grade_class"', false)
            ->assertSee('name="parent_email"', false)
            ->assertSee('name="additional_email"', false);

        $this->put(route('virtual-accounts.fixed.update', $virtualAccount), [
            'name' => 'Budi Santoso Baru',
            'grade_class' => 'Grade 4A',
            'parent_email' => 'wali.budi@example.com',
            'additional_email' => 'keluarga.budi@example.com',
            'expired_at' => '2027-08-31T23:59',
        ])
            ->assertRedirect(route('virtual-accounts.fixed.index'))
            ->assertSessionHas('status', 'Data Fixed VA berhasil diperbarui di sistem dan backend bank.');

        $this->assertDatabaseHas('students', [
            'id' => $virtualAccount->student_id,
            'name' => 'Budi Santoso Baru',
            'grade_class' => 'Grade 4A',
            'parent_email' => 'wali.budi@example.com',
            'additional_email' => 'keluarga.budi@example.com',
        ]);
        $this->assertDatabaseHas('virtual_accounts', [
            'id' => $virtualAccount->id,
            'virtual_account_name' => 'Budi Santoso Baru',
            'expired_at' => '2027-08-31 23:59:00',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('h2h_api_logs', [
            'virtual_account_id' => $virtualAccount->id,
            'endpoint' => '/api/internal/va/update',
            'http_method' => 'PUT',
            'response_status' => 200,
        ]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://h2h.test/api/internal/va/update'
            && $request->data() === [
                'virtualAccountNo' => '8576501001234567',
                'customerNo' => '8576501001234567',
                'virtualAccountName' => 'Budi Santoso Baru',
                'totalAmount' => '0.00',
                'feeAmount' => '0.00',
                'billCode' => 'FIXED-BTR-01234567',
                'billName' => 'Fixed VA SPP - Budi Santoso Baru',
                'expiredAt' => '2027-08-31 23:59:00',
                'status' => 'ACTIVE',
            ]);
    }

    public function test_bank_rejection_keeps_fixed_va_data_unchanged_when_editing(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://h2h.test/api/internal/va/update' => Http::response(['message' => 'rejected'], 422),
        ]);
        config([
            'services.mandiri_h2h.base_url' => 'https://h2h.test',
            'services.mandiri_h2h.api_key' => 'test-h2h-key',
            'services.mandiri_h2h.partner_service_id' => '85765',
        ]);

        $user = $this->createSuperAdmin();
        $branch = $this->createBranch();
        $bank = $this->mandiri();
        $virtualAccount = $this->createFixedVirtualAccount($branch, $bank, '01234567', 'Budi Santoso');

        $this->actingAs($user)
            ->put(route('virtual-accounts.fixed.update', $virtualAccount), [
                'name' => 'Budi Santoso Baru',
                'grade_class' => 'Grade 4A',
                'parent_email' => 'wali.budi@example.com',
                'additional_email' => null,
                'expired_at' => '2027-08-31T23:59',
            ])
            ->assertRedirect(route('virtual-accounts.fixed.index'))
            ->assertSessionHas('error', 'Backend H2H menolak perubahan Fixed VA. Data lokal tidak diubah.');

        $this->assertDatabaseHas('students', [
            'id' => $virtualAccount->student_id,
            'name' => 'Budi Santoso',
            'grade_class' => null,
        ]);
        $this->assertDatabaseHas('virtual_accounts', [
            'id' => $virtualAccount->id,
            'virtual_account_name' => 'Budi Santoso',
            'status' => 'active',
        ]);
        Http::assertSentCount(1);
    }

    public function test_invalid_fixed_va_edit_is_rejected_before_calling_h2h(): void
    {
        Http::preventStrayRequests();
        $user = $this->createSuperAdmin();
        $branch = $this->createBranch();
        $bank = $this->mandiri();
        $virtualAccount = $this->createFixedVirtualAccount($branch, $bank, '01234567', 'Budi Santoso');

        $this->actingAs($user)
            ->from(route('virtual-accounts.fixed.edit', $virtualAccount))
            ->put(route('virtual-accounts.fixed.update', $virtualAccount), [
                'name' => 'Budi Santoso Baru',
                'grade_class' => 'Grade 4A',
                'parent_email' => 'alamat-email-tidak-valid',
                'additional_email' => null,
                'expired_at' => '2027-08-31T23:59',
            ])
            ->assertRedirect(route('virtual-accounts.fixed.edit', $virtualAccount))
            ->assertSessionHasErrors([
                'parent_email' => 'Email utama harus berupa alamat email yang valid.',
            ]);

        $this->assertDatabaseHas('students', [
            'id' => $virtualAccount->student_id,
            'name' => 'Budi Santoso',
        ]);
        $this->assertDatabaseHas('virtual_accounts', [
            'id' => $virtualAccount->id,
            'status' => 'active',
        ]);
        Http::assertSentCount(0);
    }

    public function test_editing_fixed_va_marks_it_for_verification_when_h2h_result_is_unknown(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://h2h.test/api/internal/va/update' => Http::failedConnection(),
        ]);
        config([
            'services.mandiri_h2h.base_url' => 'https://h2h.test',
            'services.mandiri_h2h.api_key' => 'test-h2h-key',
            'services.mandiri_h2h.partner_service_id' => '85765',
        ]);

        $user = $this->createSuperAdmin();
        $branch = $this->createBranch();
        $bank = $this->mandiri();
        $virtualAccount = $this->createFixedVirtualAccount($branch, $bank, '01234567', 'Budi Santoso');

        $this->actingAs($user)
            ->put(route('virtual-accounts.fixed.update', $virtualAccount), [
                'name' => 'Budi Santoso Baru',
                'grade_class' => 'Grade 4A',
                'parent_email' => 'wali.budi@example.com',
                'additional_email' => null,
                'expired_at' => '2027-08-31T23:59',
            ])
            ->assertRedirect(route('virtual-accounts.fixed.index'))
            ->assertSessionHas('error', 'Tidak dapat memastikan hasil perubahan di H2H. Status VA perlu diverifikasi sebelum perubahan dicoba kembali.');

        $this->assertDatabaseHas('students', [
            'id' => $virtualAccount->student_id,
            'name' => 'Budi Santoso',
        ]);
        $this->assertDatabaseHas('virtual_accounts', [
            'id' => $virtualAccount->id,
            'virtual_account_name' => 'Budi Santoso',
            'status' => 'verification_required',
        ]);
        $this->assertDatabaseHas('h2h_api_logs', [
            'virtual_account_id' => $virtualAccount->id,
            'endpoint' => '/api/internal/va/update',
            'response_status' => null,
        ]);
    }

    public function test_edit_fixed_va_requires_create_permission(): void
    {
        $user = User::factory()->create([
            'role' => 'finance_pusat',
            'is_active' => true,
        ]);
        $branch = $this->createBranch();
        $bank = $this->mandiri();
        $virtualAccount = $this->createFixedVirtualAccount($branch, $bank, '01234567', 'Budi Santoso');

        $this->actingAs($user)
            ->get(route('virtual-accounts.fixed.edit', $virtualAccount))
            ->assertForbidden();
        $this->actingAs($user)
            ->put(route('virtual-accounts.fixed.update', $virtualAccount), [])
            ->assertForbidden();
    }

    private function mandiri(): Bank
    {
        return Bank::query()->where('code', 'mandiri')->firstOrFail();
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

    private function createFixedVirtualAccount(Branch $branch, Bank $bank, string $nis, string $name): VirtualAccount
    {
        $student = Student::query()->create([
            'branch_id' => $branch->id,
            'nis' => $nis,
            'name' => $name,
            'parent_email' => 'wali.'.$nis.'@example.com',
            'status' => 'active',
        ]);
        $vaNumber = $branch->va_fixed_prefix.$nis;

        return VirtualAccount::query()->create([
            'branch_id' => $branch->id,
            'bank_id' => $bank->id,
            'student_id' => $student->id,
            'va_type' => 'fixed',
            'partner_service_id' => '85765',
            'customer_no' => $vaNumber,
            'virtual_account_no' => $vaNumber,
            'virtual_account_name' => $name,
            'current_amount' => '0.00',
            'expired_at' => '2027-06-30 23:59:00',
            'status' => 'active',
        ]);
    }
}
