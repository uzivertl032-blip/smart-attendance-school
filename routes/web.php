<?php

use App\Http\Controllers\Admin\AdminManagementController;
use App\Http\Controllers\Admin\AiConfigurationController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Admin\TeacherLeaveRequestController as AdminTeacherLeaveRequestController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\LeaveRequestController as StudentLeaveRequestController;
use App\Http\Controllers\Student\StudentAuthController;
use App\Http\Controllers\Teacher\DashboardController;
use App\Http\Controllers\Teacher\LeaveRequestController;
use App\Http\Controllers\Teacher\MyLeaveRequestController;
use App\Http\Controllers\Teacher\TeacherAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Smart Attendance School
|--------------------------------------------------------------------------
*/

// 1. Landing Page
Route::get('/', [HomeController::class, 'index'])->name('home');

// Attendance Scanner Kiosk (Strictly restricted to authenticated active students & teachers)
Route::middleware(['attendance.access'])->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');

    // Rate-limited attendance kiosk endpoints (30 req/min per IP)
    Route::middleware('throttle:attendance-kiosk')->group(function () {
        Route::post('/attendance/verify', [AttendanceController::class, 'verifyFace'])->name('attendance.verify');
        Route::post('/attendance/record', [AttendanceController::class, 'recordAttendance'])->name('attendance.record');
    });
});

// 2. Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');

    // Teacher dedicated login
    Route::get('/teacher/login', [TeacherAuthController::class, 'showLoginForm'])->name('teacher.login');
    Route::post('/teacher/login', [TeacherAuthController::class, 'login'])->name('teacher.login.post');

    // Student dedicated login
    Route::get('/student/login', [StudentAuthController::class, 'showLoginForm'])->name('student.login');
    Route::post('/student/login', [StudentAuthController::class, 'login'])->name('student.login.post');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Chatbot+ Assistant Route (Strictly for authenticated users)
Route::post('/chatbot/ask', [ChatbotController::class, 'ask'])
    ->middleware(['auth', 'throttle:60,1'])
    ->name('chatbot.ask');

// 3. Student Protected Routes (Middleware: auth & student)
Route::middleware(['auth', 'student'])->prefix('student')->name('student.')->group(function () {
    Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');
    Route::get('/history', [StudentDashboardController::class, 'history'])->name('history');
    Route::get('/leave-requests', [StudentLeaveRequestController::class, 'index'])->name('leave-requests.index');
    Route::post('/leave-requests', [StudentLeaveRequestController::class, 'store'])->name('leave-requests.store');
});

// 4. Teacher Protected Routes (Middleware: auth & teacher)
Route::middleware(['auth', 'teacher'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    // Review Student Leave Requests (Strictly for student requests)
    Route::get('/leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
    Route::get('/leave-requests/{attendance}', [LeaveRequestController::class, 'show'])->name('leave-requests.show');
    Route::patch('/leave-requests/{attendance}/approve', [LeaveRequestController::class, 'approve'])->name('leave-requests.approve');
    Route::patch('/leave-requests/{attendance}/reject', [LeaveRequestController::class, 'reject'])->name('leave-requests.reject');

    // Teacher Own Leave Request Submission (Requirement 2 & 3)
    Route::get('/my-leave-requests', [MyLeaveRequestController::class, 'index'])->name('my-leave-requests.index');
    Route::get('/my-leave-requests/create', [MyLeaveRequestController::class, 'create'])->name('my-leave-requests.create');
    Route::post('/my-leave-requests', [MyLeaveRequestController::class, 'store'])->name('my-leave-requests.store');
});

// 3. Admin Protected Routes (Middleware: auth & admin)
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/attendances/{attendance}', [AdminDashboardController::class, 'showAttendance'])->name('attendances.show');

    // Teacher Leave Approval Management (Requirement 1, 2, 4, 5, 7)
    Route::get('teacher-leave-requests', [AdminTeacherLeaveRequestController::class, 'index'])
        ->name('teacher-leave-requests.index');
    Route::get('teacher-leave-requests/{attendance}', [AdminTeacherLeaveRequestController::class, 'show'])
        ->name('teacher-leave-requests.show');
    Route::patch('teacher-leave-requests/{attendance}/approve', [AdminTeacherLeaveRequestController::class, 'approve'])
        ->name('teacher-leave-requests.approve');
    Route::patch('teacher-leave-requests/{attendance}/reject', [AdminTeacherLeaveRequestController::class, 'reject'])
        ->name('teacher-leave-requests.reject');

    // Student Management
    Route::resource('students', StudentController::class);
    Route::patch('students/{student}/toggle-status', [StudentController::class, 'toggleStatus'])
        ->name('students.toggle-status');
    Route::get('students/{student}/face-register', [StudentController::class, 'showFaceRegistration'])
        ->name('students.face-register');
    Route::post('students/{student}/face-register', [StudentController::class, 'storeFaceData'])
        ->name('students.face-register.store');
    Route::delete('students/{student}/face-register', [StudentController::class, 'destroyFaceData'])
        ->name('students.face-register.destroy');

    // Teacher Management
    Route::resource('teachers', TeacherController::class);
    Route::patch('teachers/{teacher}/toggle-status', [TeacherController::class, 'toggleStatus'])
        ->name('teachers.toggle-status');
    Route::get('teachers/{teacher}/face-register', [TeacherController::class, 'showFaceRegistration'])
        ->name('teachers.face-register');
    Route::post('teachers/{teacher}/face-register', [TeacherController::class, 'storeFaceData'])
        ->name('teachers.face-register.store');
    Route::delete('teachers/{teacher}/face-register', [TeacherController::class, 'destroyFaceData'])
        ->name('teachers.face-register.destroy');

    // Admin Account Management
    Route::get('admins', [AdminManagementController::class, 'index'])->name('admins.index');
    Route::get('admins/create', [AdminManagementController::class, 'create'])->name('admins.create');
    Route::post('admins', [AdminManagementController::class, 'store'])->name('admins.store');
    Route::patch('admins/{user}/toggle-status', [AdminManagementController::class, 'toggleStatus'])
        ->name('admins.toggle-status');

    // AI Multi-Provider Configuration
    Route::get('ai-configuration', [\App\Http\Controllers\Admin\AiConfigurationController::class, 'index'])
        ->name('ai-configuration.index');
    Route::post('ai-configuration', [\App\Http\Controllers\Admin\AiConfigurationController::class, 'update'])
        ->name('ai-configuration.update');
    Route::post('ai-configuration/test', [\App\Http\Controllers\Admin\AiConfigurationController::class, 'testConnection'])
        ->name('ai-configuration.test');
});
