<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthenticateUserRequest;
use App\Services\Contracts\AuthServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(private readonly AuthServiceInterface $authService) {}

    public function index(): View
    {
        return view('auth.auth');
    }

    public function store(AuthenticateUserRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        $result = $this->authService->login($credentials['email'], $credentials['password']);

        return match ($result) {
            AuthServiceInterface::LOGIN_SUCCESS => redirect()->intended(route('home')),
            AuthServiceInterface::LOGIN_UNAUTHORIZED => back()
                ->withErrors(['email' => 'Your account does not have access to this application.']),
            default => back()
                ->withInput($request->safe()->only('email'))
                ->withErrors(['email' => 'The provided credentials are incorrect or the account is inactive.']),
        };
    }

    public function destroy(): RedirectResponse
    {
        $this->authService->logout();

        return redirect()->route('login')->with('status', 'You have been signed out successfully.');
    }
}
