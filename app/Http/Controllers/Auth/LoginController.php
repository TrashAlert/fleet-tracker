<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('fleet.dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // Rate limit: 5 attempts per minute per IP+email combo
        $throttleKey = Str::lower($request->email) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            ActivityLogger::logEvent(
                'login_throttled',
                "Login throttled for {$request->email} — too many attempts",
                'User', null, $request->email,
                ['ip' => $request->ip()]
            );

            return back()->withErrors([
                'email' => "Too many login attempts. Please wait {$seconds} seconds.",
            ])->onlyInput('email');
        }

        $credentials = $request->only('email', 'password');
        $remember    = $request->boolean('remember');

        // Check if user exists and is active before attempting auth
        $user = \App\Models\User::where('email', $request->email)->first();

        if ($user && ! $user->is_active) {
            RateLimiter::hit($throttleKey);

            ActivityLogger::logEvent(
                'login_inactive',
                "Login attempt by inactive account: {$request->email}",
                'User', $user->id, $user->name,
                ['ip' => $request->ip()]
            );

            return back()->withErrors([
                'email' => 'This account has been deactivated. Contact your administrator.',
            ])->onlyInput('email');
        }

        if (Auth::attempt($credentials, $remember)) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            // Record last login
            Auth::user()->update(['last_login_at' => now()]);

            ActivityLogger::logEvent(
                'login_success',
                Auth::user()->name . ' (' . Auth::user()->role . ') logged in',
                'User', Auth::id(), Auth::user()->name,
                ['ip' => $request->ip(), 'role' => Auth::user()->role]
            );

            return redirect()->intended(route('fleet.dashboard'));
        }

        RateLimiter::hit($throttleKey);

        ActivityLogger::logEvent(
            'login_failed',
            "Failed login attempt for email: {$request->email}",
            'User', null, $request->email,
            ['ip' => $request->ip()]
        );

        return back()->withErrors([
            'email' => 'Invalid email or password.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        $name = Auth::user()->name ?? 'Unknown';
        $id   = Auth::id();

        ActivityLogger::logEvent(
            'logout',
            "{$name} logged out",
            'User', $id, $name
        );

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('auth.login');
    }
}
