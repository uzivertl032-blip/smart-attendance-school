# ============================================================
# Smart Attendance School - Chatbot Stop Script
# File   : stop-chatbot.ps1
# Tujuan : Hentikan semua proses node.exe yang menjalankan server.js
# ============================================================

$LogDir  = "C:\laragon\www\absensi-sekolah\chatbot-service\logs"
$LogFile = "$LogDir\chatbot-startup.log"
$Port    = 3001

function Write-Log {
    param([string]$Message, [string]$Level = "INFO")
    $ts = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Add-Content -Path $LogFile -Value "[$ts] [$Level] $Message" -Encoding UTF8
}

$pidsToKill = [System.Collections.Generic.List[int]]::new()

# 1. Cari berdasarkan CommandLine
$nodes = Get-CimInstance Win32_Process -Filter "Name='node.exe'" -ErrorAction SilentlyContinue |
    Where-Object { $_.CommandLine -and $_.CommandLine -like "*server.js*" }
foreach ($n in $nodes) {
    if (-not $pidsToKill.Contains($n.ProcessId)) {
        $pidsToKill.Add($n.ProcessId)
    }
}

# 2. Cari berdasarkan proses yang mendengarkan di port 3001
try {
    $tcp = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue
    if ($tcp) {
        $ownerPid = $tcp[0].OwningProcess
        $p = Get-Process -Id $ownerPid -ErrorAction SilentlyContinue
        if ($p -and $p.ProcessName -eq "node" -and (-not $pidsToKill.Contains($ownerPid))) {
            $pidsToKill.Add($ownerPid)
        }
    }
} catch {}

if ($pidsToKill.Count -gt 0) {
    foreach ($pidNum in $pidsToKill) {
        try {
            Stop-Process -Id $pidNum -Force
            Write-Log "Chatbot dihentikan. PID: $pidNum"
            Write-Host "Chatbot dihentikan. PID: $pidNum" -ForegroundColor Green
        } catch {
            Write-Log "Gagal menghentikan PID ${pidNum}: $($_.Exception.Message)" "ERROR"
            Write-Host "Gagal menghentikan PID ${pidNum}: $($_.Exception.Message)" -ForegroundColor Red
        }
    }
} else {
    Write-Host "Tidak ada proses chatbot (node server.js) yang sedang berjalan." -ForegroundColor Yellow
    Write-Log "stop-chatbot dipanggil tapi tidak ada proses yang ditemukan."
}
