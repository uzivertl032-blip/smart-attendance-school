<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu untuk mengakses panel admin.');
        }

        $user = Auth::user();

        if ($user->role !== 'admin' || $user->status !== 'active') {
            abort(403, 'Akses ditolak. Anda tidak memiliki hak akses sebagai Administrator.');
        }

        return $next($request);
    }
}
