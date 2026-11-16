# Smart Attendance School - Sistem Absensi Sekolah Berbasis Face Recognition

**Smart Attendance School** adalah aplikasi web modern untuk mengelola presensi siswa dan guru secara otomatis, akurat, dan real-time menggunakan kamera dan teknologi face recognition.

1. Identitas Kelompok
AHMAD FIROSY FIJRI 41257176
Fajar febryano 41257190
Danish Akmal Marchielo 41257182
Arya Kusuma Firdaus (41257182)
Galang Marcellino (41257194)
TREDI (41257229)

Link pengumpulan sudah termasuk video,ppt,laporan
https://drive.google.com/drive/folders/15Av6ef9i8amg6u704aqe_Yb3U3d0dVma?usp=sharing

Tema Topik Kelompok: Absensi Menggunakan kamera

2. Deskripsi Aplikasi

SmartAttendance merupakan aplikasi sistem informasi presensi sekolah berbasis web yang dirancang untuk membantu pengelolaan kehadiran siswa dan guru secara modern, cepat, akurat, dan terintegrasi.

Aplikasi ini menyediakan sistem manajemen presensi yang dapat digunakan oleh Administrator, Guru, dan Siswa. Sistem dilengkapi teknologi pengenalan wajah (Face Recognition), pengelolaan izin dan sakit, rekapitulasi kehadiran, serta Chatbot+ berbasis Artificial Intelligence.

SmartAttendance dikembangkan menggunakan Laravel dan MySQL serta dijalankan secara lokal menggunakan Laragon.

## 🏛️ Arsitektur Sistem & Stack Teknologi

