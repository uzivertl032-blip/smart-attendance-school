# ============================================================
# Smart Attendance School — Register Task Scheduler
# File   : register-task.ps1
# Tujuan : Mendaftarkan backend chatbot ke Windows Task Scheduler
#          agar berjalan otomatis di background saat pengguna login.
# ============================================================

# 1. Resolusi Path Dinamis & Akurat
$WorkingDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
if (-not $WorkingDir) {
    $WorkingDir = "C:\laragon\www\absensi-sekolah\chatbot-service"
}
$TaskName   = "SmartAttendanceChatbot"
$VbsPath    = Join-Path $WorkingDir "start-chatbot.vbs"
$Ps1Path    = Join-Path $WorkingDir "start-chatbot.ps1"
$ServerJs   = Join-Path $WorkingDir "server.js"
$NodeExe    = "C:\Program Files\nodejs\node.exe"
$Username   = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name

# 2. Self-Elevation (Otomatis minta hak Administrator jika belum)
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host ""
    Write-Host "Membuka ulang script sebagai Administrator (UAC Prompt)..." -ForegroundColor Yellow
    $scriptToRun = $MyInvocation.MyCommand.Definition
    if (-not $scriptToRun) {
        $scriptToRun = Join-Path $WorkingDir "register-task.ps1"
    }
    try {
        Start-Process powershell.exe -ArgumentList ("-NoProfile -ExecutionPolicy Bypass -File `"{0}`"" -f $scriptToRun) -Verb RunAs
        exit 0
    } catch {
        Write-Host "ERROR: Memerlukan hak Administrator untuk mendaftarkan task!" -ForegroundColor Red
        Write-Host "Silakan klik kanan powershell/terminal lalu Run as Administrator." -ForegroundColor Yellow
        pause
        exit 1
    }
}

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "   SMART ATTENDANCE SCHOOL - REGISTRASI AUTO-START CHATBOT   " -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "Task Name        : $TaskName"
Write-Host "User Target      : $Username"
Write-Host "Working Directory: $WorkingDir"
Write-Host "VBS Launcher     : $VbsPath"
Write-Host "Startup Script   : $Ps1Path"
Write-Host ""

# 3. Validasi Keberadaan Seluruh File Kunci
if (-not (Test-Path $VbsPath)) {
    Write-Host "ERROR: File launcher VBS tidak ditemukan di: $VbsPath" -ForegroundColor Red
    pause
    exit 1
}

if (-not (Test-Path $Ps1Path)) {
    Write-Host "ERROR: File start-chatbot.ps1 tidak ditemukan di: $Ps1Path" -ForegroundColor Red
    pause
    exit 1
}

if (-not (Test-Path $ServerJs)) {
    Write-Host "ERROR: File backend server.js tidak ditemukan di: $ServerJs" -ForegroundColor Red
    pause
    exit 1
}

if (-not (Test-Path $NodeExe)) {
    Write-Host "PERINGATAN: node.exe tidak ditemukan di $NodeExe. Pastikan Node.js terinstal." -ForegroundColor Yellow
}

$XmlPath    = Join-Path $WorkingDir "SmartAttendanceChatbot.xml"

# 4. Validasi Keberadaan File Definisi XML
if (-not (Test-Path $XmlPath)) {
    Write-Host "ERROR: File definisi task XML tidak ditemukan di: $XmlPath" -ForegroundColor Red
    pause
    exit 1
}

# 5. Registrasi Task Secara Atomik menggunakan schtasks dengan XML resmi
# Menggunakan XML memastikan DisallowStartIfOnBatteries=false, Delay=PT10S, dan WorkingDirectory terpasang sempurna tanpa bug cmdlet
try {
    $schtasksOutput = & schtasks.exe /create /tn $TaskName /xml $XmlPath /f 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "schtasks gagal mendaftarkan task: $schtasksOutput"
    }

    Write-Host "Task '$TaskName' berhasil didaftarkan dan aktif!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Detail Konfigurasi:" -ForegroundColor White
    Write-Host "  - Trigger        : Saat pengguna ($Username) login (delay 10 detik)"
    Write-Host "  - Launcher       : wscript.exe -> start-chatbot.vbs (tanpa jendela hitam/popup)"
    Write-Host "  - Working Dir    : $WorkingDir"
    Write-Host "  - Target Server  : node server.js (Port 3001)"
    Write-Host "  - Battery Mode   : Tetap aktif baik memakai baterai maupun charger"
    Write-Host ""
    Write-Host "Perintah Kelola Task (Opsional):" -ForegroundColor DarkGray
    Write-Host "  Uji jalankan   : schtasks /run /tn $TaskName" -ForegroundColor DarkGray
    Write-Host "  Cek status     : powershell -ExecutionPolicy Bypass -File .\status-chatbot.ps1" -ForegroundColor DarkGray
    Write-Host "  Hentikan       : powershell -ExecutionPolicy Bypass -File .\stop-chatbot.ps1" -ForegroundColor DarkGray
    Write-Host "  Hapus auto-run : schtasks /delete /tn $TaskName /f" -ForegroundColor DarkGray
    Write-Host ""
} catch {
    Write-Host "ERROR saat mendaftar task: $($_.Exception.Message)" -ForegroundColor Red
    pause
    exit 1
}

Write-Host "Selesai. Tekan ENTER untuk menutup..." -ForegroundColor Cyan
pause
