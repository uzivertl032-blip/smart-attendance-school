<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\FaceRecognitionEngineInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStudentRequest;
use App\Http\Requests\Admin\UpdateStudentRequest;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use InvalidArgumentException;

class StudentController extends Controller
{
    /**
     * Display a paginated list of students with optional search.
     */
    public function index(Request $request): View
    {
        $search = $request->input('search');

        $students = Student::with('user')
            ->when($search, function ($query) use ($search) {
                $query->where('nis', 'like', "%{$search}%")
                    ->orWhere('class', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.students.index', compact('students', 'search'));
    }

    /**
     * Show the form for creating a new student.
     */
    public function create(): View
    {
        return view('admin.students.create');
    }

    /**
     * Store a newly created student in storage.
     * Creates a User record (role=student) and an associated Student record.
     */
    public function store(StoreStudentRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'student',
                'status' => $request->status,
            ]);

            Student::create([
                'user_id' => $user->id,
                'nis' => $request->nis,
                'class' => $request->class,
            ]);
        });

        return redirect()->route('admin.students.index')
            ->with('success', 'Siswa berhasil ditambahkan ke sistem.');
    }

    /**
     * Display the specified student's detail.
     */
    public function show(Student $student): View
    {
        $student->load('user');

        return view('admin.students.show', compact('student'));
    }

    /**
     * Show the form for editing the specified student.
     */
    public function edit(Student $student): View
    {
        $student->load('user');

        return view('admin.students.edit', compact('student'));
    }

    /**
     * Update the specified student in storage.
     * Updates both the User record and the Student record.
     */
    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse
    {
        DB::transaction(function () use ($request, $student) {
            $userUpdates = [
                'name' => $request->name,
                'email' => $request->email,
                'status' => $request->status,
            ];

            if ($request->filled('password')) {
                $userUpdates['password'] = Hash::make($request->password);
            }

            $student->user->update($userUpdates);

            $student->update([
                'nis' => $request->nis,
                'class' => $request->class,
            ]);
        });

        return redirect()->route('admin.students.index')
            ->with('success', 'Data siswa berhasil diperbarui.');
    }

    /**
     * Remove the specified student from storage.
     * Deleting the User will cascade-delete the Student record safely in a database transaction.
     */
    public function destroy(Student $student): RedirectResponse
    {
        $student->load('user.attendances');

        if ($student->user && $student->user->attendances()->exists()) {
            return redirect()->route('admin.students.index')
                ->with('error', 'Akun siswa tidak dapat dihapus karena masih memiliki riwayat absensi. Silakan nonaktifkan akun jika siswa sudah tidak aktif.');
        }

        DB::transaction(function () use ($student) {
            $user = $student->user;
            $student->delete();

            if ($user) {
                $user->delete();
            }
        });

        return redirect()->route('admin.students.index')
            ->with('success', 'Akun siswa berhasil dihapus.');
    }

    /**
     * Toggle the active/inactive status of the student's user account.
     */
    public function toggleStatus(Student $student): RedirectResponse
    {
        $user = $student->user;
        $user->status = $user->status === 'active' ? 'inactive' : 'active';
        $user->save();

        $label = $user->status === 'active' ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Akun siswa \"{$user->name}\" berhasil {$label}.");
    }

    /**
     * Display the face registration camera page for student.
     */
    public function showFaceRegistration(Student $student): View
    {
        $student->load('user');
        $personType = 'student';
        $personName = $student->user->name;
        $personCode = $student->nis;
        $personSubtitle = "Kelas: {$student->class}";
        $backUrl = route('admin.students.show', $student);
        $submitUrl = route('admin.students.face-register.store', $student);

        return view('admin.face.register', compact(
            'student',
            'personType',
            'personName',
            'personCode',
            'personSubtitle',
            'backUrl',
            'submitUrl'
        ));
    }

    /**
     * Store biometric face data for student using FaceRecognitionEngineInterface.
     */
    public function storeFaceData(Request $request, Student $student, FaceRecognitionEngineInterface $faceEngine): RedirectResponse
    {
        $request->validate([
            'face_data' => ['required', 'string'],
        ], [
            'face_data.required' => 'Data biometrik wajah wajib diisi.',
        ]);

        try {
            $processedFaceData = $faceEngine->processRegistration($request->input('face_data'));
            $student->update([
                'face_data' => $processedFaceData,
            ]);

            return redirect()->route('admin.students.show', $student)
                ->with('success', "Registrasi wajah untuk siswa \"{$student->user->name}\" berhasil disimpan!");
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal memproses data biometrik wajah: '.$e->getMessage());
        }
    }

    /**
     * Remove biometric face data for student.
     */
    public function destroyFaceData(Student $student): RedirectResponse
    {
        $student->update([
            'face_data' => null,
        ]);

        return redirect()->route('admin.students.show', $student)
            ->with('success', "Data biometrik wajah siswa \"{$student->user->name}\" berhasil dihapus.");
    }
}
