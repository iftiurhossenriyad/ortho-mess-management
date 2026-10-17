$ErrorActionPreference = 'Stop'

$projectRoot = $PSScriptRoot
$existingServer = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue
if ($existingServer) {
    throw 'Stop the running Ortho PHP server before starting with OAuth credentials; restart this script afterward.'
}

$env:OAUTH_BASE_URL = (Read-Host 'App base URL [http://localhost:8000]').Trim()
if (-not $env:OAUTH_BASE_URL) {
    $env:OAUTH_BASE_URL = 'http://localhost:8000'
}
$env:OAUTH_BASE_URL = $env:OAUTH_BASE_URL.TrimEnd('/')

try {
    $baseUri = [Uri] $env:OAUTH_BASE_URL
} catch {
    throw 'Enter a valid application base URL.'
}
if ($baseUri.Scheme -notin @('http', 'https')) {
    throw 'The application base URL must use HTTP or HTTPS.'
}
if ($baseUri.Scheme -ne 'https' -and $baseUri.Host -notin @('localhost', '127.0.0.1', '::1')) {
    throw 'Social sign-in requires HTTPS outside localhost.'
}

function Read-OAuthSecret([string] $Prompt) {
    $secureValue = Read-Host $Prompt -AsSecureString
    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureValue)
    try {
        return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    } finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
        $secureValue.Dispose()
    }
}

$providers = @(
    @{ Name = 'Google'; Prefix = 'OAUTH_GOOGLE'; AppIdPrompt = 'Google OAuth Client ID (leave empty to skip)' },
    @{ Name = 'GitHub'; Prefix = 'OAUTH_GITHUB'; AppIdPrompt = 'GitHub OAuth Client ID (leave empty to skip)' },
    @{ Name = 'Facebook'; Prefix = 'OAUTH_FACEBOOK'; AppIdPrompt = 'Facebook App ID (leave empty to skip)' }
)
$configuredProviders = 0

foreach ($provider in $providers) {
    $clientId = (Read-Host $provider.AppIdPrompt).Trim()
    if (-not $clientId) {
        continue
    }

    $clientSecret = Read-OAuthSecret "$($provider.Name) OAuth Client Secret"
    if (-not $clientSecret) {
        throw "$($provider.Name) OAuth Client Secret is required when a Client ID is provided."
    }

    [Environment]::SetEnvironmentVariable("$($provider.Prefix)_CLIENT_ID", $clientId, 'Process')
    [Environment]::SetEnvironmentVariable("$($provider.Prefix)_CLIENT_SECRET", $clientSecret, 'Process')
    $configuredProviders++
}

if ($configuredProviders -eq 0) {
    throw 'Configure at least one OAuth provider before starting the app.'
}

Write-Host "Starting Ortho with $configuredProviders social sign-in provider(s). Secrets are held only in this PowerShell process."
& (Join-Path $projectRoot 'start.ps1')
