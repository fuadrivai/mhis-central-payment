<?php

namespace App\Services\Contracts;

use App\Models\User;

interface PasswordServiceInterface
{
    public function sendResetLink(string $email): string;

    /**
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $credentials
     */
    public function reset(array $credentials): string;

    public function changePassword(User $user, string $currentPassword, string $newPassword): void;
}
