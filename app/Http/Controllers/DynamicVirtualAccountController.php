<?php

namespace App\Http\Controllers;

use App\Enums\VirtualAccountType;
use App\Services\Contracts\VirtualAccountServiceInterface;
use Illuminate\View\View;

class DynamicVirtualAccountController extends Controller
{
    public function __construct(private readonly VirtualAccountServiceInterface $virtualAccountService) {}

    public function index(): View
    {
        return view('virtual-accounts.dynamic.index', [
            'virtualAccounts' => $this->virtualAccountService->paginate(VirtualAccountType::Dynamic),
        ]);
    }
}
