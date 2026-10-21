<?php

namespace App\Providers;

use App\Services\Contracts\AuthServiceInterface;
use App\Services\Contracts\BankReconciliationServiceInterface;
use App\Services\Contracts\BranchServiceInterface;
use App\Services\Contracts\BranchWebhookDeliveryServiceInterface;
use App\Services\Contracts\CallbackApiKeyServiceInterface;
use App\Services\Contracts\InvoiceServiceInterface;
use App\Services\Contracts\PasswordServiceInterface;
use App\Services\Contracts\PaymentNotificationServiceInterface;
use App\Services\Contracts\PaymentTransactionServiceInterface;
use App\Services\Contracts\VirtualAccountServiceInterface;
use App\Services\Implementations\AuthService;
use App\Services\Implementations\BankReconciliationService;
use App\Services\Implementations\BranchService;
use App\Services\Implementations\BranchWebhookDeliveryService;
use App\Services\Implementations\CallbackApiKeyService;
use App\Services\Implementations\InvoiceService;
use App\Services\Implementations\PasswordService;
use App\Services\Implementations\PaymentNotificationService;
use App\Services\Implementations\PaymentTransactionService;
use App\Services\Implementations\VirtualAccountService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        AuthServiceInterface::class => AuthService::class,
        BankReconciliationServiceInterface::class => BankReconciliationService::class,
        BranchWebhookDeliveryServiceInterface::class => BranchWebhookDeliveryService::class,
        PasswordServiceInterface::class => PasswordService::class,
        BranchServiceInterface::class => BranchService::class,
        CallbackApiKeyServiceInterface::class => CallbackApiKeyService::class,
        VirtualAccountServiceInterface::class => VirtualAccountService::class,
        InvoiceServiceInterface::class => InvoiceService::class,
        PaymentNotificationServiceInterface::class => PaymentNotificationService::class,
        PaymentTransactionServiceInterface::class => PaymentTransactionService::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        RateLimiter::for('branch-api', fn (Request $request): Limit => Limit::perMinute(60)->by(
            $request->attributes->get('branch')?->id ?? $request->ip(),
        ));

        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(5)->by(
            Str::lower($request->string('email')->toString()).'|'.$request->ip(),
        ));

        RateLimiter::for('password-reset', fn (Request $request): Limit => Limit::perMinute(5)->by(
            Str::lower($request->string('email')->toString()).'|'.$request->ip(),
        ));
    }
}
