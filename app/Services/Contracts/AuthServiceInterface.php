<?php

namespace App\Services\Contracts;

interface AuthServiceInterface
{
    public const LOGIN_SUCCESS = 'success';

    public const LOGIN_INVALID_CREDENTIALS = 'invalid_credentials';

    public const LOGIN_UNAUTHORIZED = 'unauthorized';

    /**
     * @return self::LOGIN_*
     */
    public function login(string $email, string $password): string;

    public function logout(): void;
}
