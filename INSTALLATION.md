# Panduan Instalasi SmartAttendance

> Panduan ini ditujukan untuk menjalankan project **SmartAttendance** dari nol di laptop/komputer baru menggunakan **Laragon** (Windows).

---

## Daftar Isi

1. [Kebutuhan Sistem](#1-kebutuhan-sistem)
2. [Software yang Harus Diinstal](#2-software-yang-harus-diinstal)
3. [Menyalin Project ke Laptop Baru](#3-menyalin-project-ke-laptop-baru)
4. [Export Database dari Laptop Lama](#4-export-database-dari-laptop-lama)
5. [Konfigurasi Laragon](#5-konfigurasi-laragon)
6. [Import Database di Laptop Baru](#6-import-database-di-laptop-baru)
7. [Konfigurasi File .env](#7-konfigurasi-file-env)
8. [Instalasi Dependency](#8-instalasi-dependency)
9. [Build Frontend](#9-build-frontend)
10. [Konfigurasi Storage & Symlink](#10-konfigurasi-storage--symlink)
11. [Konfigurasi Domain Laragon](#11-konfigurasi-domain-laragon)
12. [Konfigurasi HTTPS (Kamera Face Recognition)](#12-konfigurasi-https-kamera-face-recognition)
13. [Input Ulang API Key AI](#13-input-ulang-api-key-ai)
14. [Checklist Pengujian Pasca-Migrasi](#14-checklist-pengujian-pasca-migrasi)
15. [Kredensial Default](#15-kredensial-default)
16. [Masalah yang Diketahui](#16-masalah-yang-diketahui)
17. [Troubleshooting](#17-troubleshooting)

---

## 1. Kebutuhan Sistem

| Komponen | Versi yang Dibutuhkan | Keterangan |
|---|---|---|
| PHP | 8.3.x | Via Laragon |
| MySQL | 8.4.x | Via Laragon |
| Node.js | 20.x atau lebih baru | Via installer resmi |
| npm | 10.x atau lebih baru | Bawaan Node.js |
| Composer | 2.x | Installer terpisah |
| Laragon | 6.x (Full) | Versi Full disarankan |

**PHP Extensions yang dibutuhkan:**

| Extension | Status |
|---|---|
| `pdo_mysql` | Wajib |
| `mbstring` | Wajib |
| `openssl` | Wajib |
| `tokenizer` | Wajib |
| `xml` | Wajib |
| `ctype` | Wajib |
| `fileinfo` | Wajib |
| `curl` | Wajib (untuk AI API) |
| `gd` | Disarankan |
| `bcmath` | Disarankan |
| `zip` | Opsional |

> Semua extension di atas sudah aktif secara default di Laragon Full, kecuali `zip` yang perlu diaktifkan manual jika diperlukan.

---

## 2. Software yang Harus Diinstal

### A. Laragon (Wajib)

Download dari: **https://laragon.org/download/**

Pilih versi **Laragon Full** (sudah termasuk PHP, MySQL, Apache/Nginx, phpMyAdmin).

Instalasi default: `C:\laragon\`

### B. Composer (Wajib)

Download installer dari: **https://getcomposer.org/download/**

Verifikasi instalasi:
```bash
composer --version
# Output: Composer version 2.x.x
```

### C. Node.js (Wajib)

Download dari: **https://nodejs.org/** — pilih versi LTS (20.x atau lebih baru)

Verifikasi instalasi:
```bash
node --version
# Output: v20.x.x atau lebih baru

npm --version
# Output: 10.x.x atau lebih baru
```

---

## 3. Menyalin Project ke Laptop Baru

### Metode 1: Salin Langsung (USB / LAN)

Salin seluruh folder project `absensi-sekolah` ke:
```
C:\laragon\www\absensi-sekolah\
```

**File dan folder yang WAJIB disertakan:**

| Path | Keterangan |
|---|---|
| `app/` | Controllers, Models, Services |
| `bootstrap/` | Cache bootstrap Laravel |
| `config/` | File konfigurasi |
| `database/` | Migrations, Seeders, Factories |
| `public/` | Asset publik (CSS, JS, images, build) |
| `resources/` | Views Blade, CSS/JS sumber |
| `routes/` | Definisi routing |
| `storage/` | Log, cache, upload (folder harus ada) |
| `composer.json` | Dependency PHP |
| `composer.lock` | Lock file versi dependency |
| `package.json` | Dependency Node.js |
| `package-lock.json` | Lock file versi Node.js |
| `.env.example` | Template konfigurasi environment |
| `artisan` | CLI Laravel |
| `vite.config.js` | Konfigurasi Vite |
| `tailwind.config.js` | Konfigurasi Tailwind (jika ada) |

**File dan folder yang TIDAK perlu disertakan:**

| Path | Keterangan |
|---|---|
| `vendor/` | Akan diinstal ulang via Composer |
| `node_modules/` | Akan diinstal ulang via npm |
| `.env` | File sensitif, jangan disalin |
| `storage/logs/` | Log tidak perlu dipindahkan |

### Metode 2: Git Repository

Jika project sudah menggunakan Git:
```bash
git clone https://github.com/[username]/absensi-sekolah.git C:\laragon\www\absensi-sekolah
```

---

## 4. Export Database dari Laptop Lama

Lakukan ini di **laptop lama** sebelum pindah.

### Via Command Line (mysqldump)

Buka terminal atau Command Prompt, lalu jalankan:

```bash
# Path mysqldump Laragon (sesuaikan versi MySQL)
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe -u root smart_attendance > smart_attendance_backup.sql
```

Atau tambahkan ke PATH terlebih dahulu:
```bash
mysqldump -u root smart_attendance > smart_attendance_backup.sql
```

Jika MySQL memiliki password root (default Laragon: kosong):
```bash
mysqldump -u root -p smart_attendance > smart_attendance_backup.sql
# Tekan Enter saat diminta password (kosongkan jika tidak ada password)
```

File `smart_attendance_backup.sql` akan tersimpan di direktori aktif saat perintah dijalankan.

### Via phpMyAdmin (Alternatif)

1. Buka **phpMyAdmin** melalui Laragon (klik kanan tray → phpMyAdmin)
2. Pilih database `smart_attendance`
3. Klik tab **Export**
4. Metode: **Custom**
5. Centang **Add DROP TABLE**: Yes
6. Centang **Add CREATE DATABASE**: Yes (opsional)
7. Format: **SQL**
8. Klik **Go** — file `.sql` akan terunduh

Simpan file backup ke USB atau cloud storage.

---

## 5. Konfigurasi Laragon

### Jalankan Laragon

1. Buka Laragon di laptop baru
2. Klik **Start All** untuk menjalankan Apache/Nginx dan MySQL
3. Pastikan status hijau (running)

### Verifikasi MySQL Berjalan

Buka phpMyAdmin dari Laragon (klik kanan ikon Laragon di tray → Database → phpMyAdmin).

Pastikan bisa login dengan:
- Username: `root`
- Password: (kosong, default Laragon)

---

## 6. Import Database di Laptop Baru

### Langkah A: Buat Database Baru

Di **phpMyAdmin**:
1. Klik **New** di panel kiri
2. Nama database: `smart_attendance`
3. Collation: `utf8mb4_unicode_ci`
4. Klik **Create**

Atau via CLI:
```bash
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root -e "CREATE DATABASE smart_attendance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### Langkah B: Import File SQL

**Via phpMyAdmin:**
1. Pilih database `smart_attendance`
2. Klik tab **Import**
3. Klik **Choose File** → pilih `smart_attendance_backup.sql`
4. Format: SQL
5. Klik **Go**

**Via CLI:**
```bash
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root smart_attendance < smart_attendance_backup.sql
```

> **Catatan:** API key AI yang tersimpan di tabel `ai_settings` dienkripsi menggunakan `APP_KEY` Laravel. Setelah migrasi dengan `APP_KEY` baru, API key harus diinput ulang secara manual (lihat bagian [13](#13-input-ulang-api-key-ai)).

---

## 7. Konfigurasi File .env

### Langkah A: Salin .env.example

Buka terminal di folder project:
```bash
cd C:\laragon\www\absensi-sekolah
copy .env.example .env
```

### Langkah B: Isi Konfigurasi

Buka file `.env` dengan text editor (Notepad++, VS Code, dll.) dan sesuaikan:

**Bagian wajib diisi/periksa:**

```env
APP_NAME=SmartAttendance
APP_ENV=local
APP_KEY=                          # Dikosongkan dulu, akan digenerate
APP_DEBUG=true
APP_URL=http://absensi-sekolah.test

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=smart_attendance
DB_USERNAME=root
DB_PASSWORD=                      # Kosongkan jika default Laragon (tanpa password)

# Waktu Absensi (sesuaikan dengan jadwal sekolah)
ATTENDANCE_OPENING_TIME=06:30
ATTENDANCE_LATE_AFTER=07:15

# AI Provider (isi salah satu atau semua)
GEMINI_API_KEY=                   # Isi dengan API key Google Gemini
GEMINI_MODEL=gemini-2.0-flash
GEMINI_BASE_URL=https://generativelanguage.googleapis.com/v1beta

OPENAI_API_KEY=                   # Isi jika menggunakan OpenAI
OPENAI_MODEL=gpt-4o-mini
OPENAI_BASE_URL=https://api.openai.com/v1

NVIDIA_API_KEY=                   # Isi jika menggunakan NVIDIA NIM
NVIDIA_MODEL=nvidia/nemotron-3.5-bolt-e4b
NVIDIA_BASE_URL=https://integrate.api.nvidia.com/v1
```

> **PENTING:** Jangan pernah memasukkan API key asli ke file `.env.example`. File `.env` tidak boleh di-commit ke Git.

### Langkah C: Generate Application Key

```bash
php artisan key:generate
```

Perintah ini akan mengisi `APP_KEY` di file `.env` secara otomatis.

---

## 8. Instalasi Dependency

### PHP Dependencies (Composer)

```bash
cd C:\laragon\www\absensi-sekolah
composer install --no-interaction
```

Proses ini membutuhkan koneksi internet. Tunggu hingga selesai (biasanya 1–3 menit).

### Node.js Dependencies (npm)

```bash
npm install --no-interaction
```

Proses ini membutuhkan koneksi internet. Tunggu hingga selesai.

---

## 9. Build Frontend

Setelah `npm install` selesai, build asset frontend:

```bash
npm run build
```

Jika berhasil, folder `public/build/` akan terbentuk berisi file CSS, JS, dan font yang sudah dikompilasi.

> **Catatan:** SmartAttendance menggunakan kombinasi CDN (Bootstrap) dan self-hosted asset di `public/`. Build Vite diperlukan untuk kompilasi Tailwind CSS dan beberapa komponen internal.

---

## 10. Konfigurasi Storage & Symlink

Jalankan perintah berikut untuk membuat symlink dari `public/storage` ke `storage/app/public`:

```bash
php artisan storage:link
```

Perintah ini hanya perlu dijalankan sekali. Tanpa symlink ini, file yang diupload (seperti foto profil) tidak akan dapat diakses dari browser.

---

## 11. Konfigurasi Domain Laragon

Laragon secara otomatis membuat domain virtual berdasarkan nama folder di `C:\laragon\www\`.

Karena folder project bernama `absensi-sekolah`, Laragon akan membuat domain:
```
http://absensi-sekolah.test
```

### Verifikasi

1. Buka Laragon → klik kanan di tray → **www** → folder project harus muncul
2. Klik kanan Laragon → **Preferences** → pastikan **Virtual Hosts** aktif
3. Buka browser, akses: `http://absensi-sekolah.test`

Jika domain tidak bisa diakses, coba restart Laragon atau jalankan sebagai Administrator.

---

## 12. Konfigurasi HTTPS (Kamera Face Recognition)

Fitur **Face Recognition** (presensi menggunakan kamera) membutuhkan **Secure Context (HTTPS)** karena browser modern membatasi akses kamera pada halaman HTTP.

### Opsi A: Aktifkan HTTPS di Laragon (Disarankan)

1. Klik kanan ikon Laragon di tray
2. Pilih **Laragon** → **Preferences** → **SSL**
3. Klik **Generate** untuk membuat self-signed certificate
4. Restart Laragon

Setelah HTTPS aktif, akses via:
```
https://absensi-sekolah.test
```

Browser mungkin menampilkan peringatan "Not Secure" karena self-signed cert — klik **Advanced** → **Proceed** (hanya perlu sekali).

Perbarui `APP_URL` di `.env`:
```env
APP_URL=https://absensi-sekolah.test
```

### Opsi B: Chrome Flags (Workaround tanpa HTTPS)

Jika tidak ingin mengatur HTTPS:

1. Buka Chrome, ketik di address bar: `chrome://flags`
2. Cari: **Insecure origins treated as secure**
3. Tambahkan: `http://absensi-sekolah.test`
4. Ubah status menjadi **Enabled**
5. Restart Chrome

---

## 13. Input Ulang API Key AI

> **Mengapa perlu input ulang?**  
> API key AI disimpan terenkripsi di database menggunakan `APP_KEY` Laravel. Setelah migrasi dengan `APP_KEY` baru, enkripsi lama tidak bisa didekripsi sehingga API key harus diinput ulang secara manual.

### Cara Input API Key

1. Login sebagai **Admin**
2. Buka halaman pengaturan AI: **`http://absensi-sekolah.test/admin/ai`**
3. Pilih provider yang ingin digunakan (Google Gemini / OpenAI / NVIDIA NIM)
4. Masukkan API key yang valid
5. Simpan dan aktifkan provider

### Mendapatkan API Key

| Provider | URL | Keterangan |
|---|---|---|
| Google Gemini | https://aistudio.google.com/app/apikey | Gratis hingga batas tertentu |
| OpenAI | https://platform.openai.com/api-keys | Berbayar |
| NVIDIA NIM | https://build.nvidia.com | Free tier tersedia |

---

## 14. Checklist Pengujian Pasca-Migrasi

Lakukan pengujian berikut setelah migrasi selesai:

### Akses Dasar

- [ ] Landing page terbuka di `http://absensi-sekolah.test`
- [ ] Halaman login dapat diakses
- [ ] Tidak ada error 500 atau 404 pada halaman utama

### Login & Autentikasi

- [ ] Login Admin berhasil (`admin@smartattendance.sch.id` / `admin123`)
- [ ] Login Guru berhasil (gunakan akun guru dari seeder)
- [ ] Login Siswa berhasil (gunakan akun siswa dari seeder)
- [ ] Logout berfungsi dengan benar
- [ ] Redirect ke dashboard yang sesuai setelah login

### Dashboard Admin

- [ ] Dashboard Admin terbuka tanpa error
- [ ] Statistik total siswa, guru, kehadiran hari ini tampil
- [ ] Grafik kehadiran tampil
- [ ] Aktivitas terbaru tampil
- [ ] Menu navigasi sidebar berfungsi

### Manajemen Data (Admin)

- [ ] Halaman Manajemen Siswa terbuka
- [ ] Tambah siswa baru berhasil
- [ ] Edit data siswa berhasil
- [ ] Hapus siswa berhasil
- [ ] Halaman Manajemen Guru terbuka
- [ ] Tambah guru baru berhasil
- [ ] Edit data guru berhasil
- [ ] Halaman Manajemen Admin terbuka
- [ ] **Tambah akun Admin baru berhasil** _(fitur wajib tersedia)_
- [ ] Edit akun admin berhasil

### Dashboard Guru

- [ ] Dashboard Guru terbuka tanpa error
- [ ] Statistik siswa, menunggu persetujuan, disetujui hari ini tampil
- [ ] Jadwal mengajar tampil
- [ ] Aktivitas terbaru tampil

### Manajemen Izin (Guru)

- [ ] Guru dapat melihat daftar pengajuan izin siswa
- [ ] Guru dapat menyetujui pengajuan izin
- [ ] Guru dapat menolak pengajuan izin
- [ ] Guru dapat mengajukan izin sendiri (menu izin guru)

### Dashboard Siswa

- [ ] Dashboard Siswa terbuka tanpa error
- [ ] Status presensi hari ini tampil
- [ ] Jadwal pembelajaran tampil
- [ ] Riwayat absensi tampil

### Presensi Kamera

- [ ] Halaman presensi kamera dapat diakses
- [ ] Kamera browser aktif (membutuhkan HTTPS atau Chrome Flags)
- [ ] Proses scan wajah berjalan (membutuhkan model face-api.js — lihat [Masalah yang Diketahui](#16-masalah-yang-diketahui))
- [ ] Status presensi terupdate setelah absensi berhasil

### Pengajuan Izin & Sakit (Siswa)

- [ ] Siswa dapat mengajukan izin
- [ ] Siswa dapat mengajukan keterangan sakit
- [ ] Riwayat pengajuan izin tampil di dashboard siswa

### Chatbot+

- [ ] Tombol Chatbot+ muncul di halaman yang sesuai
- [ ] Chatbot merespons pertanyaan
- [ ] Chatbot dapat menjawab pertanyaan tentang data absensi
- [ ] Chatbot mengenali nama provider AI yang aktif

### Statistik & Laporan

- [ ] Statistik kehadiran tampil di dashboard Admin
- [ ] Grafik kehadiran tampil dan akurat
- [ ] Riwayat absensi dapat difilter berdasarkan tanggal
- [ ] Data ekspor/laporan berfungsi (jika fitur tersedia)

### Konfigurasi AI

- [ ] Halaman `/admin/ai` dapat diakses
- [ ] Provider AI dapat dipilih dan diaktifkan
- [ ] API key berhasil disimpan
- [ ] Test koneksi AI berhasil

---

## 15. Kredensial Default

Akun berikut dibuat oleh seeder (`database/seeders/AdminSeeder.php`):

> **PENTING:** Ganti password default segera setelah pertama kali login di lingkungan produksi!

| Role | Email | Password | Keterangan |
|---|---|---|---|
| Admin | `admin@smartattendance.sch.id` | `admin123` | Akun admin utama |
| Admin (demo) | `admin2@smartattendance.sch.id` | `admin123` | Akun admin tambahan |
| Guru | `guru@smartattendance.sch.id` | `teacher123` | Akun guru demo |
| Siswa | `siswa@smartattendance.sch.id` | `student123` | Akun siswa demo |

> Jika database diimport dari backup, akun yang tersedia akan mengikuti data di backup tersebut.  
> Jika menggunakan fresh database (migrasi + seeder), gunakan akun di atas.

---

## 16. Masalah yang Diketahui

### ⚠️ Face Recognition: Model File Tidak Ada

**Masalah:**  
Direktori `public/models/` yang seharusnya berisi model weights untuk library `face-api.js` tidak tersedia di project. Model weights ini diperlukan agar fitur pengenalan wajah (face recognition) dapat berjalan.

**Dampak:**  
Fitur presensi menggunakan kamera/wajah tidak akan berfungsi sampai model weights ditambahkan.

**Solusi:**  
1. Download model weights `face-api.js` dari repository resmi:  
   **https://github.com/justadudewhohacks/face-api.js/tree/master/weights**
2. Buat folder: `C:\laragon\www\absensi-sekolah\public\models\`
3. Salin semua file model weights ke folder tersebut

Model yang dibutuhkan biasanya:
- `tiny_face_detector_model-weights_manifest.json`
- `tiny_face_detector_model-shard1`
- `face_landmark_68_model-weights_manifest.json`
- `face_landmark_68_model-shard1`
- `face_recognition_model-weights_manifest.json`
- `face_recognition_model-shard1`
- `face_recognition_model-shard2`
- `face_expression_models-weights_manifest.json`
- `face_expression_models-shard1`

### ⚠️ API Key AI Harus Diinput Ulang

Seperti dijelaskan di bagian [13](#13-input-ulang-api-key-ai), API key AI yang tersimpan di database menggunakan enkripsi berbasis `APP_KEY`. Setelah migrasi dengan `APP_KEY` baru, key harus diinput ulang di halaman `/admin/ai`.

### ℹ️ Cache & Session

Setelah migrasi, disarankan untuk membersihkan cache:
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

## 17. Troubleshooting

### Error: "No application encryption key has been specified"

**Penyebab:** `APP_KEY` di `.env` kosong.

**Solusi:**
```bash
php artisan key:generate
```

### Error: "SQLSTATE[HY000] [1049] Unknown database"

**Penyebab:** Database `smart_attendance` belum dibuat di MySQL.

**Solusi:** Buat database terlebih dahulu via phpMyAdmin atau CLI (lihat bagian [6](#6-import-database-di-laptop-baru)).

### Error: "Class not found" atau "Target class not found"

**Penyebab:** `vendor/` belum terinstal.

**Solusi:**
```bash
composer install --no-interaction
php artisan optimize:clear
```

### Error: "Unable to locate file in Vite manifest"

**Penyebab:** `public/build/` belum di-build atau hasil build tidak kompatibel.

**Solusi:**
```bash
npm install
npm run build
```

### Error: Halaman Blank / 500 Internal Server Error

**Penyebab:** Kemungkinan konfigurasi `.env` salah atau permission storage.

**Solusi:**
1. Periksa `storage/logs/laravel.log` untuk detail error
2. Pastikan folder `storage/` dan `bootstrap/cache/` bisa ditulis (write permission)
3. Jalankan:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

### Error: Kamera Tidak Aktif / "Camera access denied"

**Penyebab:** Browser memblokir akses kamera pada halaman HTTP.

**Solusi:** Aktifkan HTTPS di Laragon, atau gunakan Chrome Flags workaround (lihat bagian [12](#12-konfigurasi-https-kamera-face-recognition)).

### Chatbot+ Tidak Merespons

**Penyebab:**  
- API key AI belum diinput ulang setelah migrasi
- Provider AI tidak aktif

**Solusi:**  
Login sebagai Admin → buka `/admin/ai` → input API key yang valid → aktifkan provider.

### Domain .test Tidak Bisa Diakses

**Penyebab:** Laragon belum dikonfigurasi dengan benar atau hosts file belum diperbarui.

**Solusi:**
1. Jalankan Laragon sebagai **Administrator**
2. Klik kanan Laragon → **Preferences** → **Virtual Hosts**: pastikan aktif
3. Restart Laragon
4. Periksa file: `C:\Windows\System32\drivers\etc\hosts` — seharusnya ada baris:
   ```
   127.0.0.1   absensi-sekolah.test
   ```
   Jika tidak ada, tambahkan manual (jalankan Notepad sebagai Admin).

---

## Informasi Project

| Item | Detail |
|---|---|
| Nama Project | SmartAttendance |
| Framework | Laravel 13.x |
| PHP | 8.3.x |
| Database | MySQL 8.4.x |
| Frontend | Tailwind CSS v4, Vite 8.x, Bootstrap CDN |
| AI Provider | Google Gemini / OpenAI / NVIDIA NIM (konfigurasi di `/admin/ai`) |
| Face Recognition | face-api.js (model weights perlu ditambahkan manual) |

---

*Dokumen ini dibuat secara otomatis sebagai bagian dari persiapan migrasi SmartAttendance.*  
*Terakhir diperbarui: November 2026*

