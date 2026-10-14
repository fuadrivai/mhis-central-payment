<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreFixedVirtualAccountApiRequest;
use App\Http\Resources\VirtualAccountResource;
use App\Services\Contracts\VirtualAccountServiceInterface;
use Illuminate\Http\JsonResponse;

class FixedVirtualAccountController extends Controller
{
    public function __construct(private readonly VirtualAccountServiceInterface $virtualAccountService) {}

    public function store(StoreFixedVirtualAccountApiRequest $request): JsonResponse
    {
        $result = $this->virtualAccountService->createFixed($request->validated());

        [$status, $message] = match ($result['outcome']) {
            VirtualAccountServiceInterface::OUTCOME_ACTIVE => [201, 'Fixed VA berhasil dibuat dan terdaftar di bank.'],
            VirtualAccountServiceInterface::OUTCOME_REJECTED => [422, 'Bank menolak pembuatan Fixed VA.'],
            VirtualAccountServiceInterface::OUTCOME_SERVER_ERROR => [502, 'Backend bank mengalami kendala. Status VA perlu diverifikasi sebelum mencoba kembali.'],
            default => [202, 'Hasil pembuatan VA di bank belum dapat dipastikan. Jangan kirim ulang sebelum statusnya diperiksa.'],
        };

        return (new VirtualAccountResource($result['virtualAccount']->load(['bank', 'student'])))
            ->additional(['message' => $message])
            ->response()
            ->setStatusCode($status);
    }
}
