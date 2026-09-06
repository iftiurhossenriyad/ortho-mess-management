$ErrorActionPreference = 'Stop'

$projectRoot = $PSScriptRoot
$php = 'D:\XAMPP\php\php.exe'

if (-not (Test-Path $php)) {
    throw "XAMPP PHP was not found at $php."
}

$env:TWILIO_ACCOUNT_SID = Read-Host 'Twilio Account SID'
$secureToken = Read-Host 'Twilio Auth Token' -AsSecureString
$tokenPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureToken)
try {
    $env:TWILIO_AUTH_TOKEN = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($tokenPointer)
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($tokenPointer)
}
$env:TWILIO_FROM_NUMBER = Read-Host 'Twilio From Number (for example +17372508034)'

Set-Location $projectRoot
Write-Host 'PHP server with SMS OTP configuration: http://localhost:8000'
Write-Host 'Press Ctrl+C to stop the server.'
& $php -S localhost:8000 -t $projectRoot
