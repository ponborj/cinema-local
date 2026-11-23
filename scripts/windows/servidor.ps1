param([ValidateSet('Start', 'Stop')][string]$Action = 'Start', [switch]$NoBrowser)
$ErrorActionPreference = 'Stop'
$projectPath = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$runtimePath = Join-Path $projectPath 'storage\framework\cinema-server'
$statePath = Join-Path $runtimePath 'processes.json'
$configPath = Join-Path $runtimePath 'httpd.conf'
$artisanPath = Join-Path $projectPath 'artisan'
$phpPath = (Get-Command php.exe).Source
$phpDirectory = Split-Path $phpPath
$apacheDirectory = Get-ChildItem -LiteralPath 'C:\laragon\bin\apache' -Directory | Sort-Object Name -Descending | Select-Object -First 1 -ExpandProperty FullName
$apachePath = Join-Path $apacheDirectory 'bin\httpd.exe'
$portMatch = [regex]::Match((Get-Content -LiteralPath (Join-Path $projectPath '.env') -Raw), '(?m)^MEDIA_PORT=(\d+)')
$port = if ($portMatch.Success) { [int]$portMatch.Groups[1].Value } else { 80 }
if ($port -lt 1 -or $port -gt 65535) { throw 'MEDIA_PORT precisa estar entre 1 e 65535.' }
$serverUrl = if ($port -eq 80) { 'http://localhost' } else { "http://localhost:$port" }

function Get-OwnedProcess($entry, $expectedPath, $expectedArgument) {
    if (!$entry) { return $null }
    try {
        $owned = Get-Process -Id $entry.id -ErrorAction Stop
        $info = Get-CimInstance Win32_Process -Filter "ProcessId = $($entry.id)"
        if ($owned.Path -ne $expectedPath -or $owned.StartTime.ToUniversalTime().Ticks.ToString() -ne $entry.startedAtTicks -or !$info.CommandLine.Contains($expectedArgument)) { return $null }
        return $owned
    } catch { return $null }
}

$state = if (Test-Path -LiteralPath $statePath) { Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json } else { $null }
$server = Get-OwnedProcess $state.server $apachePath $configPath
$worker = Get-OwnedProcess $state.worker $phpPath $artisanPath

if ($Action -eq 'Stop') {
    if ($worker) {
        & $phpPath $artisanPath cinema:stop-worker --no-interaction
        $worker.WaitForExit(6000) | Out-Null
        if (!$worker.HasExited) { throw 'O worker ainda esta encerrando a conversao. Aguarde e tente parar novamente.' }
    }
    if ($server) {
        # This Apache is a console instance, not an installed Windows service.
        $children = @(Get-CimInstance Win32_Process -Filter "ParentProcessId = $($server.Id)" | Where-Object { $_.ExecutablePath -eq $apachePath })
        Stop-Process -Id $server.Id
        foreach ($child in $children) {
            $currentChild = Get-CimInstance Win32_Process -Filter "ProcessId = $($child.ProcessId)"
            if ($currentChild -and $currentChild.ParentProcessId -eq $server.Id -and $currentChild.ExecutablePath -eq $apachePath) { Stop-Process -Id $child.ProcessId -ErrorAction SilentlyContinue }
        }
        $server.WaitForExit(6000) | Out-Null
        if (!$server.HasExited) { throw 'O Apache ainda esta encerrando. Aguarde e tente novamente.' }
    }
    if (Test-Path -LiteralPath $statePath) { Remove-Item -LiteralPath $statePath }
    Write-Host 'Cinema Local Laravel encerrado.'
    exit 0
}

