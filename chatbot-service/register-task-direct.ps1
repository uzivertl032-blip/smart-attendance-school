$TaskName = "SmartAttendanceChatbot"
$VbsPath  = "C:\laragon\www\absensi-sekolah\chatbot-service\start-chatbot.vbs"
$Username = $env:USERDOMAIN + "\" + $env:USERNAME

$Action    = New-ScheduledTaskAction -Execute "wscript.exe" -Argument ('"{0}"' -f $VbsPath)
$Trigger   = New-ScheduledTaskTrigger -AtLogon -User $Username
$Settings  = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -ExecutionTimeLimit (New-TimeSpan -Hours 0) -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 2)
$Principal = New-ScheduledTaskPrincipal -UserId $Username -LogonType Interactive -RunLevel Highest

$result = Register-ScheduledTask -TaskName $TaskName -Action $Action -Trigger $Trigger -Settings $Settings -Principal $Principal -Description "Chatbot backend Node.js untuk Smart Attendance School" -Force 2>&1
if ($LASTEXITCODE -eq 0 -or $result.TaskName -eq $TaskName) {
    Write-Output "SUCCESS: Task '$TaskName' berhasil didaftarkan untuk user $Username"
} else {
    Write-Output "ERROR: $result"
    exit 1
}
