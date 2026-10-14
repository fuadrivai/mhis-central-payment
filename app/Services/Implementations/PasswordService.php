<?php

namespace App\Services\Implementations;

use App\Models\User;
use App\Services\Contracts\PasswordServiceInterface;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordService implements PasswordServiceInterface
{
    public function sendResetLink(string $email): string
    {
        return Password::sendResetLink(['email' => $email]);
    }

    public function reset(array $credentials): string
    {
        return Password::reset(
            $credentials,
            function ($user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        Auth::logoutOtherDevices($currentPassword);

        $user->forceFill(['password' => Hash::make($newPassword)])->save();

        session()->regenerate();
    }
}
