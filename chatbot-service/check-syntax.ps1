$target = "C:\laragon\www\absensi-sekolah\chatbot-service\start-chatbot.ps1"
$errors = $null
$null = [System.Management.Automation.Language.Parser]::ParseFile($target, [ref]$null, [ref]$errors)
if ($errors.Count -eq 0) {
    Write-Host "SYNTAX OK: start-chatbot.ps1 tidak memiliki syntax error"
} else {
    Write-Host "SYNTAX ERRORS ditemukan:"
    foreach ($e in $errors) {
        Write-Host "  Line $($e.Extent.StartLineNumber): $($e.Message)"
    }
}
