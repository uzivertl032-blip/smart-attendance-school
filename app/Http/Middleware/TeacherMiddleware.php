<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TeacherMiddleware
{
    /**
     * Handle an incoming request for teachers only.
     * Prevents unauthorized access, students, and administrators.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('teacher.login')->with('error', 'Silakan login terlebih dahulu untuk mengakses portal guru.');
        }

        $user = Auth::user();

        if ($user->role !== 'teacher' || $user->status !== 'active') {
            abort(403, 'Akses ditolak. Halaman ini hanya dapat diakses oleh Guru yang berwenang.');
        }

        return $next($request);
    }
}
