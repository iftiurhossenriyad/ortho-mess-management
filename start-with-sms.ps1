$ErrorActionPreference = 'Stop'

$projectRoot = $PSScriptRoot
$env:TWILIO_ACCOUNT_SID = Read-Host 'Twilio Account SID'
$secureToken = Read-Host 'Twilio Auth Token' -AsSecureString
$tokenPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureToken)
try {
    $env:TWILIO_AUTH_TOKEN = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($tokenPointer)
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($tokenPointer)
}
$env:TWILIO_FROM_NUMBER = Read-Host 'Twilio From Number (for example +17372508034)'

& (Join-Path $projectRoot 'start.ps1')
