<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FixedVirtualAccountApiTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'branch-api-key-1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'services.mandiri_h2h.base_url' => 'https://h2h.test',
            'services.mandiri_h2h.api_key' => 'test-h2h-key',
            'services.mandiri_h2h.partner_service_id' => '85765',
        ]);
    }

    public function test_request_without_valid_api_key_is_rejected(): void
    {
        $this->postJson(route('api.v1.virtual-accounts.fixed.store'), $this->payload())->assertUnauthorized();
        $this->postJson(route('api.v1.virtual-accounts.fixed.store'), $this->payload(), ['X-API-KEY' => 'wrong'])
            ->assertUnauthorized();
    }

    public function test_inactive_branch_api_key_is_rejected(): void
    {
        $this->createBranch(['is_active' => false]);

        $this->postJson(route('api.v1.virtual-accounts.fixed.store'), $this->payload(), $this->headers())
            ->assertUnauthorized();
    }

    public function test_fixed_va_is_created_for_the_branch_that_owns_the_api_key(): void
    {
        Http::fake(['https://h2h.test/api/internal/va/create' => Http::response(['status' => 'success'], 200)]);
        $branch = $this->createBranch();
        $otherBranch = $this->createBranch(['code' => 'OTH', 'va_fixed_prefix' => '85765011', 'va_dynamic_prefix' => '85765992', 'api_key' => 'other-key']);

        $this->postJson(route('api.v1.virtual-accounts.fixed.store'), $this->payload(['branch_id' => $otherBranch->id]), $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.virtual_account_no', '8576501001234567')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.va_type', 'fixed')
            ->assertJsonPath('data.bank.code', 'mandiri')
            ->assertJsonPath('data.student.nis', '01234567')
            ->assertJsonPath('message', 'Fixed VA berhasil dibuat dan terdaftar di bank.');

        $this->assertDatabaseHas('virtual_accounts', ['branch_id' => $branch->id, 'virtual_account_no' => '8576501001234567']);
        $this->assertDatabaseMissing('students', ['branch_id' => $otherBranch->id]);
    }

    public function test_api_can_add_a_va_to_an_existing_student(): void
    {
        Http::fake(['https://h2h.test/api/internal/va/create' => Http::response(['status' => 'success'], 200)]);
        $branch = $this->createBranch();
        $student = Student::query()->create([
            'branch_id' => $branch->id,
            'nis' => '01234567',
            'name' => 'Budi Santoso',
            'parent_email' => 'wali.budi@example.com',
        ]);

        $this->postJson(route('api.v1.virtual-accounts.fixed.store'), [
            'student_id' => $student->id,
            'va_number_mode' => 'automatic',
            'expired_at' => '2999-06-30 23:59',
        ], $this->headers())->assertCreated();

        $this->assertSame(1, Student::query()->count());
        $this->assertSame(1, $student->virtualAccounts()->count());
    }

    public function test_student_from_another_branch_cannot_be_used(): void
    {
        $this->createBranch();
        $otherBranch = $this->createBranch(['code' => 'OTH', 'va_fixed_prefix' => '85765011', 'va_dynamic_prefix' => '85765992', 'api_key' => 'other-key']);
        $student = Student::query()->create([
            'branch_id' => $otherBranch->id,
            'nis' => '09999999',
            'name' => 'Siswa Lain',
            'parent_email' => 'lain@example.com',
        ]);

        $this->postJson(route('api.v1.virtual-accounts.fixed.store'), [
            'student_id' => $student->id,
            'va_number_mode' => 'automatic',
            'expired_at' => '2999-06-30 23:59',
        ], $this->headers())->assertUnprocessable()->assertJsonValidationErrors('student_id');
    }

    public function test_validation_errors_are_returned_as_json(): void
    {
        $this->createBranch();

        $this->postJson(route('api.v1.virtual-accounts.fixed.store'), ['expired_at' => '2999-06-30T23:59'], $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['va_number_mode', 'nis', 'name', 'parent_email', 'expired_at']);
    }

    public function test_bank_rejection_returns_422_and_marks_va_failed(): void
    {
        Http::fake(['https://h2h.test/api/internal/va/create' => Http::response(['message' => 'Invalid'], 422)]);
        $this->createBranch();

        $this->postJson(route('api.v1.virtual-accounts.fixed.store'), $this->payload(), $this->headers())
            ->assertUnprocessable()
            ->assertJsonPath('data.status', 'failed');
    }

    public function test_bank_server_error_returns_502(): void
    {
        Http::fake(['https://h2h.test/api/internal/va/create' => Http::response(['message' => 'down'], 500)]);
        $this->createBranch();

        $this->postJson(route('api.v1.virtual-accounts.fixed.store'), $this->payload(), $this->headers())
            ->assertStatus(502)
            ->assertJsonPath('data.status', 'verification_required');
    }

    public function test_unknown_bank_outcome_returns_202_with_verification_status(): void
    {
        Http::fake(['https://h2h.test/api/internal/va/create' => Http::failedConnection()]);
        $this->createBranch();

        $this->postJson(route('api.v1.virtual-accounts.fixed.store'), $this->payload(), $this->headers())
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'verification_required');
    }

    public function test_bank_must_be_specified_when_several_banks_are_active(): void
    {
        $this->createBranch();
        Bank::factory()->create();

        $this->postJson(route('api.v1.virtual-accounts.fixed.store'), $this->payload(), $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('bank_id');
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['X-API-KEY' => self::API_KEY];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'va_number_mode' => 'automatic',
            'nis' => '01234567',
            'name' => 'Budi Santoso',
            'parent_email' => 'wali.budi@example.com',
            'expired_at' => '2999-06-30 23:59',
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createBranch(array $overrides = []): Branch
    {
        return Branch::query()->create($overrides + [
            'name' => 'Bintaro',
            'code' => 'BTR',
            'va_fixed_prefix' => '85765010',
            'va_dynamic_prefix' => '85765991',
            'api_key' => self::API_KEY,
            'is_active' => true,
        ]);
    }
}
