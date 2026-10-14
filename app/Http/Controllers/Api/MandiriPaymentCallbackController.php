<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MandiriPaymentCallbackRequest;
use App\Services\Contracts\PaymentNotificationServiceInterface;
use Illuminate\Http\JsonResponse;

class MandiriPaymentCallbackController extends Controller
{
    public function __construct(private readonly PaymentNotificationServiceInterface $paymentNotifications) {}

    public function store(MandiriPaymentCallbackRequest $request): JsonResponse
    {
        $outcome = $this->paymentNotifications->process($request->validated());

        [$status, $message] = match ($outcome) {
            PaymentNotificationServiceInterface::OUTCOME_PROCESSED => [200, 'Payment notification processed.'],
            PaymentNotificationServiceInterface::OUTCOME_DUPLICATE => [200, 'Payment notification already processed.'],
            PaymentNotificationServiceInterface::OUTCOME_NOT_FOUND => [404, 'Virtual account or invoice was not found.'],
            PaymentNotificationServiceInterface::OUTCOME_CONFLICT => [409, 'Payment notification conflicts with the invoice or VA state.'],
            default => [422, 'Payment notification data does not match the invoice.'],
        };

        return response()->json([
            'outcome' => $outcome,
            'message' => $message,
        ], $status);
    }
}
