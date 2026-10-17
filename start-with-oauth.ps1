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

$googleClientFile = Join-Path $env:LOCALAPPDATA 'OrthoMessManagement\credentials\google-client.json'
if (-not (Test-Path -LiteralPath $googleClientFile -PathType Leaf)) {
    $googleClientFile = (Read-Host 'Google OAuth client JSON file path').Trim('"', ' ')
}
if (-not (Test-Path -LiteralPath $googleClientFile -PathType Leaf)) {
    throw 'The Google OAuth client JSON file was not found.'
}

try {
    $googleClient = Get-Content -LiteralPath $googleClientFile -Raw | ConvertFrom-Json
    $googleWebClient = $googleClient.web
} catch {
    throw 'The Google OAuth client file is not valid JSON.'
}

$googleCallback = "$($env:OAUTH_BASE_URL)/oauth.php?provider=google"
if ([string]::IsNullOrWhiteSpace($googleWebClient.client_id) -or [string]::IsNullOrWhiteSpace($googleWebClient.client_secret) -or $googleWebClient.redirect_uris -notcontains $googleCallback) {
    throw 'The Google OAuth JSON must contain a web client with the matching Ortho callback URI.'
}

[Environment]::SetEnvironmentVariable('OAUTH_GOOGLE_CLIENT_ID', $googleWebClient.client_id, 'Process')
[Environment]::SetEnvironmentVariable('OAUTH_GOOGLE_CLIENT_SECRET', $googleWebClient.client_secret, 'Process')
$configuredProviders = 1

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
    @{ Name = 'GitHub'; Prefix = 'OAUTH_GITHUB'; AppIdPrompt = 'GitHub OAuth Client ID (leave empty to skip)' },
    @{ Name = 'Facebook'; Prefix = 'OAUTH_FACEBOOK'; AppIdPrompt = 'Facebook App ID (leave empty to skip)' }
)

foreach ($provider in $providers) {
    $providerName = $provider.Name.ToLowerInvariant()
    $providerCredentialFile = Join-Path $env:LOCALAPPDATA "OrthoMessManagement\credentials\oauth-client-$providerName.json"
    if (Test-Path -LiteralPath $providerCredentialFile -PathType Leaf) {
        try {
            $providerCredentials = Get-Content -LiteralPath $providerCredentialFile -Raw | ConvertFrom-Json
            $providerClient = $providerCredentials.web
        } catch {
            throw "$($provider.Name) OAuth client file is not valid JSON."
        }

        $providerCallback = "$($env:OAUTH_BASE_URL)/oauth.php?provider=$providerName"
        if ([string]::IsNullOrWhiteSpace($providerClient.client_id) -or [string]::IsNullOrWhiteSpace($providerClient.client_secret) -or $providerClient.redirect_uris -notcontains $providerCallback) {
            throw "$($provider.Name) OAuth client file must contain a web client with the matching Ortho callback URI."
        }

        [Environment]::SetEnvironmentVariable("$($provider.Prefix)_CLIENT_ID", $providerClient.client_id, 'Process')
        [Environment]::SetEnvironmentVariable("$($provider.Prefix)_CLIENT_SECRET", $providerClient.client_secret, 'Process')
        $configuredProviders++
        continue
    }

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

Write-Host "Starting Ortho with $configuredProviders social sign-in provider(s). Provider secrets are not stored in project files."
& (Join-Path $projectRoot 'start.ps1')
