<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\SendPasswordResetLinkRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Services\Contracts\PasswordServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function __construct(private readonly PasswordServiceInterface $passwordService) {}

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(SendPasswordResetLinkRequest $request): RedirectResponse
    {
        $status = $this->passwordService->sendResetLink($request->safe()->string('email')->toString());

        return $status === Password::ResetLinkSent
            ? back()->with('status', __($status))
            : back()->withInput($request->safe()->only('email'))->withErrors(['email' => __($status)]);
    }

    public function edit(string $token): View
    {
        return view('auth.reset-password', ['token' => $token]);
    }

    public function update(ResetPasswordRequest $request): RedirectResponse
    {
        $status = $this->passwordService->reset(
            $request->safe()->only('email', 'password', 'password_confirmation', 'token'),
        );

        return $status === Password::PasswordReset
            ? redirect()->route('login')->with('status', __($status))
            : back()->withInput($request->safe()->only('email'))->withErrors(['email' => __($status)]);
    }

    public function editCurrent(): View
    {
        return view('auth.change-password');
    }

    public function updateCurrent(UpdatePasswordRequest $request): RedirectResponse
    {
        $this->passwordService->changePassword(
            $request->user(),
            $request->string('current_password')->toString(),
            $request->string('password')->toString(),
        );

        return redirect()->route('home')->with('status', 'Your password has been changed successfully.');
    }
}
