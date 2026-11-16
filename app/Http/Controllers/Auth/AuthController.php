<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Show the admin login form.
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            $user = Auth::user();

            if ($user->isAdmin()) {
                return redirect()->route('admin.dashboard');
            }

            if ($user->isTeacher()) {
                return redirect()->route('teacher.dashboard');
            }

            if ($user->isStudent()) {
                return redirect()->route('student.dashboard');
            }

            return redirect()->route('attendance.index');
        }

        return view('auth.login');
    }

    /**
     * Handle an authentication attempt for Admin.
     */
    public function login(Request $request): RedirectResponse
    {
        // 1. Validate inputs
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        // 2. Rate Limiting Protection (Max 5 attempts per minute)
        $throttleKey = Str::transliterate(Str::lower($request->input('email')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => "Terlalu banyak percobaan login. Silakan coba lagi dalam {$seconds} detik.",
            ]);
        }

        $remember = $request->boolean('remember');

        // 3. Attempt Authentication
        if (Auth::attempt($credentials, $remember)) {
            $user = Auth::user();

            // Check role and active status
            if ($user->role !== 'admin') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                RateLimiter::hit($throttleKey);

                return back()->withErrors([
                    'email' => 'Akses ditolak. Portal ini khusus untuk akun Administrator.',
                ])->onlyInput('email');
            }

            if ($user->status !== 'active') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                RateLimiter::hit($throttleKey);

                return back()->withErrors([
                    'email' => 'Akun Anda berstatus non-aktif. Silakan hubungi pengelola sistem.',
                ])->onlyInput('email');
            }

            // Success: clear rate limiter and regenerate session ID to prevent fixation
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            return redirect()->intended(route('admin.dashboard'))->with('success', 'Selamat datang kembali, '.$user->name.'!');
        }

        RateLimiter::hit($throttleKey);

        return back()->withErrors([
            'email' => 'Kombinasi email dan kata sandi yang Anda masukkan tidak sesuai.',
        ])->onlyInput('email');
    }

    /**
     * Log the user out of the application.
     *
     * Redirects back to the appropriate portal login page based on the user's role.
     */
    public function logout(Request $request): RedirectResponse
    {
        $role = Auth::user()?->role;

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = ['info' => 'Anda telah berhasil keluar dari sistem.'];

        return match ($role) {
            'student' => redirect()->route('student.login')->with($message),
            'teacher' => redirect()->route('teacher.login')->with($message),
            default => redirect()->route('login')->with($message),
        };
    }
}
