<?php

namespace App\Services\Implementations;

use App\Services\Contracts\AuthServiceInterface;
use Illuminate\Support\Facades\Auth;

class AuthService implements AuthServiceInterface
{
    public function login(string $email, string $password): string
    {
        if (! Auth::attempt(['email' => $email, 'password' => $password, 'is_active' => true])) {
            return self::LOGIN_INVALID_CREDENTIALS;
        }

        session()->regenerate();

        if (! Auth::user()->hasRole('super_admin')) {
            $this->logout();

            return self::LOGIN_UNAUTHORIZED;
        }

        return self::LOGIN_SUCCESS;
    }

    public function logout(): void
    {
        Auth::logout();

        session()->invalidate();
        session()->regenerateToken();
    }
}
