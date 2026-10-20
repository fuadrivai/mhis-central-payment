<?php

namespace App\Services\Implementations;

use App\Enums\VirtualAccountType;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\PaymentType;
use App\Models\Student;
use App\Services\Contracts\InvoiceServiceInterface;
use App\Services\Contracts\VirtualAccountServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceService implements InvoiceServiceInterface
{
    public function __construct(private readonly VirtualAccountServiceInterface $virtualAccounts) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Invoice::query();

        $this->applyIndexFilters($query, $filters);

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyIndexFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $searchColumns = [
            'bill_code' => 'bill_code',
            'invoice_number' => 'invoice_number',
            'email' => 'email',
        ];
        $searchColumn = $searchColumns[$filters['search_by'] ?? 'bill_code'] ?? 'bill_code';

        if ($search !== '') {
            $query->where($searchColumn, 'like', '%'.$search.'%');
        }

        $dateColumns = [
            'paid_at' => 'paid_at',
            'created_at' => 'created_at',
            'expired_at' => 'expired_at',
        ];

        foreach ($filters['date_filters'] ?? [] as $dateFilter) {
            $column = $dateColumns[$dateFilter] ?? null;
            $range = $filters['date_ranges'][$dateFilter] ?? [];

            if ($column === null) {
                continue;
            }

            if (! empty($range['from'])) {
                $query->where($column, '>=', Carbon::parse($range['from'])->startOfDay());
            }

            if (! empty($range['to'])) {
                $query->where($column, '<=', Carbon::parse($range['to'])->endOfDay());
            }
        }

        $statuses = $filters['statuses'] ?? [];

        if ($statuses !== []) {
            $query->where(function (Builder $statusQuery) use ($statuses): void {
                foreach ($statuses as $status) {
                    if ($status === 'expired') {
                        $statusQuery
                            ->orWhere('status', 'expired')
                            ->orWhere(function (Builder $pendingQuery): void {
                                $pendingQuery
                                    ->where('status', 'pending')
                                    ->whereNotNull('expired_at')
                                    ->where('expired_at', '<=', now());
                            });

                        continue;
                    }

                    if ($status === 'pending') {
                        $statusQuery->orWhere(function (Builder $pendingQuery): void {
                            $pendingQuery
                                ->where('status', 'pending')
                                ->where(function (Builder $expiryQuery): void {
                                    $expiryQuery
                                        ->whereNull('expired_at')
                                        ->orWhere('expired_at', '>', now());
                                });
                        });

                        continue;
                    }

                    $statusQuery->orWhere('status', $status);
                }
            });
        }

        if (($filters['created_from'] ?? []) !== []) {
            $query->whereIn('source', $filters['created_from']);
        }

        if (isset($filters['amount']) && $filters['amount'] !== '') {
            $operator = match ($filters['amount_operator'] ?? 'equals') {
                'greater' => '>',
                'less' => '<',
                default => '=',
            };

            $query->where('total_amount', $operator, $filters['amount']);
        }
    }

    public function detail(Invoice $invoice): Invoice
    {
        return $invoice->load([
            'branch:id,name,code',
            'student:id,nis,name,grade_class',
            'paymentType:id,code,name',
            'virtualAccount.bank:id,name,code',
            'paymentTransactions' => fn ($query) => $query
                ->with([
                    'bank:id,name,code',
                    'branch:id,name,code',
                    'virtualAccount:id,virtual_account_no,virtual_account_name,customer_no,va_type,status',
                ])
                ->latest('paid_at'),
        ]);
    }

    public function paymentPage(Invoice $invoice): Invoice
    {
        return $invoice->load([
            'virtualAccount.bank:id,name,code',
        ]);
    }

    public function voidInvoice(Invoice $invoice): string
    {
        return $this->virtualAccounts->voidInvoice($invoice);
    }

    public function create(array $data): array
    {
        $branch = Branch::query()->active()->findOrFail($data['branch_id']);

        $student = isset($data['student_id'])
            ? Student::query()->where('branch_id', $branch->id)->findOrFail($data['student_id'])
            : null;

        $paymentType = isset($data['payment_type'])
            ? PaymentType::query()->where('code', $data['payment_type'])->firstOrFail()
            : null;

        $amount = number_format((float) $data['amount'], 2, '.', '');
        $feeAmount = number_format((float) ($data['fee_amount'] ?? 0), 2, '.', '');
        $totalAmount = number_format((float) $amount + (float) $feeAmount, 2, '.', '');

        $invoice = DB::transaction(fn (): Invoice => Invoice::query()->create([
            'branch_id' => $branch->id,
            'student_id' => $student?->id,
            'payment_type_id' => $paymentType?->id,
            'va_type' => VirtualAccountType::Dynamic->value,
            'invoice_number' => $this->generateInvoiceNumber(),
            'source' => $data['source'] ?? 'api',
            'bill_code' => $data['bill_code'],
            'bill_name' => $data['bill_name'],
            'customer_name' => $data['customer_name'] ?? $student?->name,
            'customer_phone' => $data['customer_phone'] ?? $student?->parent_phone,
            'email' => $data['email'],
            'amount' => $amount,
            'fee_amount' => $feeAmount,
            'total_amount' => $totalAmount,
            'status' => 'pending',
            'expired_at' => Carbon::parse($data['expired_at'])->toDateTimeString(),
        ]));

        $result = $this->virtualAccounts->createDynamic($invoice, (int) $data['bank_id']);

        return [...$result, 'invoice' => $invoice];
    }

    private function generateInvoiceNumber(): string
    {
        do {
            $number = 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (Invoice::query()->where('invoice_number', $number)->exists());

        return $number;
    }
}
