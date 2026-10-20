<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreInvoiceApiRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Branch;
use App\Models\Invoice;
use App\Services\Contracts\InvoiceServiceInterface;
use App\Services\Contracts\VirtualAccountServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceServiceInterface $invoiceService) {}

    public function store(StoreInvoiceApiRequest $request): JsonResponse
    {
        /** @var Branch $branch */
        $branch = $request->attributes->get('branch');

        $result = $this->invoiceService->create([...$request->validated(), 'branch_id' => $branch->id]);

        [$status, $message] = match ($result['outcome']) {
            VirtualAccountServiceInterface::OUTCOME_ACTIVE => [201, 'Invoice dan VA berhasil dibuat.'],
            VirtualAccountServiceInterface::OUTCOME_REJECTED => [422, 'Invoice dibuat, tetapi bank menolak pembuatan VA.'],
            VirtualAccountServiceInterface::OUTCOME_SERVER_ERROR => [502, 'Invoice dibuat, tetapi backend bank mengalami kendala. Status VA perlu diverifikasi.'],
            default => [202, 'Invoice dibuat, tetapi hasil pembuatan VA di bank belum dapat dipastikan.'],
        };

        $invoice = $result['invoice']->load('virtualAccount.bank');
        $invoiceUrl = null;

        if (
            $invoice->status === 'pending'
            && $invoice->expired_at?->isFuture()
            && $invoice->virtualAccount?->status === 'active'
        ) {
            $invoiceUrl = URL::temporarySignedRoute(
                'payment.show',
                $invoice->expired_at,
                ['invoice' => $invoice],
            );
        }

        return (new InvoiceResource($invoice))
            ->additional([
                'message' => $message,
                'id' => $invoice->id,
                'status' => strtoupper($invoice->status),
                'invoice_url' => $invoiceUrl,
                'created' => $invoice->created_at?->toIso8601String(),
                'expiry_date' => $invoice->expired_at?->toIso8601String(),
            ])
            ->response()
            ->setStatusCode($status);
    }

    public function expire(Request $request, string $transId): JsonResponse
    {
        /** @var Branch $branch */
        $branch = $request->attributes->get('branch');

        $invoice = Invoice::query()
            ->where('branch_id', $branch->id)
            ->where('bill_code', $transId)
            ->first();

        if ($invoice === null && ctype_digit($transId)) {
            $invoice = Invoice::query()
                ->where('branch_id', $branch->id)
                ->whereKey((int) $transId)
                ->first();
        }

        abort_if($invoice === null, 404);

        $outcome = $this->invoiceService->voidInvoice($invoice);
        $invoice->refresh()->load('virtualAccount.bank');

        [$status, $success, $message] = match ($outcome) {
            VirtualAccountServiceInterface::OUTCOME_VOIDED => [
                200,
                true,
                'Invoice berhasil dinonaktifkan.',
            ],
            VirtualAccountServiceInterface::OUTCOME_REJECTED => [
                422,
                false,
                'Bank menolak permintaan penonaktifan invoice.',
            ],
            VirtualAccountServiceInterface::OUTCOME_SERVER_ERROR => [
                502,
                false,
                'Backend bank mengalami kendala. Status VA perlu diverifikasi.',
            ],
            default => [
                202,
                false,
                'Hasil penonaktifan belum dapat dipastikan. Status VA perlu diverifikasi.',
            ],
        };

        return (new InvoiceResource($invoice))
            ->additional([
                'success' => $success,
                'message' => $message,
                'id' => $invoice->id,
                'status' => strtoupper($invoice->status),
            ])
            ->response()
            ->setStatusCode($status);
    }
}
