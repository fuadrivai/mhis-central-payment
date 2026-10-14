<?php

namespace App\Providers;

use App\Services\Contracts\AuthServiceInterface;
use App\Services\Contracts\BranchServiceInterface;
use App\Services\Contracts\FixedVirtualAccountServiceInterface;
use App\Services\Contracts\PasswordServiceInterface;
use App\Services\Implementations\AuthService;
use App\Services\Implementations\BranchService;
use App\Services\Implementations\FixedVirtualAccountService;
use App\Services\Implementations\PasswordService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        PasswordServiceInterface::class => PasswordService::class,
        BranchServiceInterface::class => BranchService::class,
        FixedVirtualAccountServiceInterface::class => FixedVirtualAccountService::class,
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
        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(5)->by(
            Str::lower($request->string('email')->toString()).'|'.$request->ip(),
        ));

        RateLimiter::for('password-reset', fn (Request $request): Limit => Limit::perMinute(5)->by(
            Str::lower($request->string('email')->toString()).'|'.$request->ip(),
        ));
    }
}
