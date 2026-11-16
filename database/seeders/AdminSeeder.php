<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
