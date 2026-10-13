<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\SendPasswordResetLinkRequest;
use App\Http\Requests\UpdatePasswordRequest;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(SendPasswordResetLinkRequest $request): RedirectResponse
    {
        $status = Password::sendResetLink($request->safe()->only('email'));

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
        $status = Password::reset(
            $request->safe()->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
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
        $user = $request->user();

        Auth::logoutOtherDevices($request->string('current_password')->toString());

        $user->forceFill([
            'password' => Hash::make($request->string('password')->toString()),
        ])->save();

        $request->session()->regenerate();

        return redirect()->route('home')->with('status', 'Your password has been changed successfully.');
    }
}
