<?php

namespace App\Services\Contracts;

use App\Models\BranchWebhookDelivery;
use App\Models\PaymentTransaction;

interface BranchWebhookDeliveryServiceInterface
{
    public function createForPaymentTransaction(PaymentTransaction $paymentTransaction): BranchWebhookDelivery;

    public function retry(PaymentTransaction $paymentTransaction): BranchWebhookDelivery;
}
