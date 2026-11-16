<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AttendanceAccessMiddleware
{
    /**
     * Handle an incoming request for attendance scanner / kiosk.
     * Restricts access strictly to authenticated active students and teachers.
     * Denies access to administrators and unauthenticated visitors.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Unauthenticated users must log in first
        if (! Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Autentikasi diperlukan. Silakan login sebagai Siswa atau Guru untuk mengakses scanner presensi.',
                ], 401);
            }

            return redirect()->route('login')->with('error', 'Silakan login sebagai Siswa atau Guru untuk mengakses scanner presensi.');
        }

        $user = Auth::user();

        // 2. Administrators are strictly forbidden from accessing the scanner directly
        if ($user->role === 'admin') {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Akses ditolak. Administrator tidak diperkenankan mengakses scanner presensi.',
                ], 403);
            }

            abort(403, 'Akses ditolak. Fitur scanner presensi hanya diperuntukkan bagi Siswa dan Guru.');
        }

        // 3. Only active students and teachers are allowed
        if (! in_array($user->role, ['student', 'teacher'], true) || $user->status !== 'active') {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Akses ditolak. Akun tidak aktif atau Anda tidak memiliki hak akses scanner presensi.',
                ], 403);
            }

            abort(403, 'Akses ditolak. Fitur scanner presensi hanya diperuntukkan bagi Siswa dan Guru yang berstatus aktif.');
        }

        return $next($request);
    }
}
