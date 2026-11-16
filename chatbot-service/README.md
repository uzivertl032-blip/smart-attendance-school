# Chatbot AI Service — Smart Attendance School

Layanan backend mandiri berbasis **Node.js (Express)** yang menghubungkan antarmuka chatbot di halaman Beranda Smart Attendance School dengan penyedia AI **Nous Research** (menggunakan model Hermes atau model OpenAI-compatible lainnya).

---

## 1. Arsitektur & Keamanan

- **Aplikasi Utama:** Laravel 11 / PHP 8.3 (`c:\laragon\www\absensi-sekolah`)
- **Chatbot Backend:** Node.js Express (`c:\laragon\www\absensi-sekolah\chatbot-service`)
- **Penyedia AI:** Nous Research API (`https://inference-api.nousresearch.com/v1`)
- **Keamanan Kunci API:**
  - `NOUS_API_KEY` disimpan **hanya** pada file `chatbot-service/.env`.
  - Kunci API **tidak pernah** dikirim ke browser atau diekspos melalui frontend.
  - File `.env` telah didaftarkan ke `.gitignore`.
  - Dilengkapi dengan *rate limiting* (maksimal 30 request/menit per IP) dan validasi panjang pesan (maksimal 1000 karakter).

---

## 2. Persyaratan Sistem

- **Node.js v18+ LTS** (Pada Laragon, tersedia di `C:\laragon\bin\nodejs\node-v22`).
- **NPM** (Bawaan Node.js).
- Laragon Web Server (Apache/Nginx & MySQL aktif untuk aplikasi Laravel).

---

## 3. Instalasi & Konfigurasi

### Langkah 1: Masuk ke direktori chatbot-service
Buka terminal dan arahkan ke folder:
```bash
cd C:\laragon\www\absensi-sekolah\chatbot-service
```

### Langkah 2: Instal dependensi
```bash
npm install
```
*(Jika menggunakan PowerShell di Windows dan terjadi pembatasan script execution policy, gunakan `cmd /c "npm install"`)*.

### Langkah 3: Konfigurasi file `.env`
Salin template `.env.example` menjadi `.env`:
```bash
cp .env.example .env
```
Buka file `.env` dan masukkan API Key resmi dari [Nous Research Portal](https://portal.nousresearch.com):

```env
PORT=3001
NOUS_API_BASE_URL=https://inference-api.nousresearch.com/v1
NOUS_API_KEY=masukkan_api_key_resmi_anda_di_sini
NOUS_MODEL=hermes-3-llama-70b
ALLOWED_ORIGINS=http://localhost,http://127.0.0.1,http://localhost:8000,http://127.0.0.1:8000,http://absensi-sekolah.test
```


---

## 4. Cara Menjalankan Layanan di Windows & Laragon

### Opsi A: Melalui Laragon Terminal (Cmder)
1. Buka Laragon, klik kanan di mana saja lalu pilih **Laragon -> Terminal** (atau klik tombol **Terminal** di jendela Laragon).
2. Jalankan perintah berikut:
   ```bash
   cd C:\laragon\www\absensi-sekolah\chatbot-service
   npm start
   ```

### Opsi B: Melalui Windows PowerShell
Jika Node.js belum dimasukkan ke variabel PATH sistem global, tambahkan path Node.js bawaan Laragon pada sesi PowerShell:
```powershell
$env:Path = "C:\laragon\bin\nodejs\node-v22;" + $env:Path
cd C:\laragon\www\absensi-sekolah\chatbot-service
node server.js
```

Layanan akan aktif dan mencetak pesan:
```text
[Chatbot Service] Berjalan di http://localhost:3001
[Chatbot Service] Model aktif: hermes-3-llama-70b
[Chatbot Service] Base URL: https://inference-api.nousresearch.com/v1
```

---

## 5. Menjalankan Aplikasi Laravel

Di terminal terpisah, jalankan server Laravel seperti biasa:
```bash
cd C:\laragon\www\absensi-sekolah
php artisan serve
```
Buka peramban di `http://localhost:8000`.

---

## 6. Skenario & Pengujian Endpoint

Layanan telah dilengkapi penanganan kasus uji komprehensif:

### 1. Uji Status Layanan (Health Check)
```bash
curl http://localhost:3001/health
# atau
curl http://localhost:3001/api/health
```
**Respons:**
```json
{
  "success": true,
  "status": "healthy",
  "service": "Smart Attendance Chatbot Service",
  "model": "hermes-3-llama-70b"
}
```

### 2. Uji Penolakan Pertanyaan di Luar Topik (Guardrail)
```bash
curl -X POST http://localhost:3001/api/chat \
  -H "Content-Type: application/json" \
  -d '{"message": "Buatkan kode Python"}'
```
**Respons:**
```json
{
  "success": true,
  "reply": "Maaf, saya adalah asisten khusus Smart Attendance School. Saya hanya dapat membantu masalah absensi, login, kamera, verifikasi wajah, keterlambatan, serta pengajuan izin dan sakit. Apakah ada kendala pada aplikasi absensi yang bisa saya bantu?",
  "offTopic": true
}
```

### 3. Uji Sapaan Singkat
```bash
curl -X POST http://localhost:3001/api/chat \
  -H "Content-Type: application/json" \
  -d '{"message": "hai"}'
```

### 4. Uji Otomatis Seluruh Skenario
Jalankan skrip tes komprehensif:
```bash
node test.js
```
