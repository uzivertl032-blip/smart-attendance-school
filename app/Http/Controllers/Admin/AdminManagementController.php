<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AdminManagementController extends Controller
{
    /**
     * Display a listing of administrator accounts.
     */
    public function index(Request $request): View
    {
        $search = $request->input('search');

        $admins = User::where('role', 'admin')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.admins.index', compact('admins', 'search'));
    }

    /**
     * Show the form for creating a new administrator account.
     */
    public function create(): View
    {
        return view('admin.admins.create');
    }

    /**
     * Store a newly created administrator account in storage.
     */
    public function store(StoreAdminRequest $request): RedirectResponse
    {
        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'admin',
            'status' => $request->status,
        ]);

        return redirect()->route('admin.admins.index')
            ->with('success', 'Akun Administrator baru berhasil ditambahkan.');
    }

    /**
     * Toggle the active/inactive status of the administrator account.
     */
    public function toggleStatus(User $user): RedirectResponse
    {
        // Ensure targeted user is an administrator
        if (! $user->isAdmin()) {
            abort(404, 'Akun administrator tidak ditemukan.');
        }

        // Prevent admin from deactivating or modifying their own active status
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Anda tidak dapat mengubah status aktif akun Anda sendiri demi keamanan.');
        }

        $user->status = $user->status === 'active' ? 'inactive' : 'active';
        $user->save();

        $label = $user->status === 'active' ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Akun administrator \"{$user->name}\" berhasil {$label}.");
    }
}
