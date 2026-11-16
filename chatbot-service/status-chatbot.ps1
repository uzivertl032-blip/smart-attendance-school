# ============================================================
# Smart Attendance School - Chatbot Status Check
# File   : status-chatbot.ps1
# Tujuan : Periksa apakah chatbot backend sedang berjalan
# ============================================================

$Port = 3001

Write-Host ""
Write-Host "=== STATUS CHATBOT BACKEND ===" -ForegroundColor Cyan

# Cek port 3001 dan PID pemiliknya
$portActive = $false
$owningPid  = $null

try {
    $conn = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue
    if ($conn) {
        $portActive = $true
        $owningPid  = $conn[0].OwningProcess
    }
} catch {
    $ns = netstat -ano 2>$null | Select-String ":$Port\s.*LISTENING"
    if ($ns) {
        $portActive = $true
        $parts = ($ns -split '\s+') | Where-Object { $_ -ne '' }
        if ($parts.Length -ge 5) {
            $owningPid = [int]$parts[-1]
        }
    }
}

# Cek proses node.exe (via CommandLine atau via Port OwningProcess)
$detectedPid = $null
$nodes = Get-CimInstance Win32_Process -Filter "Name='node.exe'" -ErrorAction SilentlyContinue |
    Where-Object { $_.CommandLine -and $_.CommandLine -like "*server.js*" }

if ($nodes) {
    $detectedPid = $nodes[0].ProcessId
} elseif ($owningPid) {
    $proc = Get-Process -Id $owningPid -ErrorAction SilentlyContinue
    if ($proc -and $proc.ProcessName -eq "node") {
        $detectedPid = $proc.Id
    }
}

if ($detectedPid) {
    Write-Host "[OK] Proses Node.js     : BERJALAN (PID: $detectedPid)" -ForegroundColor Green
} else {
    Write-Host "[X]  Proses Node.js     : TIDAK BERJALAN" -ForegroundColor Red
}

if ($portActive) {
    Write-Host "[OK] Port $Port          : AKTIF" -ForegroundColor Green
} else {
    Write-Host "[X]  Port $Port          : TIDAK AKTIF" -ForegroundColor Red
}

# Health check HTTP
try {
    $r = Invoke-WebRequest -Uri "http://localhost:$Port/health" -TimeoutSec 5 -UseBasicParsing -ErrorAction Stop
    $json = $r.Content | ConvertFrom-Json
    Write-Host "[OK] HTTP Health Check  : OK - Status: $($json.status), Model: $($json.model)" -ForegroundColor Green
} catch {
    Write-Host "[X]  HTTP Health Check  : GAGAL - $($_.Exception.Message)" -ForegroundColor Red
}

# Info log terbaru
$LogFile = "C:\laragon\www\absensi-sekolah\chatbot-service\logs\chatbot-startup.log"
if (Test-Path $LogFile) {
    Write-Host ""
    Write-Host "--- 5 baris log terakhir ---" -ForegroundColor DarkGray
    Get-Content $LogFile -Tail 5 | ForEach-Object { Write-Host $_ -ForegroundColor DarkGray }
}

Write-Host ""
