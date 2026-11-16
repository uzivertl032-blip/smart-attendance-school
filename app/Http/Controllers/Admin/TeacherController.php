<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\FaceRecognitionEngineInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTeacherRequest;
use App\Http\Requests\Admin\UpdateTeacherRequest;
use App\Models\Attendance;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use InvalidArgumentException;

class TeacherController extends Controller
{
    /**
     * Display a paginated list of teachers with optional search.
     */
    public function index(Request $request): View
    {
        $search = $request->input('search');

        $teachers = Teacher::with('user')
            ->when($search, function ($query) use ($search) {
                $query->where('nip', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.teachers.index', compact('teachers', 'search'));
    }

    /**
     * Show the form for creating a new teacher.
     */
    public function create(): View
    {
        return view('admin.teachers.create');
    }

    /**
     * Store a newly created teacher in storage.
     * Creates a User record (role=teacher) and an associated Teacher record.
     */
    public function store(StoreTeacherRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'teacher',
                'status' => $request->status,
            ]);

            Teacher::create([
                'user_id' => $user->id,
                'nip' => $request->nip,
            ]);
        });

        return redirect()->route('admin.teachers.index')
            ->with('success', 'Guru berhasil ditambahkan ke sistem.');
    }

    /**
     * Display the specified teacher's detail.
     */
    public function show(Teacher $teacher): View
    {
        $teacher->load('user');

        return view('admin.teachers.show', compact('teacher'));
    }

    /**
     * Show the form for editing the specified teacher.
     */
    public function edit(Teacher $teacher): View
    {
        $teacher->load('user');

        return view('admin.teachers.edit', compact('teacher'));
    }

    /**
     * Update the specified teacher in storage.
     * Updates both the User record and the Teacher record.
     */
    public function update(UpdateTeacherRequest $request, Teacher $teacher): RedirectResponse
    {
        DB::transaction(function () use ($request, $teacher) {
            $userUpdates = [
                'name' => $request->name,
                'email' => $request->email,
                'status' => $request->status,
            ];

            if ($request->filled('password')) {
                $userUpdates['password'] = Hash::make($request->password);
            }

            $teacher->user->update($userUpdates);

            $teacher->update([
                'nip' => $request->nip,
            ]);
        });

        return redirect()->route('admin.teachers.index')
            ->with('success', 'Data guru berhasil diperbarui.');
    }

    /**
     * Remove the specified teacher from storage.
     * Deleting the User will cascade-delete the Teacher record safely in a database transaction.
     */
    public function destroy(Teacher $teacher): RedirectResponse
    {
        $teacher->load('user.attendances');

        $hasAttendances = $teacher->user && $teacher->user->attendances()->exists();
        $hasApprovedAttendances = $teacher->user_id && Attendance::where('approved_by', $teacher->user_id)->exists();

        if ($hasAttendances || $hasApprovedAttendances) {
            return redirect()->route('admin.teachers.index')
                ->with('error', 'Akun guru tidak dapat dihapus karena masih memiliki riwayat absensi atau persetujuan izin. Silakan nonaktifkan akun jika guru sudah tidak aktif.');
        }

        DB::transaction(function () use ($teacher) {
            $user = $teacher->user;
            $teacher->delete();

            if ($user) {
                $user->delete();
            }
        });

        return redirect()->route('admin.teachers.index')
            ->with('success', 'Akun guru berhasil dihapus.');
    }

    /**
     * Toggle the active/inactive status of the teacher's user account.
     */
    public function toggleStatus(Teacher $teacher): RedirectResponse
    {
        $user = $teacher->user;
        $user->status = $user->status === 'active' ? 'inactive' : 'active';
        $user->save();

        $label = $user->status === 'active' ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Akun guru \"{$user->name}\" berhasil {$label}.");
    }

    /**
     * Display the face registration camera page for teacher.
     */
    public function showFaceRegistration(Teacher $teacher): View
    {
        $teacher->load('user');
        $personType = 'teacher';
        $personName = $teacher->user->name;
        $personCode = "NIP: {$teacher->nip}";
        $personSubtitle = 'Pengajar / Staf';
        $backUrl = route('admin.teachers.show', $teacher);
        $submitUrl = route('admin.teachers.face-register.store', $teacher);

        return view('admin.face.register', compact(
            'teacher',
            'personType',
            'personName',
            'personCode',
            'personSubtitle',
            'backUrl',
            'submitUrl'
        ));
    }

    /**
     * Store biometric face data for teacher using FaceRecognitionEngineInterface.
     */
    public function storeFaceData(Request $request, Teacher $teacher, FaceRecognitionEngineInterface $faceEngine): RedirectResponse
    {
        $request->validate([
            'face_data' => ['required', 'string'],
        ], [
            'face_data.required' => 'Data biometrik wajah wajib diisi.',
        ]);

        try {
            $processedFaceData = $faceEngine->processRegistration($request->input('face_data'));
            $teacher->update([
                'face_data' => $processedFaceData,
            ]);

            return redirect()->route('admin.teachers.show', $teacher)
                ->with('success', "Registrasi wajah untuk guru \"{$teacher->user->name}\" berhasil disimpan!");
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal memproses data biometrik wajah: '.$e->getMessage());
        }
    }

    /**
     * Remove biometric face data for teacher.
     */
    public function destroyFaceData(Teacher $teacher): RedirectResponse
    {
        $teacher->update([
            'face_data' => null,
        ]);

        return redirect()->route('admin.teachers.show', $teacher)
            ->with('success', "Data biometrik wajah guru \"{$teacher->user->name}\" berhasil dihapus.");
    }
}
