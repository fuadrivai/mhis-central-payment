<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreInvoiceApiRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Branch;
use App\Services\Contracts\InvoiceServiceInterface;
use App\Services\Contracts\VirtualAccountServiceInterface;
use Illuminate\Http\JsonResponse;

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

        return (new InvoiceResource($result['invoice']->load('virtualAccount.bank')))
            ->additional(['message' => $message])
            ->response()
            ->setStatusCode($status);
    }
}
