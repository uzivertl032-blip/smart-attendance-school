<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('demo:admin {--password=admin123 : Kata sandi untuk akun demo admin}', function () {
    $password = $this->option('password') ?: 'admin123';

    $users = [
        [
            'name' => 'Administrator Smart Attendance',
            'email' => 'admin@smartattendance.sch.id',
        ],
        [
            'name' => 'Admin Demo',
            'email' => 'admin@demo.com',
        ],
    ];

    foreach ($users as $item) {
        $user = User::updateOrCreate(
            ['email' => $item['email']],
            [
                'name' => $item['name'],
                'password' => Hash::make($password),
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        $this->info('✓ Akun Admin berhasil disiapkan:');
        $this->line("   Email    : {$user->email}");
        $this->line("   Password : {$password}");
        $this->line("   Role     : {$user->role}");
        $this->line("   Status   : {$user->status}");
    }
})->purpose('Buat atau perbarui kredensial akun demo admin');
