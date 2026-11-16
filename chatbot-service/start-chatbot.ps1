# ============================================================
# Smart Attendance School - Chatbot Backend Startup Script
# File    : start-chatbot.ps1
# Tujuan  : Memastikan hanya 1 instance berjalan di port 3001
#           lalu menjalankan node server.js di background.
# ============================================================

$ScriptDir = $PSScriptRoot
if (-not $ScriptDir) {
    $ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
}
if (-not $ScriptDir) {
    $ScriptDir = "C:\laragon\www\absensi-sekolah\chatbot-service"
}
$WorkingDir = $ScriptDir
Set-Location $WorkingDir

$NodeExe    = "C:\Program Files\nodejs\node.exe"
if (-not (Test-Path $NodeExe)) {
    $resolvedNode = (Get-Command node -ErrorAction SilentlyContinue).Source
    if ($resolvedNode -and (Test-Path $resolvedNode)) {
        $NodeExe = $resolvedNode
    }
}

$ServerJs   = Join-Path $WorkingDir "server.js"
$LogDir     = Join-Path $WorkingDir "logs"
$LogFile    = Join-Path $LogDir "chatbot-startup.log"
$Port       = 3001

# ---- Buat direktori logs jika belum ada ----
if (-not (Test-Path $LogDir)) {
    New-Item -ItemType Directory -Path $LogDir -Force | Out-Null
}

# ---- Fungsi log (tidak mencatat API key / secrets) ----
function Write-Log {
    param([string]$Message, [string]$Level = "INFO")
    $ts = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $entry = "[$ts] [$Level] $Message"
    Add-Content -Path $LogFile -Value $entry -Encoding UTF8
}

Write-Log "=== Chatbot startup script dimulai ==="
Write-Log "NodeExe    : $NodeExe"
Write-Log "WorkingDir : $WorkingDir"

# ---- Validasi Node.js tersedia ----
if (-not (Test-Path $NodeExe)) {
    Write-Log "GAGAL: node.exe tidak ditemukan di $NodeExe" "ERROR"
    exit 1
}

# ---- Validasi server.js tersedia ----
if (-not (Test-Path $ServerJs)) {
    Write-Log "GAGAL: server.js tidak ditemukan di $ServerJs" "ERROR"
    exit 1
}

# ---- Cek apakah port 3001 sudah terpakai (cepat & akurat) ----
$portInUse = $false
try {
    $listeners = [System.Net.NetworkInformation.IPGlobalProperties]::GetIPGlobalProperties().GetActiveTcpListeners()
    if ($listeners | Where-Object { $_.Port -eq $Port }) {
        $portInUse = $true
        Write-Log "Port $Port sudah aktif (mendengarkan koneksi). Melewati startup agar tidak terjadi instance ganda."
        exit 0
    }
} catch {
    $netstatResult = netstat -ano 2>$null | Select-String ":$Port\s.*LISTENING"
    if ($netstatResult) {
        $portInUse = $true
        Write-Log "Port $Port sudah aktif (terdeteksi via netstat). Melewati startup."
        exit 0
    }
}

# ---- Cek apakah proses node.exe untuk server.js sudah berjalan ----
$existingNode = Get-CimInstance Win32_Process -Filter "Name='node.exe'" -ErrorAction SilentlyContinue |
    Where-Object { $_.CommandLine -and $_.CommandLine -like "*server.js*" }
if ($existingNode) {
    $pids = ($existingNode | ForEach-Object { $_.ProcessId }) -join ", "
    Write-Log "Proses node server.js sudah berjalan (PID: $pids). Melewati startup."
    exit 0
}

# ---- Jalankan server.js sebagai proses terpisah ----
Write-Log "Memulai node server.js..."

$stdoutLog = "$LogDir\chatbot-stdout.log"
$stderrLog = "$LogDir\chatbot-stderr.log"

# Gunakan Start-Process agar tidak bergantung pada terminal yang terbuka
$processArgs = @{
    FilePath               = $NodeExe
    ArgumentList           = $ServerJs
    WorkingDirectory       = $WorkingDir
    WindowStyle            = "Hidden"
    RedirectStandardOutput = $stdoutLog
    RedirectStandardError  = $stderrLog
    PassThru               = $true
}

try {
    $proc = Start-Process @processArgs
    Write-Log "Server berhasil dimulai. PID: $($proc.Id)"

    # Verifikasi server merespons dengan retry loop (maksimal 5 percobaan x 3 detik = 15 detik)
    $maxAttempts = 5
    $attempt = 0
    $isHealthy = $false

    while ($attempt -lt $maxAttempts -and -not $isHealthy) {
        $attempt++
        Start-Sleep -Seconds 3
        try {
            $response = Invoke-WebRequest -Uri "http://localhost:$Port/health" -TimeoutSec 5 -UseBasicParsing -ErrorAction Stop
            if ($response.StatusCode -eq 200) {
                $isHealthy = $true
                Write-Log "Health check BERHASIL (percobaan ke-$attempt) - server merespons di http://localhost:$Port/health"
            } else {
                Write-Log "Health check mengembalikan status $($response.StatusCode)" "WARN"
            }
        } catch {
            if ($attempt -eq $maxAttempts) {
                Write-Log "Health check gagal setelah $maxAttempts percobaan (server mungkin masih inisialisasi): $($_.Exception.Message)" "WARN"
            }
        }
    }
} catch {
    Write-Log "GAGAL menjalankan server: $($_.Exception.Message)" "ERROR"
    exit 1
}

Write-Log "=== Script selesai ==="
