$ErrorActionPreference = 'Stop'

$projectRoot = $PSScriptRoot
$php = 'D:\XAMPP\php\php.exe'
$mysql = 'D:\XAMPP\mysql\bin\mysql.exe'
$mysqlAdmin = 'D:\XAMPP\mysql\bin\mysqladmin.exe'
$mysqlInstall = 'D:\XAMPP\mysql\bin\mysql_install_db.exe'
$mysqlServer = 'D:\XAMPP\mysql\bin\mysqld.exe'
$mysqlRoot = 'D:\XAMPP\mysql'
$mysqlData = Join-Path $env:LOCALAPPDATA 'OrthoMessManagement\mysql-data'
$mysqlConfig = Join-Path $mysqlData 'my.ini'
$mysqlLog = Join-Path $mysqlData 'ortho-mariadb.log'
$mysqlPort = 3307
$databaseName = 'ortho_mess_management'

function Test-OrthoDatabaseReady {
    $previousErrorActionPreference = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $result = & $mysqlAdmin --host=127.0.0.1 --port=$mysqlPort --user=root ping 2>$null
        return $LASTEXITCODE -eq 0 -and (($result -join ' ') -match 'alive')
    } finally {
        $ErrorActionPreference = $previousErrorActionPreference
    }
}

if (-not (Test-Path $php)) {
    throw "XAMPP PHP was not found at $php."
}

if (-not (Test-Path $mysql) -or -not (Test-Path $mysqlAdmin) -or -not (Test-Path $mysqlInstall) -or -not (Test-Path $mysqlServer)) {
    throw 'XAMPP MySQL tools were not found. Check the XAMPP installation path.'
}

if (-not (Test-Path (Join-Path $mysqlData 'mysql\global_priv.frm'))) {
    New-Item -ItemType Directory -Path $mysqlData -Force | Out-Null
    & $mysqlInstall "--datadir=$mysqlData" "--port=$mysqlPort"
    if ($LASTEXITCODE -ne 0) {
        throw "The isolated Ortho database could not be initialized in $mysqlData."
    }
}

$mysqlReady = Test-OrthoDatabaseReady
if (-not $mysqlReady) {
    Start-Process -FilePath $mysqlServer -ArgumentList @("--defaults-file=$mysqlConfig", "--basedir=$mysqlRoot", '--bind-address=127.0.0.1', "--log-error=$mysqlLog") -WorkingDirectory (Join-Path $mysqlRoot 'bin') -WindowStyle Hidden
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        Start-Sleep -Seconds 1
        $mysqlReady = Test-OrthoDatabaseReady
        if ($mysqlReady) {
            break
        }
    }
}

if (-not $mysqlReady) {
    $logDetails = if (Test-Path $mysqlLog) { (Get-Content $mysqlLog -Tail 20) -join "`n" } else { 'No MariaDB log was created.' }
    throw "The isolated Ortho database failed to start. Check $mysqlLog.`n$logDetails"
}

$actualData = & $mysql --host=127.0.0.1 --port=$mysqlPort --user=root --batch --skip-column-names --execute='SELECT @@datadir;'
if ($LASTEXITCODE -ne 0) {
    throw 'The Ortho database server identity could not be verified.'
}
$actualDataPath = [IO.Path]::GetFullPath(($actualData -join '').Replace('\\', '\').Trim()).TrimEnd([char[]]@('\', '/'))
$expectedDataPath = [IO.Path]::GetFullPath($mysqlData).TrimEnd([char[]]@('\', '/'))
if (-not [string]::Equals($actualDataPath, $expectedDataPath, [StringComparison]::OrdinalIgnoreCase)) {
    throw "Port $mysqlPort is serving a different database directory. The existing server was left untouched."
}

Get-Content -Raw (Join-Path $projectRoot 'database.sql') | & $mysql --host=127.0.0.1 --port=$mysqlPort --user=root
if ($LASTEXITCODE -ne 0) {
    throw 'The Ortho database schema could not be created. Check MariaDB permissions and the database log.'
}

$env:ORTHO_DB_HOST = '127.0.0.1'
$env:ORTHO_DB_PORT = [string] $mysqlPort
$env:ORTHO_DB_USER = 'root'
$env:ORTHO_DB_PASSWORD = ''
$env:ORTHO_DB_NAME = $databaseName

$server = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue
if ($server) {
    Write-Host 'Ortho server is already running at http://localhost:8000'
    exit 0
}

Set-Location $projectRoot
Write-Host 'Ortho server: http://localhost:8000'
Write-Host 'Press Ctrl+C to stop the PHP server.'
& $php -S localhost:8000 -t $projectRoot
