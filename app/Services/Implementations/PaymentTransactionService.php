<?php

namespace App\Services\Implementations;

use App\Models\PaymentTransaction;
use App\Services\Contracts\PaymentTransactionServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PaymentTransactionService implements PaymentTransactionServiceInterface
{
    public function paginate(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = PaymentTransaction::query()
            ->with([
                'bank:id,name',
                'branch:id,name',
                'invoice:id,invoice_number,bill_code',
                'virtualAccount:id,virtual_account_no',
            ]);

        $search = trim((string) $search);

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query
                    ->where('payment_reference_no', 'like', '%'.$search.'%')
                    ->orWhere('payment_request_id', 'like', '%'.$search.'%')
                    ->orWhereHas('invoice', function (Builder $invoiceQuery) use ($search): void {
                        $invoiceQuery
                            ->where('invoice_number', 'like', '%'.$search.'%')
                            ->orWhere('bill_code', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('virtualAccount', function (Builder $virtualAccountQuery) use ($search): void {
                        $virtualAccountQuery->where('virtual_account_no', 'like', '%'.$search.'%');
                    });
            });
        }

        return $query
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends(['search' => $search]);
    }

    public function detail(PaymentTransaction $paymentTransaction): PaymentTransaction
    {
        return $paymentTransaction->load([
            'bank:id,name,code',
            'branch:id,name,code',
            'invoice:id,invoice_number,bill_code,bill_name,status,total_amount',
            'virtualAccount:id,virtual_account_no,virtual_account_name,customer_no,va_type,status',
            'webhookDelivery',
        ]);
    }
}
