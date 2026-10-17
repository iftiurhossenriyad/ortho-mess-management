$ErrorActionPreference = 'Stop'

$projectRoot = $PSScriptRoot
$existingServer = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue
if ($existingServer) {
    throw 'Stop the running Ortho PHP server before starting with SMS credentials; restart this script afterward.'
}

$env:TWILIO_ACCOUNT_SID = (Read-Host 'Twilio Account SID').Trim()
if ([string]::IsNullOrWhiteSpace($env:TWILIO_ACCOUNT_SID)) {
    throw 'Twilio Account SID is required to enable SMS recovery.'
}

$secureToken = Read-Host 'Twilio Auth Token' -AsSecureString
$tokenPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureToken)
try {
    $env:TWILIO_AUTH_TOKEN = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($tokenPointer)
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($tokenPointer)
    $secureToken.Dispose()
}
if ([string]::IsNullOrWhiteSpace($env:TWILIO_AUTH_TOKEN)) {
    throw 'Twilio Auth Token is required to enable SMS recovery.'
}

$env:TWILIO_FROM_NUMBER = (Read-Host 'Twilio From Number (for example +17372508034)').Trim()
if ([string]::IsNullOrWhiteSpace($env:TWILIO_FROM_NUMBER)) {
    throw 'A Twilio sender number is required to enable SMS recovery.'
}

& (Join-Path $projectRoot 'start.ps1')
