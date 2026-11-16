<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $demoAccountPasswords = [
            'admin@smartattendance.sch.id' => 'admin123',
            'admin@demo.com' => 'admin123',
            'budi.santoso@smartattendance.sch.id' => 'teacher123',
            'ahmad.rizky@smartattendance.sch.id' => 'student123',
        ];

        if (app()->environment('production')) {
            User::query()
                ->whereIn('email', array_keys($demoAccountPasswords))
                ->get()
                ->each(function (User $user) use ($demoAccountPasswords): void {
                    if (Hash::check($demoAccountPasswords[$user->email], $user->password)) {
                        $user->update(['status' => 'inactive']);
                    }
                });

            $adminBootstrap = config('app.admin_bootstrap');
            $hasAdminBootstrap = array_filter(
                $adminBootstrap,
                static fn ($value): bool => $value !== null && $value !== ''
            ) !== [];

            if ($hasAdminBootstrap) {
                $name = $adminBootstrap['name'] ?? null;
                $email = $adminBootstrap['email'] ?? null;
                $password = $adminBootstrap['password'] ?? null;

                if (
                    ! is_string($name)
                    || trim($name) === ''
                    || ! is_string($email)
                    || filter_var($email, FILTER_VALIDATE_EMAIL) === false
                    || ! is_string($password)
                    || strlen($password) < 16
                ) {
                    throw new \RuntimeException('Admin bootstrap configuration is incomplete or invalid.');
                }

                $existingUser = User::query()
                    ->where('email', $email)
                    ->where('role', 'admin')
                    ->first();

                if ($existingUser === null || $existingUser->status !== 'active') {
                    User::updateOrCreate(
                        ['email' => $email, 'role' => 'admin'],
                        [
                            'name' => trim($name),
                            'password' => Hash::make($password),
                            'status' => 'active',
                        ]
                    );

                    Log::info('Production admin bootstrap account created or activated.');
                } else {
                    Log::info('Production admin bootstrap account already active; credentials were not changed.');
                }
            } elseif (! User::query()->where('role', 'admin')->where('status', 'active')->exists()) {
                Log::warning('No active admin exists and production admin bootstrap is not configured.');
            }

            $roleBootstrapAccounts = config('app.role_bootstrap', []);

            foreach ($roleBootstrapAccounts as $role => $account) {
                $hasRoleBootstrap = array_filter(
                    $account,
                    static fn ($value): bool => $value !== null && $value !== ''
                ) !== [];

                if (! $hasRoleBootstrap) {
                    continue;
                }

                $name = $account['name'] ?? null;
                $email = $account['email'] ?? null;
                $password = $account['password'] ?? null;

                if (
                    ! in_array($role, ['admin', 'teacher', 'student'], true)
                    || ! is_string($name)
                    || trim($name) === ''
                    || ! is_string($email)
                    || filter_var($email, FILTER_VALIDATE_EMAIL) === false
                    || ! is_string($password)
                    || $password === ''
                ) {
                    throw new \RuntimeException("The production {$role} bootstrap configuration is incomplete or invalid.");
                }

                $user = User::query()
                    ->where('email', $email)
                    ->where('role', $role)
                    ->first()
                    ?? User::query()
                        ->where('role', $role)
                        ->where('status', 'active')
                        ->first()
                    ?? User::query()->where('role', $role)->first();

                if ($user === null) {
                    $user = User::create([
                        'name' => trim($name),
                        'email' => $email,
                        'password' => Hash::make($password),
                        'role' => $role,
                        'status' => 'active',
                    ]);
                } else {
                    $user->update([
                        'name' => trim($name),
                        'email' => $email,
                        'password' => Hash::make($password),
                        'status' => 'active',
                    ]);
                }

                if ($role === 'teacher') {
                    Teacher::firstOrCreate(
                        ['user_id' => $user->id],
                        ['nip' => "BOOTSTRAP-{$user->id}"]
                    );
                } elseif ($role === 'student') {
                    Student::firstOrCreate(
                        ['user_id' => $user->id],
                        [
                            'nis' => "BOOTSTRAP-{$user->id}",
                            'class' => 'Belum ditentukan',
                        ]
                    );
                }

                Log::info("Production {$role} bootstrap account created or updated.");
            }

            return;
        }

        // 1. Create Default Admin User
        $admin = User::updateOrCreate(
            ['email' => 'admin@smartattendance.sch.id'],
            [
                'name' => 'Administrator Smart Attendance',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        // 1b. Create Demo Admin Account
        User::updateOrCreate(
            ['email' => 'admin@demo.com'],
            [
                'name' => 'Admin Demo',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        // 2. Create Sample Teacher for development
        $teacherUser = User::firstOrCreate(
            ['email' => 'budi.santoso@smartattendance.sch.id'],
            [
                'name' => 'Budi Santoso, S.Kom',
                'password' => Hash::make('teacher123'),
                'role' => 'teacher',
                'status' => 'active',
            ]
        );

        Teacher::firstOrCreate(
            ['user_id' => $teacherUser->id],
            [
                'nip' => '198501152010011002',
                'face_data' => null,
            ]
        );

        // 3. Create Sample Student for development
        $studentUser = User::firstOrCreate(
            ['email' => 'ahmad.rizky@smartattendance.sch.id'],
            [
                'name' => 'Ahmad Rizky Pratama',
                'password' => Hash::make('student123'),
                'role' => 'student',
                'status' => 'active',
            ]
        );

        Student::firstOrCreate(
            ['user_id' => $studentUser->id],
            [
                'nis' => '202610101',
                'class' => 'XII RPL 1',
                'face_data' => null,
            ]
        );

        // 4. Sample Attendance Log for development
        $today = Carbon::today()->toDateString();

        Attendance::firstOrCreate(
            [
                'user_id' => $studentUser->id,
                'attendance_date' => $today,
            ],
            [
                'attendance_time' => '06:45:00',
                'status' => 'present',
                'verification_status' => 'verified',
            ]
        );

        Attendance::firstOrCreate(
            [
                'user_id' => $teacherUser->id,
                'attendance_date' => $today,
            ],
            [
                'attendance_time' => '06:30:00',
                'status' => 'present',
                'verification_status' => 'verified',
            ]
        );
    }
}
