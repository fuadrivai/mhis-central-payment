<?php

namespace App\Services\Contracts;

use App\Models\PaymentTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PaymentTransactionServiceInterface
{
    /**
     * Paginate recent payment transactions and search their identifiers.
     */
    public function paginate(?string $search = null, int $perPage = 15): LengthAwarePaginator;

    /**
     * Load all related data required by the transaction detail page.
     */
    public function detail(PaymentTransaction $paymentTransaction): PaymentTransaction;
}