if (!(Test-Path -LiteralPath (Join-Path $projectPath 'public\build\manifest.json'))) { throw 'Compile os assets antes: npm install e npm run build.' }
if (!(Test-Path -LiteralPath (Join-Path $phpDirectory 'php8apache2_4.dll'))) { throw 'Este iniciador requer PHP Thread Safe com php8apache2_4.dll, disponivel no Laragon.' }
New-Item -ItemType Directory -Force -Path $runtimePath | Out-Null
$runtimeUnix = $runtimePath.Replace('\','/')
$projectUnix = $projectPath.Replace('\','/')
$apacheUnix = $apacheDirectory.Replace('\','/')
$phpUnix = $phpDirectory.Replace('\','/')
if (!$server) {
    $probe = New-Object System.Net.Sockets.TcpClient
    try { $probe.Connect('127.0.0.1', $port); $occupied = $true } catch { $occupied = $false } finally { $probe.Dispose() }
    if ($occupied) { throw "A porta $port ja esta em uso. Altere MEDIA_PORT no .env ou pare o servidor existente." }
    $apacheConfig = @"
ServerRoot "$apacheUnix"
Listen 0.0.0.0:$port
ServerName localhost:$port
PidFile "$runtimeUnix/httpd.pid"
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule authz_host_module modules/mod_authz_host.so
LoadModule dir_module modules/mod_dir.so
LoadModule mime_module modules/mod_mime.so
LoadModule log_config_module modules/mod_log_config.so
LoadFile "$phpUnix/php8ts.dll"
LoadModule php_module "$phpUnix/php8apache2_4.dll"
PHPIniDir "$phpUnix"
ServerTokens Prod
ServerSignature Off
ThreadsPerChild 64
Timeout 60
KeepAlive On
KeepAliveTimeout 3
EnableSendfile Off
ErrorLog "$runtimeUnix/apache-error.log"
LogLevel warn
TypesConfig "$apacheUnix/conf/mime.types"
DocumentRoot "$projectUnix/public"
<Directory />
    AllowOverride None
    Require all denied
</Directory>
<Directory "$projectUnix/public">
    Options -Indexes +FollowSymLinks
    AllowOverride None
    Require ip 127.0.0.0/8 10.0.0.0/8 172.16.0.0/12 192.168.0.0/16 169.254.0.0/16 ::1
    DirectoryIndex index.php
    FallbackResource /index.php
    php_admin_value max_execution_time 0
    php_admin_value memory_limit 256M
</Directory>
<FilesMatch "\.php$">
    SetHandler application/x-httpd-php
</FilesMatch>
"@
    [IO.File]::WriteAllText($configPath, $apacheConfig, [Text.UTF8Encoding]::new($false))
    & $apachePath -t -f $configPath
    if ($LASTEXITCODE -ne 0) { throw 'A configuracao do Apache precisa de revisao.' }
    & $phpPath $artisanPath migrate --force --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Nao foi possivel preparar o banco SQLite.' }
    $server = Start-Process -FilePath $apachePath -ArgumentList ('-f "' + $configPath + '"') -WorkingDirectory $projectPath -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtimePath 'server.log') -RedirectStandardError (Join-Path $runtimePath 'server-error.log') -PassThru
}
if (!$worker) {
    $workerArguments = '"' + $artisanPath + '" queue:work --sleep=1 --tries=1 --timeout=21630 --memory=256'
    $worker = Start-Process -FilePath $phpPath -ArgumentList $workerArguments -WorkingDirectory $projectPath -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtimePath 'worker.log') -RedirectStandardError (Join-Path $runtimePath 'worker-error.log') -PassThru
}
@{ server = @{ id = $server.Id; startedAtTicks = $server.StartTime.ToUniversalTime().Ticks.ToString() }; worker = @{ id = $worker.Id; startedAtTicks = $worker.StartTime.ToUniversalTime().Ticks.ToString() } } | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath $statePath -Encoding UTF8
$ready = $false
$lastReadinessError = ''
for ($attempt = 0; $attempt -lt 10; $attempt++) {
    try { $null = Invoke-WebRequest -UseBasicParsing -Uri "http://127.0.0.1:$port" -TimeoutSec 3; $ready = $true; break } catch { $lastReadinessError = $_.Exception.Message; Start-Sleep -Milliseconds 300 }
}
if (!$ready) { throw "Servidor indisponivel: $lastReadinessError. Consulte storage/framework/cinema-server e storage/logs." }
Write-Host "Cinema Local Laravel: $serverUrl"
Write-Host 'Servidor Apache e fila de preparacao ativos. Para encerrar, execute parar.cmd.'
if (!$NoBrowser) { Start-Process $serverUrl -WindowStyle Hidden }
