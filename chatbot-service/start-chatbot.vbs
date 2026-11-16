' ============================================================
' Smart Attendance School — Chatbot Backend Launcher
' Menjalankan Node.js server.js di background tanpa jendela
' ============================================================

Dim oShell, fso, scriptDir, scriptPath
Set oShell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")

' Path ke PowerShell script (relatif ke lokasi launcher ini)
scriptDir = fso.GetParentFolderName(WScript.ScriptFullName)
scriptPath = scriptDir & "\start-chatbot.ps1"

' Fallback ke path absolut jika path relatif tidak ditemukan
If Not fso.FileExists(scriptPath) Then
    scriptDir = "C:\laragon\www\absensi-sekolah\chatbot-service"
    scriptPath = scriptDir & "\start-chatbot.ps1"
End If

' Pastikan working directory adalah folder chatbot-service
If fso.FolderExists(scriptDir) Then
    oShell.CurrentDirectory = scriptDir
End If

' Jalankan PowerShell script tersembunyi (0 = SW_HIDE tersembunyi, False = async)
oShell.Run "powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File """ & scriptPath & """", 0, False

Set fso = Nothing
Set oShell = Nothing
