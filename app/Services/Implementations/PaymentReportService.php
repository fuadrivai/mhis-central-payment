<?php

namespace App\Services\Implementations;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PaymentReportService
{
    /**
     * @return array<string, string>
     */
    public function reports(): array
    {
        return [
            'summary' => 'Ringkasan Pembayaran',
            'students' => 'Pembayaran per Siswa / NIS',
            'unpaid' => 'Tagihan Belum Dibayar',
            'reconciliations' => 'Rekonsiliasi Bank',
            'branches' => 'Ringkasan per Cabang',
            'issues' => 'Transaksi Bermasalah',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function columns(string $report): array
    {
        return match ($report) {
            'summary' => [
                'period' => 'Tanggal Pembayaran',
                'amount' => 'Total Pembayaran',
                'transaction_count' => 'Jumlah Transaksi',
            ],
            'students' => [
                'period' => 'Waktu Bayar',
                'branch' => 'Cabang',
                'bank' => 'Bank',
                'nis' => 'NIS',
                'student_name' => 'Nama Siswa',
                'identifier' => 'No. Invoice',
                'reference' => 'Referensi Pembayaran',
                'amount' => 'Nominal',
            ],
            'unpaid' => [
                'period' => 'Dibuat',
                'branch' => 'Cabang',
                'bank' => 'Bank',
                'nis' => 'NIS',
                'student_name' => 'Nama Siswa',
                'identifier' => 'No. Invoice',
                'description' => 'Nama Tagihan',
                'amount' => 'Nominal',
                'status' => 'Status',
            ],
            'reconciliations' => [
                'period' => 'Waktu Pembayaran',
                'branch' => 'Cabang',
                'bank' => 'Bank',
                'identifier' => 'Referensi Bank',
                'reference' => 'Referensi Sistem',
                'amount' => 'Nominal Bank',
                'status' => 'Status Rekonsiliasi',
            ],
            'branches' => [
                'branch' => 'Cabang',
                'bank' => 'Bank',
                'transaction_count' => 'Jumlah Transaksi',
                'amount' => 'Total Pembayaran',
            ],
            default => [
                'period' => 'Waktu',
                'description' => 'Jenis Masalah',
                'branch' => 'Cabang',
                'bank' => 'Bank',
                'identifier' => 'Invoice / VA',
                'nis' => 'NIS',
                'student_name' => 'Nama Siswa',
                'amount' => 'Nominal',
                'status' => 'Status',
                'reference' => 'Detail',
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(string $report, array $filters): Builder
    {
        return match ($report) {
            'summary' => $this->summaryQuery($filters),
            'students' => $this->studentQuery($filters),
            'unpaid' => $this->unpaidQuery($filters),
            'reconciliations' => $this->reconciliationQuery($filters),
            'branches' => $this->branchQuery($filters),
            default => $this->issueQuery($filters),
        };
    }

    /**
     * @return array{total_count: int, total_amount: string}
     */
    public function metrics(Builder $query, string $report): array
    {
        $countExpression = in_array($report, ['summary', 'branches'], true)
            ? 'COALESCE(SUM(report_rows.transaction_count), 0)'
            : 'COUNT(*)';
        $metrics = DB::query()
            ->fromSub(clone $query, 'report_rows')
            ->selectRaw($countExpression.' as total_count, COALESCE(SUM(report_rows.amount), 0) as total_amount')
            ->first();

        return [
            'total_count' => (int) $metrics->total_count,
            'total_amount' => (string) $metrics->total_amount,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function summaryQuery(array $filters): Builder
    {
        return $this->transactionQuery($filters)
            ->selectRaw("DATE(pt.paid_at) as period, 'Pembayaran diterima' as description")
            ->selectRaw('NULL as branch, NULL as bank, NULL as identifier, NULL as nis, NULL as student_name')
            ->selectRaw("SUM(pt.paid_amount) as amount, 'paid' as status, NULL as reference, COUNT(pt.id) as transaction_count")
            ->groupByRaw('DATE(pt.paid_at)')
            ->orderByDesc('period');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function studentQuery(array $filters): Builder
    {
        return $this->transactionQuery($filters)
            ->selectRaw('pt.paid_at as period, NULL as description, b.name as branch, k.name as bank')
            ->selectRaw('i.invoice_number as identifier, s.nis as nis, s.name as student_name')
            ->selectRaw("pt.paid_amount as amount, 'paid' as status, pt.payment_reference_no as reference, 1 as transaction_count")
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('s.nis', 'like', '%'.$search.'%')
                        ->orWhere('s.name', 'like', '%'.$search.'%')
                        ->orWhere('i.invoice_number', 'like', '%'.$search.'%')
                        ->orWhere('pt.payment_reference_no', 'like', '%'.$search.'%')
                        ->orWhere('va.virtual_account_no', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('pt.paid_at')
            ->orderByDesc('pt.id');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function branchQuery(array $filters): Builder
    {
        return $this->transactionQuery($filters)
            ->selectRaw('NULL as period, NULL as description, b.name as branch, k.name as bank')
            ->selectRaw('NULL as identifier, NULL as nis, NULL as student_name')
            ->selectRaw("SUM(pt.paid_amount) as amount, 'paid' as status, NULL as reference, COUNT(pt.id) as transaction_count")
            ->groupBy('pt.branch_id', 'b.name', 'pt.bank_id', 'k.name')
            ->orderByDesc('amount');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function unpaidQuery(array $filters): Builder
    {
        $query = DB::table('invoices as i')
            ->join('branches as b', 'b.id', '=', 'i.branch_id')
            ->leftJoin('virtual_accounts as va', 'va.invoice_id', '=', 'i.id')
            ->leftJoin('banks as k', 'k.id', '=', 'va.bank_id')
            ->leftJoin('students as s', 's.id', '=', 'i.student_id')
            ->whereIn('i.status', ['pending', 'expired'])
            ->selectRaw('i.created_at as period, i.bill_name as description, b.name as branch, k.name as bank')
            ->selectRaw('i.invoice_number as identifier, s.nis as nis, s.name as student_name')
            ->selectRaw("i.total_amount as amount, CASE WHEN i.status = 'expired' OR (i.expired_at IS NOT NULL AND i.expired_at <= ?) THEN 'expired' ELSE i.status END as status", [now()])
            ->selectRaw('i.bill_code as reference, NULL as transaction_count');

        $this->applyDateRange($query, 'i.created_at', $filters);
        $this->applyBranchBankAndType($query, 'i.branch_id', 'va.bank_id', 'i.va_type', $filters);

        if (($filters['status'] ?? null) === 'expired') {
            $query->where(function (Builder $query): void {
                $query->where('i.status', 'expired')
                    ->orWhere(function (Builder $query): void {
                        $query->where('i.status', 'pending')
                            ->whereNotNull('i.expired_at')
                            ->where('i.expired_at', '<=', now());
                    });
            });
        } elseif (($filters['status'] ?? null) === 'pending') {
            $query->where('i.status', 'pending')
                ->where(function (Builder $query): void {
                    $query->whereNull('i.expired_at')->orWhere('i.expired_at', '>', now());
                });
        }

        if (($filters['status'] ?? null) !== null && ! in_array($filters['status'], ['pending', 'expired'], true)) {
            $query->whereRaw('1 = 0');
        }

        if (($filters['search'] ?? '') !== '') {
            $search = $filters['search'];
            $query->where(function (Builder $query) use ($search): void {
                $query->where('i.invoice_number', 'like', '%'.$search.'%')
                    ->orWhere('i.bill_code', 'like', '%'.$search.'%')
                    ->orWhere('s.nis', 'like', '%'.$search.'%')
                    ->orWhere('s.name', 'like', '%'.$search.'%');
            });
        }

        return $query->orderByDesc('i.created_at')->orderByDesc('i.id');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function reconciliationQuery(array $filters): Builder
    {
        $query = DB::table('bank_reconciliation_items as ri')
            ->join('bank_reconciliations as br', 'br.id', '=', 'ri.bank_reconciliation_id')
            ->join('banks as k', 'k.id', '=', 'br.bank_id')
            ->leftJoin('payment_transactions as pt', 'pt.id', '=', 'ri.payment_transaction_id')
            ->leftJoin('branches as b', 'b.id', '=', 'pt.branch_id')
            ->leftJoin('invoices as i', 'i.id', '=', 'pt.invoice_id')
            ->leftJoin('virtual_accounts as va', 'va.id', '=', 'pt.virtual_account_id')
            ->selectRaw('COALESCE(ri.statement_paid_at, br.created_at) as period, NULL as description, b.name as branch, k.name as bank')
            ->selectRaw('ri.reference_no as identifier, NULL as nis, NULL as student_name')
            ->selectRaw('ri.statement_amount as amount, ri.status as status, pt.payment_reference_no as reference, 1 as transaction_count');

        $this->applyDateRange($query, 'COALESCE(ri.statement_paid_at, br.created_at)', $filters);
        $query->when($filters['branch_id'] ?? null, fn (Builder $query, int|string $branchId) => $query->where('pt.branch_id', $branchId))
            ->when($filters['bank_id'] ?? null, fn (Builder $query, int|string $bankId) => $query->where('br.bank_id', $bankId))
            ->when($filters['va_type'] ?? null, fn (Builder $query, string $vaType) => $query->where('va.va_type', $vaType))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('ri.status', $status))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('ri.reference_no', 'like', '%'.$search.'%')
                        ->orWhere('pt.payment_reference_no', 'like', '%'.$search.'%');
                });
            });

        return $query->orderByDesc('period')->orderByDesc('ri.id');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function issueQuery(array $filters): Builder
    {
        $h2h = DB::table('h2h_api_logs as h')
            ->leftJoin('branches as b', 'b.id', '=', 'h.branch_id')
            ->leftJoin('banks as k', 'k.id', '=', 'h.bank_id')
            ->leftJoin('invoices as i', 'i.id', '=', 'h.invoice_id')
            ->leftJoin('virtual_accounts as va', 'va.id', '=', 'h.virtual_account_id')
            ->leftJoin('students as s', 's.id', '=', 'va.student_id')
            ->where(function (Builder $query): void {
                $query->whereNull('h.response_status')
                    ->orWhere('h.response_status', '<', 200)
                    ->orWhere('h.response_status', '>=', 300)
                    ->orWhereNotNull('h.error_message');
            })
            ->selectRaw("'Bank API error' as description, b.name as branch, k.name as bank")
            ->selectRaw('COALESCE(i.invoice_number, va.virtual_account_no, h.endpoint) as identifier, s.nis as nis, s.name as student_name')
            ->selectRaw("i.total_amount as amount, COALESCE(CAST(h.response_status AS CHAR), 'unknown') as status, h.error_message as reference")
            ->addSelect('h.created_at as period')
            ->addSelect(DB::raw('1 as transaction_count'));
        $this->applyDateRange($h2h, 'h.created_at', $filters);
        $h2h->when($filters['branch_id'] ?? null, fn (Builder $query, int|string $branchId) => $query->where('h.branch_id', $branchId))
            ->when($filters['bank_id'] ?? null, fn (Builder $query, int|string $bankId) => $query->where('h.bank_id', $bankId))
            ->when($filters['va_type'] ?? null, fn (Builder $query, string $vaType) => $query->where('va.va_type', $vaType))
            ->when($filters['status'] ?? null, function (Builder $query, string $status): void {
                if (ctype_digit($status)) {
                    $query->where('h.response_status', (int) $status);
                } else {
                    $query->whereRaw('1 = 0');
                }
            })
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('i.invoice_number', 'like', '%'.$search.'%')
                        ->orWhere('s.nis', 'like', '%'.$search.'%')
                        ->orWhere('h.endpoint', 'like', '%'.$search.'%');
                });
            });

        $virtualAccounts = DB::table('virtual_accounts as va')
            ->join('branches as b', 'b.id', '=', 'va.branch_id')
            ->leftJoin('banks as k', 'k.id', '=', 'va.bank_id')
            ->leftJoin('invoices as i', 'i.id', '=', 'va.invoice_id')
            ->leftJoin('students as s', 's.id', '=', 'va.student_id')
            ->whereIn('va.status', ['failed', 'verification_required'])
            ->selectRaw("'Virtual account issue' as description, b.name as branch, k.name as bank")
            ->selectRaw('COALESCE(i.invoice_number, va.virtual_account_no) as identifier, s.nis as nis, s.name as student_name')
            ->selectRaw('i.total_amount as amount, va.status as status, NULL as reference')
            ->addSelect('va.created_at as period')
            ->addSelect(DB::raw('1 as transaction_count'));
        $this->applyDateRange($virtualAccounts, 'va.created_at', $filters);
        $virtualAccounts->when($filters['branch_id'] ?? null, fn (Builder $query, int|string $branchId) => $query->where('va.branch_id', $branchId))
            ->when($filters['bank_id'] ?? null, fn (Builder $query, int|string $bankId) => $query->where('va.bank_id', $bankId))
            ->when($filters['va_type'] ?? null, fn (Builder $query, string $vaType) => $query->where('va.va_type', $vaType))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('va.status', $status))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('i.invoice_number', 'like', '%'.$search.'%')
                        ->orWhere('s.nis', 'like', '%'.$search.'%')
                        ->orWhere('va.virtual_account_no', 'like', '%'.$search.'%');
                });
            });

