<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthenticateUserRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function index(): View
    {
        return view('auth.auth');
    }

    public function store(AuthenticateUserRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'is_active' => true,
        ])) {
            return back()
                ->withInput($request->safe()->only('email'))
                ->withErrors(['email' => 'The provided credentials are incorrect or the account is inactive.']);
        }

        $request->session()->regenerate();

        if (! $request->user()->hasRole('super_admin')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'Your account does not have access to this application.']);
        }

        return redirect()->intended(route('home'));
    }

    public function destroy(): RedirectResponse
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been signed out successfully.');
    }
}
