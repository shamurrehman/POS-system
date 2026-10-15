<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        $key = Str::lower($credentials['username']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'username' => "Too many failed attempts. Try again in {$seconds} seconds.",
            ]);
        }

        $ok = Auth::attempt([
            'username' => $credentials['username'],
            'password' => $credentials['password'],
            'is_active' => true,
        ]);

        if (! $ok) {
            RateLimiter::hit($key, 300);
            $user = User::where('username', $credentials['username'])->first();
            ActivityLog::record('auth.failed', "Failed login for username '{$credentials['username']}'", $user, $user?->id);

            throw ValidationException::withMessages(['username' => 'Invalid username or password, or the account is disabled.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();
        ActivityLog::record('auth.login', "{$user->name} logged in");

        $home = $user->hasRole('cashier') ? route('pos') : route('dashboard');

        return redirect()->intended($home);
    }

    public function logout(Request $request)
    {
        ActivityLog::record('auth.logout', ($request->user()?->name ?? 'User').' logged out');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