        $webhooks = DB::table('branch_webhook_deliveries as wd')
            ->join('branches as b', 'b.id', '=', 'wd.branch_id')
            ->join('payment_transactions as pt', 'pt.id', '=', 'wd.payment_transaction_id')
            ->join('invoices as i', 'i.id', '=', 'pt.invoice_id')
            ->leftJoin('banks as k', 'k.id', '=', 'pt.bank_id')
            ->leftJoin('virtual_accounts as va', 'va.id', '=', 'pt.virtual_account_id')
            ->leftJoin('students as s', 's.id', '=', 'va.student_id')
            ->where(function (Builder $query): void {
                $query->where('wd.status', 'failed')
                    ->orWhere(function (Builder $query): void {
                        $query->where('wd.status', 'pending')->where('wd.attempts', '>', 0);
                    });
            })
            ->selectRaw("'Branch callback issue' as description, b.name as branch, k.name as bank")
            ->selectRaw('i.invoice_number as identifier, s.nis as nis, s.name as student_name')
            ->selectRaw('pt.paid_amount as amount, wd.status as status, pt.payment_reference_no as reference')
            ->addSelect(DB::raw('COALESCE(wd.last_attempt_at, wd.created_at) as period'))
            ->addSelect(DB::raw('1 as transaction_count'));
        $this->applyDateRange($webhooks, 'COALESCE(wd.last_attempt_at, wd.created_at)', $filters);
        $webhooks->when($filters['branch_id'] ?? null, fn (Builder $query, int|string $branchId) => $query->where('wd.branch_id', $branchId))
            ->when($filters['bank_id'] ?? null, fn (Builder $query, int|string $bankId) => $query->where('pt.bank_id', $bankId))
            ->when($filters['va_type'] ?? null, fn (Builder $query, string $vaType) => $query->where('va.va_type', $vaType))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('wd.status', $status))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('i.invoice_number', 'like', '%'.$search.'%')
                        ->orWhere('s.nis', 'like', '%'.$search.'%')
                        ->orWhere('pt.payment_reference_no', 'like', '%'.$search.'%');
                });
            });

        $callbackFailures = DB::table('audit_logs as al')
            ->leftJoin('banks as k', function ($join): void {
                $join->on('k.id', '=', 'al.actor_id')->where('al.actor_type', '=', 'bank');
            })
            ->leftJoin('branches as b', function ($join): void {
                $join->on('b.id', '=', 'al.actor_id')->where('al.actor_type', '=', 'branch');
            })
            ->where('al.event', 'api.request')
            ->where('al.route_name', 'api.v1.callbacks.mandiri.virtual-account-paid')
            ->where('al.status', '>=', 400)
            ->selectRaw("'Payment callback failure' as description, b.name as branch, k.name as bank")
            ->selectRaw('al.route_name as identifier, NULL as nis, NULL as student_name, NULL as amount')
            ->selectRaw('CAST(al.status AS CHAR) as status, NULL as reference')
            ->addSelect('al.created_at as period')
            ->addSelect(DB::raw('1 as transaction_count'));
        $this->applyDateRange($callbackFailures, 'al.created_at', $filters);
        if (($filters['branch_id'] ?? null) !== null || ($filters['va_type'] ?? null) !== null) {
            $callbackFailures->whereRaw('1 = 0');
        }
        $callbackFailures->when($filters['bank_id'] ?? null, fn (Builder $query, int|string $bankId) => $query->where('k.id', $bankId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('al.status', $status));

        $union = $h2h
            ->unionAll($virtualAccounts)
            ->unionAll($webhooks)
            ->unionAll($callbackFailures);
        $query = DB::query()->fromSub($union, 'report_rows')->select('report_rows.*');

        return $query->orderByDesc('period')->orderByDesc('identifier');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function transactionQuery(array $filters): Builder
    {
        $query = DB::table('payment_transactions as pt')
            ->join('invoices as i', 'i.id', '=', 'pt.invoice_id')
            ->join('virtual_accounts as va', 'va.id', '=', 'pt.virtual_account_id')
            ->join('branches as b', 'b.id', '=', 'pt.branch_id')
            ->leftJoin('banks as k', 'k.id', '=', 'pt.bank_id')
            ->leftJoin('students as s', 's.id', '=', 'va.student_id');

        $this->applyDateRange($query, 'pt.paid_at', $filters);
        $this->applyBranchBankAndType($query, 'pt.branch_id', 'pt.bank_id', 'va.va_type', $filters);
        $query->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('i.status', $status));

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyBranchBankAndType(
        Builder $query,
        string $branchColumn,
        string $bankColumn,
        string $typeColumn,
        array $filters,
    ): void {
        $query->when($filters['branch_id'] ?? null, fn (Builder $query, int|string $id) => $query->where($branchColumn, $id))
            ->when($filters['bank_id'] ?? null, fn (Builder $query, int|string $id) => $query->where($bankColumn, $id))
            ->when($filters['va_type'] ?? null, fn (Builder $query, string $type) => $query->where($typeColumn, $type));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyDateRange(Builder $query, string $column, array $filters): void
    {
        $query->whereRaw($column.' >= ?', [$filters['from'].' 00:00:00'])
            ->whereRaw($column.' < ?', [
                CarbonImmutable::createFromFormat('!Y-m-d', $filters['to'])->addDay()->toDateString(),
            ]);
    }
}
