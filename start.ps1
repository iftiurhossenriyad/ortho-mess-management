$ErrorActionPreference = 'Stop'

$projectRoot = $PSScriptRoot
$php = 'D:\XAMPP\php\php.exe'
$mysqlAdmin = 'D:\XAMPP\mysql\bin\mysqladmin.exe'
$mysqlServer = 'D:\XAMPP\mysql\bin\mysqld.exe'
$mysqlConfig = 'D:\XAMPP\mysql\bin\my.ini'

if (-not (Test-Path $php)) {
    throw "XAMPP PHP was not found at $php."
}

if (-not (Test-Path $mysqlAdmin) -or -not (Test-Path $mysqlServer)) {
    throw 'XAMPP MySQL tools were not found. Check the XAMPP installation path.'
}

$mysqlReady = (& $mysqlAdmin --host=127.0.0.1 --port=3306 --user=root ping 2>$null) -match 'alive'
if (-not $mysqlReady) {
    Start-Process -FilePath $mysqlServer -ArgumentList "--defaults-file=$mysqlConfig", '--standalone' -WorkingDirectory (Split-Path $mysqlServer)
    Write-Host 'MySQL start requested. If the first page load fails, wait a moment and refresh.'
}

$server = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue
if ($server) {
    Write-Host 'Ortho server is already running at http://localhost:8000'
    exit 0
}

Set-Location $projectRoot
Write-Host 'Ortho server: http://localhost:8000'
Write-Host 'Press Ctrl+C to stop the PHP server.'
& $php -S localhost:8000 -t $projectRoot
