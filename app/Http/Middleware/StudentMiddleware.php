<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class StudentMiddleware
{
    /**
     * Handle an incoming request for students only.
     * Prevents unauthorized access, teachers, and administrators.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('student.login')->with('error', 'Silakan login terlebih dahulu untuk mengakses portal siswa.');
        }

        $user = Auth::user();

        if ($user->role !== 'student' || $user->status !== 'active') {
            abort(403, 'Akses ditolak. Halaman ini hanya dapat diakses oleh Siswa yang aktif.');
        }

        return $next($request);
    }
}
