$ErrorActionPreference = 'Stop'

$configPath = Join-Path (Split-Path -Parent $PSScriptRoot) 'ortho-config.php'
if (-not (Test-Path -LiteralPath $configPath -PathType Leaf)) {
    throw 'The private ortho-config.php file was not found beside the repository.'
}

function ConvertTo-PlainText {
    param([Parameter(Mandatory = $true)][System.Security.SecureString]$Value)

    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($Value)
    try {
        return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    }
    finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    }
}

function ConvertTo-PhpString {
    param([Parameter(Mandatory = $true)][string]$Value)

    $escaped = $Value.Replace('\', '\\').Replace("'", "\'")
    return "'" + $escaped + "'"
}

$githubClientId = Read-Host 'GitHub production OAuth client ID'
$databasePasswordSecure = Read-Host 'InfinityFree hosting password (also used by MySQL)' -AsSecureString
$githubClientSecretSecure = Read-Host 'GitHub production OAuth client secret' -AsSecureString

if ([string]::IsNullOrWhiteSpace($githubClientId) -or
    $databasePasswordSecure.Length -eq 0 -or
    $githubClientSecretSecure.Length -eq 0) {
    throw 'All requested configuration values are required.'
}

$values = @{
    ORTHO_DB_PASSWORD = ConvertTo-PlainText $databasePasswordSecure
    OAUTH_GITHUB_CLIENT_ID = $githubClientId
    OAUTH_GITHUB_CLIENT_SECRET = ConvertTo-PlainText $githubClientSecretSecure
}

$lines = [System.Collections.Generic.List[string]]::new()
[System.IO.File]::ReadAllLines($configPath) | ForEach-Object { $lines.Add($_) }

foreach ($key in $values.Keys) {
    $pattern = "^\s*'$([regex]::Escape($key))'\s*=>"
    $matchedIndexes = @()
    for ($index = 0; $index -lt $lines.Count; $index++) {
        if ([regex]::IsMatch($lines[$index], $pattern)) {
            $matchedIndexes += $index
        }
    }
    if ($matchedIndexes.Count -ne 1) {
        throw "Could not safely locate exactly one $key entry in the private configuration."
    }

    $index = $matchedIndexes[0]
    $entryMatch = [regex]::Match(
        $lines[$index],
        "^(\s*'$([regex]::Escape($key))'\s*=>\s*).*(,\s*)$"
    )
    if (-not $entryMatch.Success) {
        throw "The $key configuration entry has an unsupported format."
    }

    $prefix = $entryMatch.Groups[1].Value
    $suffix = $entryMatch.Groups[2].Value
    $lines[$index] = $prefix + (ConvertTo-PhpString $values[$key]) + $suffix
}

$temporaryPath = $configPath + '.tmp'
try {
    [System.IO.File]::WriteAllText(
        $temporaryPath,
        (($lines -join [Environment]::NewLine) + [Environment]::NewLine),
        [System.Text.UTF8Encoding]::new($false)
    )
    Move-Item -LiteralPath $temporaryPath -Destination $configPath -Force
}
finally {
    if (Test-Path -LiteralPath $temporaryPath) {
        Remove-Item -LiteralPath $temporaryPath -Force
    }
}

$databasePasswordSecure.Dispose()
$githubClientSecretSecure.Dispose()
$values.Clear()
Write-Output 'Private production configuration updated; no credentials were displayed.'
