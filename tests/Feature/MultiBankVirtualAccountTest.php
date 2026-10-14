<?php

namespace Tests\Feature;

use App\Enums\VirtualAccountType;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Services\Contracts\VirtualAccountServiceInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MultiBankVirtualAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake(['https://h2h.test/api/internal/va/create' => Http::response(['status' => 'success'], 200)]);
        config([
            'services.mandiri_h2h.base_url' => 'https://h2h.test',
            'services.mandiri_h2h.api_key' => 'test-h2h-key',
            'services.mandiri_h2h.partner_service_id' => '85765',
        ]);
    }

    public function test_one_student_can_hold_fixed_vas_at_multiple_banks(): void
    {
        $branch = $this->createBranch();
        $mandiri = Bank::query()->where('code', 'mandiri')->firstOrFail();
        $secondBank = Bank::factory()->create(['driver' => 'mandiri']);
        $student = Student::query()->create([
            'branch_id' => $branch->id,
            'nis' => '01234567',
            'name' => 'Budi Santoso',
            'parent_email' => 'wali.budi@example.com',
            'status' => 'active',
        ]);

        foreach ([$mandiri, $secondBank] as $bank) {
            $result = $this->service()->createFixed([
                'bank_id' => $bank->id,
                'branch_id' => $branch->id,
                'student_id' => $student->id,
                'va_number_mode' => 'automatic',
                'expired_at' => '2027-06-30T23:59',
            ]);

            $this->assertSame(VirtualAccountServiceInterface::OUTCOME_ACTIVE, $result['outcome']);
        }

        $this->assertSame(2, $student->virtualAccounts()->where('va_type', 'fixed')->count());
        $this->assertDatabaseCount('students', 1);
    }

    public function test_same_va_number_cannot_repeat_within_a_bank_but_can_across_banks(): void
    {
        $branch = $this->createBranch();
        $mandiri = Bank::query()->where('code', 'mandiri')->firstOrFail();
        $secondBank = Bank::factory()->create();
        $student = Student::query()->create([
            'branch_id' => $branch->id,
            'nis' => '01234567',
            'name' => 'Budi Santoso',
            'parent_email' => 'wali.budi@example.com',
        ]);
        $attributes = [
            'branch_id' => $branch->id,
            'student_id' => $student->id,
            'va_type' => 'fixed',
            'partner_service_id' => '85765',
            'customer_no' => '8576501001234567',
            'virtual_account_no' => '8576501001234567',
            'virtual_account_name' => 'Budi Santoso',
        ];

        VirtualAccount::query()->create($attributes + ['bank_id' => $mandiri->id]);
        VirtualAccount::query()->create($attributes + ['bank_id' => $secondBank->id]);

        $this->expectException(QueryException::class);
        VirtualAccount::query()->create($attributes + ['bank_id' => $mandiri->id]);
    }

    public function test_request_can_add_a_va_to_an_existing_student_and_pick_the_bank(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $branch = $this->createBranch();
        $secondBank = Bank::factory()->create();
        $student = Student::query()->create([
            'branch_id' => $branch->id,
            'nis' => '01234567',
            'name' => 'Budi Santoso',
            'parent_email' => 'wali.budi@example.com',
        ]);

        $this->actingAs($user)->post(route('virtual-accounts.fixed.store'), [
            'bank_id' => $secondBank->id,
            'branch_id' => $branch->id,
            'student_id' => $student->id,
            'va_number_mode' => 'automatic',
            'expired_at' => '2999-06-30T23:59',
        ])->assertRedirect(route('virtual-accounts.fixed.index'))->assertSessionHas('status');

        $this->assertDatabaseHas('virtual_accounts', [
            'student_id' => $student->id,
            'bank_id' => $secondBank->id,
            'virtual_account_no' => '8576501001234567',
        ]);
        $this->assertDatabaseCount('students', 1);
    }

    public function test_bank_is_required_when_more_than_one_bank_is_active(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $branch = $this->createBranch();
        Bank::factory()->create();

        $this->actingAs($user)->post(route('virtual-accounts.fixed.store'), [
            'branch_id' => $branch->id,
            'va_number_mode' => 'automatic',
            'nis' => '01234567',
            'name' => 'Budi Santoso',
            'parent_email' => 'wali.budi@example.com',
            'expired_at' => '2999-06-30T23:59',
        ])->assertSessionHasErrors('bank_id');
    }

    public function test_dynamic_va_is_created_from_an_invoice(): void
    {
        $branch = $this->createBranch();
        $mandiri = Bank::query()->where('code', 'mandiri')->firstOrFail();
        $invoice = $this->createInvoice($branch);

        $result = $this->service()->createDynamic($invoice, $mandiri->id);

        $this->assertSame(VirtualAccountServiceInterface::OUTCOME_ACTIVE, $result['outcome']);
        $virtualAccount = VirtualAccount::query()->where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame(VirtualAccountType::Dynamic->value, $virtualAccount->va_type);
        $this->assertMatchesRegularExpression('/^85765991\d{8}$/', $virtualAccount->virtual_account_no);
        $this->assertDatabaseHas('virtual_accounts', [
            'invoice_id' => $invoice->id,
            'bank_id' => $mandiri->id,
            'va_type' => VirtualAccountType::Dynamic->value,
            'current_amount' => '1500000.00',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('h2h_api_logs', [
            'invoice_id' => $invoice->id,
            'bank_id' => $mandiri->id,
            'response_status' => 200,
        ]);
        Http::assertSent(fn (Request $request): bool => $request['totalAmount'] === '1500000.00'
            && $request['billCode'] === 'INV-001'
            && $request['expiredAt'] === '2999-01-31 23:59:00');
    }

    public function test_dynamic_va_cannot_be_duplicated_for_the_same_invoice_and_bank(): void
    {
        $branch = $this->createBranch();
        $mandiri = Bank::query()->where('code', 'mandiri')->firstOrFail();
        $invoice = $this->createInvoice($branch);

        $this->service()->createDynamic($invoice, $mandiri->id);

        $this->expectException(ValidationException::class);
        $this->service()->createDynamic($invoice, $mandiri->id);
    }

    public function test_dynamic_va_requires_a_pending_invoice(): void
    {
        $branch = $this->createBranch();
        $mandiri = Bank::query()->where('code', 'mandiri')->firstOrFail();
        $invoice = $this->createInvoice($branch, ['status' => 'paid']);

        $this->expectException(ValidationException::class);
        $this->service()->createDynamic($invoice, $mandiri->id);
    }

    private function service(): VirtualAccountServiceInterface
    {
        return app(VirtualAccountServiceInterface::class);
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createInvoice(Branch $branch, array $overrides = []): Invoice
    {
        return Invoice::query()->create($overrides + [
            'branch_id' => $branch->id,
            'va_type' => 'dynamic',
            'invoice_number' => 'INV-001',
            'source' => 'test',
            'bill_code' => 'INV-001',
            'bill_name' => 'Uang Pangkal',
            'customer_name' => 'Budi Santoso',
            'amount' => '1500000.00',
            'fee_amount' => '0.00',
            'total_amount' => '1500000.00',
            'status' => 'pending',
            'expired_at' => '2999-01-31 23:59:00',
        ]);
    }
}
