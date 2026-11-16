<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TeacherAuthController extends Controller
{
    /**
     * Show the teacher login form.
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            $user = Auth::user();

            if ($user->isTeacher()) {
                return redirect()->route('teacher.dashboard');
            }
            if ($user->isAdmin()) {
                return redirect()->route('admin.dashboard');
            }
            if ($user->isStudent()) {
                return redirect()->route('student.dashboard');
            }

            return redirect()->route('attendance.index');
        }

        return view('auth.teacher-login');
    }

    /**
     * Handle an authentication attempt for Teacher.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        $throttleKey = Str::transliterate(Str::lower($request->input('email')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => "Terlalu banyak percobaan login. Silakan coba lagi dalam {$seconds} detik.",
            ]);
        }

        $remember = $request->boolean('remember');

        $user = User::query()
            ->where('email', $credentials['email'])
            ->where('role', 'teacher')
            ->first();

        if ($user !== null && Hash::check($credentials['password'], $user->password)) {
            if (! $user->isActive()) {
                RateLimiter::hit($throttleKey);

                return back()->withErrors([
                    'email' => 'Akun Guru Anda berstatus non-aktif. Silakan hubungi administrator sekolah.',
                ])->onlyInput('email');
            }

            Auth::login($user, $remember);
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            return redirect()->intended(route('teacher.dashboard'))->with('success', 'Selamat datang, '.$user->name.'!');
        }

        RateLimiter::hit($throttleKey);

        return back()->withErrors([
            'email' => 'Kombinasi email dan kata sandi tidak sesuai.',
        ])->onlyInput('email');
    }
}