- **Backend Framework**: [Laravel 13.x](https://laravel.com/) (PHP 8.3) dengan Arsitektur MVC (Model-View-Controller)
- **Frontend / UI**: Blade Templating + [Bootstrap 5.3](https://getbootstrap.com/) + Bootstrap Icons + Vanilla JavaScript
- **Database**: MySQL 8.x / MariaDB (Laragon)
- **Keamanan**:
  - Hashing Password menggunakan algoritma Bcrypt (`Hash::make()`)
  - Perlindungan CSRF (`@csrf`) pada semua form otentikasi & mutasi data
  - Pembatasan rute Admin via `AdminMiddleware` & pemeriksaan status user aktif
  - Rate Limiting / Throttle pada form login untuk mencegah serangan brute-force
  - Regenerasi session ID saat login sukses untuk mencegah *session fixation*

---

## 🗄️ Skema Database & Relasi

1. **`users`**:
   - `id`, `name`, `email` (unique), `password`, `role` (`admin`, `student`, `teacher`), `status` (`active`, `inactive`), `remember_token`, `timestamps`.
2. **`students`**:
   - `id`, `user_id` (FK -> `users.id`, onDelete cascade), `nis` (unique), `class`, `face_data` (nullable), `timestamps`.
   - Relasi: `belongsTo(User::class)`
3. **`teachers`**:
   - `id`, `user_id` (FK -> `users.id`, onDelete cascade), `nip` (unique), `face_data` (nullable), `timestamps`.
   - Relasi: `belongsTo(User::class)`
4. **`attendances`**:
   - `id`, `user_id` (FK -> `users.id`, onDelete cascade), `attendance_date`, `attendance_time`, `status` (`present`, `late`, `excused`, `sick`, `absent`), `verification_status` (`verified`, `unverified`, `failed`), `timestamps`.
   - Relasi: `belongsTo(User::class)`

---

## 📂 Struktur Direktori Proyek MVC

```
rpl_project/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/
│   │   │   │   └── DashboardController.php   # Dashboard Ringkasan Admin
│   │   │   ├── Auth/
│   │   │   │   └── AuthController.php        # Otentikasi Login/Logout Admin
│   │   │   └── AttendanceController.php      # Landing Kiosk Pemindai Presensi
│   │   └── Middleware/
│   │       └── AdminMiddleware.php           # Proteksi Hak Akses Administrator
│   └── Models/
│       ├── User.php                          # Model User & Role Helpers
│       ├── Student.php                       # Model Siswa (Profil, NIS, Kelas)
│       ├── Teacher.php                       # Model Guru (Profil, NIP)
│       └── Attendance.php                    # Model Log Kehadiran & Status
├── bootstrap/
│   └── app.php                               # Konfigurasi Middleware & Routing
├── database/
│   ├── migrations/                           # Migrasi Skema Database
│   └── seeders/
│       ├── AdminSeeder.php                   # Seeder Admin, Guru, Siswa, Log Presensi
│       └── DatabaseSeeder.php
├── resources/
│   └── views/
│       ├── admin/
│       │   └── dashboard.blade.php           # Halaman Dashboard Admin Responsif
│       ├── attendance/
│       │   └── index.blade.php               # Halaman Kiosk Scanner Absensi Kamera
│       ├── auth/
│       │   └── login.blade.php               # Halaman Login Admin
│       ├── components/
│       │   └── alerts.blade.php              # Komponen Notifikasi & Error Alert
│       └── layouts/
│           ├── admin.blade.php               # Layout Admin dengan Sidebar & Topbar
│           └── app.blade.php                 # Master Layout Umum
├── routes/
│   └── web.php                               # Definisi Rute Web & Middleware
└── tests/
    └── Feature/
        ├── AdminAuthTest.php                 # Unit & Feature Test Otentikasi Admin
        └── AttendanceLandingTest.php         # Test Halaman Landing Absensi
```

---

## 🚀 Panduan Menjalankan Proyek

### 1. Prasyarat
- **PHP** >= 8.2 (Disarankan PHP 8.3 bawaan Laragon)
- **Composer** >= 2.x
- **MySQL Server** aktif (Laragon)

### 2. Konfigurasi Environment & Database
1. Pastikan server MySQL di Laragon aktif.
2. Buat database MySQL baru bernama `smart_attendance`:
   ```sql
   CREATE DATABASE smart_attendance;
   ```
3. Sesuaikan konfigurasi pada file `.env`:
   ```env
   APP_NAME="Smart Attendance School"
   APP_ENV=local
   APP_KEY=
   APP_DEBUG=true
   APP_URL=http://localhost:8000

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=smart_attendance
   DB_USERNAME=root
   DB_PASSWORD=
   ```

### 3. Migrasi Database dan Seeding Data Awal
Jalankan perintah artisan berikut di terminal proyek:
```bash
php artisan migrate:fresh --seed
```

### 4. Menjalankan Server Aplikasi
Jalankan server pengembangan Laravel:
```bash
php artisan serve
```
Akses aplikasi melalui browser:
- **Halaman Pemindai Absensi (Kiosk)**: [http://localhost:8000](http://localhost:8000) atau [http://localhost:8000/attendance](http://localhost:8000/attendance)
- **Halaman Login Admin**: [http://localhost:8000/login](http://localhost:8000/login)
- **Panel Dashboard Admin**: [http://localhost:8000/admin/dashboard](http://localhost:8000/admin/dashboard)

---

## 🚂 Deployment ke Railway

1. Hubungkan repository GitHub ini ke project baru di Railway dan tambahkan layanan MySQL.
2. Pada layanan Laravel, atur variabel berikut melalui **Variables** di Railway (jangan simpan secret di repository):

   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_KEY=<buat dengan php artisan key:generate --show>
   APP_URL=<isi setelah domain Railway dibuat>
   DB_CONNECTION=mysql
   DB_HOST=${{MySQL.MYSQLHOST}}
   DB_PORT=${{MySQL.MYSQLPORT}}
   DB_DATABASE=${{MySQL.MYSQLDATABASE}}
   DB_USERNAME=${{MySQL.MYSQLUSER}}
   DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
   SESSION_DRIVER=database
   CACHE_STORE=database
   QUEUE_CONNECTION=database
   ```

   Sesuaikan `MySQL` dengan nama layanan database Railway jika berbeda. Buat `APP_KEY` secara lokal, lalu masukkan hasilnya langsung ke Variables Railway; jangan commit nilainya.
3. Railway mendeteksi aplikasi Laravel secara otomatis. Atur perintah **Pre-deploy** menjadi `php artisan migrate --force`, lalu deploy.
4. Setelah deployment berhasil, aktifkan domain publik pada **Settings → Networking → Generate Domain**. Atur `APP_URL` ke domain `https://...` tersebut lalu deploy ulang.
5. Untuk membuat admin pertama, isi `ADMIN_BOOTSTRAP_NAME`, `ADMIN_BOOTSTRAP_EMAIL`, dan `ADMIN_BOOTSTRAP_PASSWORD` sebagai secret di Railway. Gunakan password acak minimal 16 karakter. Seeder production hanya membuat akun tersebut jika belum ada admin aktif dan menolak konfigurasi yang tidak lengkap; hapus ketiga variable setelah deployment berhasil. Seeder tidak membuat akun demo di production dan menonaktifkan akun contoh lama yang masih memakai password bawaan. `.env` dan file environment lokal lainnya dikecualikan dari Git.

> Jangan gunakan akun demo untuk production. Jika tidak ada konfigurasi bootstrap, database baru tidak memiliki akun admin hingga akun dibuat secara aman.

---

## 🧪 Menjalankan Automated Tests
Jalankan suite pengujian otomatis untuk memverifikasi fungsionalitas sistem:
```bash
php artisan test
```
Semua test menguji login valid, login invalid, isolasi role non-admin, proteksi route dashboard via middleware, dan logout.
