<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFixedVirtualAccountRequest;
use App\Models\Branch;
use App\Models\H2hApiLog;
use App\Models\Student;
use App\Models\VirtualAccount;
use App\Services\MandiriH2hClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FixedVirtualAccountController extends Controller
{
    public function index(): View
    {
        $virtualAccounts = VirtualAccount::query()
            ->with(['branch:id,name,code', 'student:id,branch_id,nis,name,status'])
            ->where('va_type', 'fixed')
            ->orderByDesc('id')
            ->paginate(15);

        return view('virtual-accounts.fixed.index', [
            'virtualAccounts' => $virtualAccounts,
        ]);
    }

    public function create(): View
    {
        return view('virtual-accounts.fixed.create', [
            'branches' => Branch::query()->active()->orderBy('name')->get(['id', 'name', 'code', 'va_fixed_prefix']),
        ]);
    }

    public function store(
        StoreFixedVirtualAccountRequest $request,
        MandiriH2hClient $mandiriH2hClient,
    ): RedirectResponse {
        if (! $mandiriH2hClient->isConfigured()) {
            Log::error('Cannot create fixed VA because Mandiri H2H configuration is incomplete.');

            abort(503, 'Integrasi Mandiri H2H belum dikonfigurasi. Hubungi administrator sistem.');
        }

        $validated = $request->validated();
        $branch = Branch::query()->active()->findOrFail($validated['branch_id']);
        $vaNumber = $validated['va_number_mode'] === 'automatic'
            ? $branch->va_fixed_prefix.$validated['nis']
            : $validated['manual_va_number'];

        [$student, $virtualAccount] = DB::transaction(function () use ($branch, $validated, $vaNumber): array {
            $student = Student::query()->create([
                'branch_id' => $branch->id,
                'nis' => $validated['nis'],
                'nisn' => $validated['nisn'] ?? null,
                'name' => $validated['name'],
                'grade_class' => $validated['grade_class'] ?? null,
                'parent_name' => $validated['parent_name'] ?? null,
                'parent_email' => $validated['parent_email'],
                'additional_email' => $validated['additional_email'] ?? null,
                'parent_phone' => $validated['parent_phone'] ?? null,
                'fixed_va_number' => $vaNumber,
                'status' => 'pending_h2h',
            ]);

            $virtualAccount = VirtualAccount::query()->create([
                'branch_id' => $branch->id,
                'student_id' => $student->id,
                'va_type' => 'fixed',
                'partner_service_id' => (string) config('services.mandiri_h2h.partner_service_id'),
                'customer_no' => $vaNumber,
                'virtual_account_no' => $vaNumber,
                'virtual_account_name' => $student->name,
                'current_amount' => '0.00',
                'expired_at' => str_replace('T', ' ', $validated['expired_at']).':00',
                'status' => 'pending_h2h',
            ]);

            return [$student, $virtualAccount];
        });

        $payload = [
            'partnerServiceId' => (string) config('services.mandiri_h2h.partner_service_id'),
            'customerNo' => $vaNumber,
            'virtualAccountNo' => $vaNumber,
            'virtualAccountName' => $student->name,
            'totalAmount' => '0.00',
            'feeAmount' => '0.00',
            'billCode' => 'FIXED-'.$branch->code.'-'.$student->nis,
            'billName' => 'Fixed VA SPP - '.$student->name,
            'expiredAt' => str_replace('T', ' ', $validated['expired_at']).':00',
        ];
        $startedAt = hrtime(true);

        try {
            $response = $mandiriH2hClient->createVirtualAccount($payload);
        } catch (ConnectionException $exception) {
            $durationMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);
            $virtualAccount->update(['status' => 'verification_required']);

            H2hApiLog::query()->create([
                'branch_id' => $branch->id,
                'virtual_account_id' => $virtualAccount->id,
                'endpoint' => '/api/internal/va/create',
                'http_method' => 'POST',
                'request_payload' => $payload,
                'duration_ms' => $durationMs,
                'error_message' => Str::limit($exception->getMessage(), 2000),
            ]);

            Log::warning('Mandiri H2H connection outcome is unknown for fixed VA creation.', [
                'branch_id' => $branch->id,
                'virtual_account_id' => $virtualAccount->id,
            ]);

            return redirect()
                ->route('virtual-accounts.fixed.index')
                ->with('error', 'Tidak dapat memastikan hasil pembuatan VA dari H2H. VA ditandai perlu verifikasi; jangan kirim ulang sebelum statusnya diperiksa.');
        }

        $responsePayload = $response->json();
        if (! is_array($responsePayload)) {
            $responsePayload = ['body' => Str::limit($response->body(), 5000)];
        }

        H2hApiLog::query()->create([
            'branch_id' => $branch->id,
            'virtual_account_id' => $virtualAccount->id,
            'endpoint' => '/api/internal/va/create',
            'http_method' => 'POST',
            'response_status' => $response->status(),
            'request_payload' => $payload,
            'response_payload' => $responsePayload,
            'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
        ]);

        if (! $response->successful()) {
            $virtualAccount->update([
                'status' => $response->serverError() ? 'verification_required' : 'failed',
                'h2h_response_raw' => $responsePayload,
            ]);

            return redirect()
                ->route('virtual-accounts.fixed.index')
                ->with('error', $response->serverError()
                    ? 'Backend H2H mengalami kendala. Status VA perlu diverifikasi sebelum mencoba kembali.'
                    : 'Backend H2H menolak pembuatan Fixed VA. Periksa log integrasi untuk detailnya.');
        }

        $virtualAccount->update([
            'status' => 'active',
            'h2h_response_raw' => $responsePayload,
        ]);
        $student->update(['status' => 'active']);

        return redirect()
            ->route('virtual-accounts.fixed.index')
            ->with('status', 'Fixed VA berhasil dibuat dan terdaftar di backend Mandiri H2H.');
    }
}
