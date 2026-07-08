[CmdletBinding()]
param(
    [string]$ProjectPath = 'd:\xampp\htdocs\MIS\uhlms',
    [string]$LocalUrl = 'http://127.0.0.1:8000',
    [string]$PublicUrl = '',
    [int]$StartupTimeoutSeconds = 45
)

$ErrorActionPreference = 'Stop'

function Test-HttpOk {
    param([string]$Url)
    try {
        $response = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 10
        return ($response.StatusCode -ge 200 -and $response.StatusCode -lt 400)
    } catch {
        return $false
    }
}

function Get-UrlStatusCode {
    param([string]$Url)

    try {
        $status = & curl.exe -k -s -I --max-time 10 -o NUL -w '%{http_code}' $Url
        if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($status)) {
            return $null
        }

        $parsed = 0
        if ([int]::TryParse(($status.Trim()), [ref]$parsed)) {
            return $parsed
        }
    } catch {
    }

    return $null
}

function Wait-ForMySql {
    $mysqlAdmin = 'D:\xampp\mysql\bin\mysqladmin.exe'
    for ($i = 0; $i -lt 20; $i++) {
        $up = Test-NetConnection 127.0.0.1 -Port 3306 -WarningAction SilentlyContinue
        if ($up.TcpTestSucceeded) {
            return $true
        }

        if (Test-Path $mysqlAdmin) {
            try {
                & $mysqlAdmin ping --host=127.0.0.1 | Out-Null
                if ($LASTEXITCODE -eq 0) {
                    return $true
                }
            } catch {
            }
        }

        Start-Sleep -Seconds 2
    }

    return $false
}

function Get-LanIp {
    $ip = Get-NetIPAddress -AddressFamily IPv4 |
        Where-Object {
            $_.IPAddress -notmatch '^127\.' -and
            $_.IPAddress -notmatch '^169\.254\.' -and
            $_.PrefixOrigin -ne 'WellKnown'
        } |
        Sort-Object InterfaceMetric |
        Select-Object -First 1 -ExpandProperty IPAddress

    return $ip
}

function Ensure-FirewallRule {
    $ruleName = 'Codex Laravel Local 8000'
    try {
        if (-not (Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue)) {
            New-NetFirewallRule -DisplayName $ruleName -Direction Inbound -Action Allow -Protocol TCP -LocalPort 8000 -Profile Private | Out-Null
        }
        return $true
    } catch {
        return $false
    }
}

function Get-CloudflaredPath {
    $cmd = Get-Command cloudflared -ErrorAction SilentlyContinue
    if ($cmd) {
        return $cmd.Source
    }

    foreach ($path in @(
        'C:\Program Files (x86)\cloudflared\cloudflared.exe',
        'C:\Program Files\cloudflared\cloudflared.exe'
    )) {
        if (Test-Path $path) {
            return $path
        }
    }

    throw 'cloudflared.exe was not found on PATH or in common install locations.'
}

function Get-EnvValueFromFile {
    param(
        [string]$EnvPath,
        [string]$Key
    )

    if (-not (Test-Path $EnvPath)) {
        return $null
    }

    $match = Select-String -Path $EnvPath -Pattern "^(?:\uFEFF)?$([regex]::Escape($Key))=(.*)$" | Select-Object -First 1
    if (-not $match) {
        return $null
    }

    $value = $match.Matches[0].Groups[1].Value.Trim()
    if ($value.Length -ge 2 -and $value.StartsWith('"') -and $value.EndsWith('"')) {
        $value = $value.Substring(1, $value.Length - 2)
    }

    if ([string]::IsNullOrWhiteSpace($value) -or $value -eq 'null') {
        return $null
    }

    return $value
}

function Update-AppUrl {
    param(
        [string]$EnvPath,
        [string]$NewUrl,
        [string]$ProjectRoot
    )

    if (-not (Test-Path $EnvPath)) {
        return
    }

    $content = Get-Content $EnvPath -Raw
    $updated = [regex]::Replace($content, '(?m)^APP_URL=.*$', "APP_URL=$NewUrl")
    if ($updated -ne $content) {
        Set-Content -Path $EnvPath -Value $updated -Encoding UTF8
    }

    & php artisan config:clear | Out-Null
}

function Stop-LegacyQuickTunnel {
    param([string]$StatePath)

    if (-not (Test-Path $StatePath)) {
        return $false
    }

    try {
        $state = Get-Content $StatePath -Raw | ConvertFrom-Json
    } catch {
        return $false
    }

    if (-not $state.pid) {
        return $false
    }

    $legacyPid = [int]$state.pid
    $process = Get-CimInstance Win32_Process -Filter "ProcessId = $legacyPid" -ErrorAction SilentlyContinue
    if (-not $process) {
        return $false
    }

    $commandLine = [string]$process.CommandLine
    if ($commandLine -match 'cloudflared' -and $commandLine -match '\stunnel\s' -and $commandLine -match '\s--url\s') {
        Stop-Process -Id $legacyPid -Force -ErrorAction SilentlyContinue
        return $true
    }

    return $false
}

function Get-CloudflaredService {
    return Get-Service *cloudflared* -ErrorAction SilentlyContinue | Select-Object -First 1
}

function Get-CloudflaredServiceCommand {
    param([string]$ServiceName)

    if ([string]::IsNullOrWhiteSpace($ServiceName)) {
        return $null
    }

    $escapedName = $ServiceName.Replace("'", "''")
    $service = Get-CimInstance Win32_Service -Filter "Name = '$escapedName'" -ErrorAction SilentlyContinue
    if (-not $service) {
        return $null
    }

    return [string]$service.PathName
}

function Get-TunnelTokenFromCommand {
    param([string]$CommandLine)

    if ([string]::IsNullOrWhiteSpace($CommandLine)) {
        return $null
    }

    $match = [regex]::Match($CommandLine, '--token\s+(?:"([^"]+)"|(\S+))')
    if (-not $match.Success) {
        return $null
    }

    if ($match.Groups[1].Success) {
        return $match.Groups[1].Value
    }

    return $match.Groups[2].Value
}

function Ensure-Http2CloudflaredConnector {
    param(
        [string]$CloudflaredPath,
        [string]$ServiceName,
        [string]$LogsRoot
    )

    $runningConnector = Get-CimInstance Win32_Process -Filter "Name = 'cloudflared.exe'" -ErrorAction SilentlyContinue |
        Where-Object { $_.CommandLine -match '--protocol\s+http2' } |
        Select-Object -First 1

    if ($runningConnector) {
        return [pscustomobject]@{
            started = $false
            running = $true
            pid = [int]$runningConnector.ProcessId
            protocol = 'http2'
        }
    }

    $serviceCommand = Get-CloudflaredServiceCommand -ServiceName $ServiceName
    $service = Get-CloudflaredService
    if ($service -and $service.Status -eq 'Running' -and $serviceCommand -match '--protocol\s+http2') {
        $serviceProcess = Get-CimInstance Win32_Service -Filter "Name = '$($service.Name.Replace("'", "''"))'" -ErrorAction SilentlyContinue

        return [pscustomobject]@{
            started = $false
            running = $true
            pid = if ($serviceProcess) { [int]$serviceProcess.ProcessId } else { $null }
            protocol = 'http2-service'
        }
    }

    $token = Get-TunnelTokenFromCommand -CommandLine $serviceCommand
    if ([string]::IsNullOrWhiteSpace($token)) {
        return [pscustomobject]@{
            started = $false
            running = $false
            pid = $null
            protocol = $null
        }
    }

    $logFile = Join-Path $LogsRoot 'cloudflared-http2-connector.log'
    $process = Start-Process -FilePath $CloudflaredPath `
        -ArgumentList @('tunnel', '--protocol', 'http2', '--logfile', $logFile, '--transport-loglevel', 'info', 'run', '--token', $token) `
        -WindowStyle Hidden `
        -PassThru

    Start-Sleep -Seconds 8
    $runningConnector = Get-CimInstance Win32_Process -Filter "ProcessId = $($process.Id)" -ErrorAction SilentlyContinue

    return [pscustomobject]@{
        started = $true
        running = [bool]$runningConnector
        pid = if ($runningConnector) { [int]$runningConnector.ProcessId } else { [int]$process.Id }
        protocol = 'http2'
    }
}

function Test-QueueWorkerRunning {
    param([string]$ProjectRoot)

    $worker = Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" -ErrorAction SilentlyContinue |
        Where-Object {
            $_.CommandLine -match 'artisan\s+queue:work' -and
            $_.CommandLine -match '\bdatabase\b'
        } |
        Select-Object -First 1

    return $worker
}

function Ensure-QueueWorker {
    param(
        [string]$ProjectRoot,
        [string]$LogsRoot
    )

    $existingWorker = Test-QueueWorkerRunning -ProjectRoot $ProjectRoot
    if ($existingWorker) {
        return [pscustomobject]@{
            started = $false
            running = $true
            pid     = [int]$existingWorker.ProcessId
        }
    }

    $workerOut = Join-Path $LogsRoot 'codex-queue-work.out.log'
    $workerErr = Join-Path $LogsRoot 'codex-queue-work.err.log'
    $process = Start-Process -FilePath 'php' `
        -ArgumentList @('artisan', 'queue:work', 'database', '--queue=default', '--sleep=1', '--tries=3', '--timeout=120') `
        -WorkingDirectory $ProjectRoot `
        -RedirectStandardOutput $workerOut `
        -RedirectStandardError $workerErr `
        -WindowStyle Hidden `
        -PassThru

    Start-Sleep -Seconds 2
    $runningWorker = Test-QueueWorkerRunning -ProjectRoot $ProjectRoot

    return [pscustomobject]@{
        started = $true
        running = [bool]$runningWorker
        pid     = if ($runningWorker) { [int]$runningWorker.ProcessId } else { [int]$process.Id }
    }
}

$logsDir = Join-Path $ProjectPath 'storage\logs'
if (-not (Test-Path $logsDir)) {
    New-Item -ItemType Directory -Path $logsDir -Force | Out-Null
}

$envPath = Join-Path $ProjectPath '.env'
if ([string]::IsNullOrWhiteSpace($PublicUrl)) {
    $PublicUrl = Get-EnvValueFromFile -EnvPath $envPath -Key 'APP_URL'
}
if ([string]::IsNullOrWhiteSpace($PublicUrl)) {
    $PublicUrl = 'https://app.uhlms.uk'
}

$mysqlUp = Test-NetConnection 127.0.0.1 -Port 3306 -WarningAction SilentlyContinue
if (-not $mysqlUp.TcpTestSucceeded) {
    Start-Process -FilePath 'D:\xampp\mysql_start.bat' -WorkingDirectory 'D:\xampp'
    if (-not (Wait-ForMySql)) {
        throw 'MySQL did not become reachable on 127.0.0.1:3306.'
    }
}

$lanIp = Get-LanIp
$firewallReady = Ensure-FirewallRule

$ngrok = Get-Process ngrok -ErrorAction SilentlyContinue
if ($ngrok) {
    $ngrok | Stop-Process -Force -ErrorAction SilentlyContinue
}

$legacyQuickTunnelStopped = Stop-LegacyQuickTunnel -StatePath (Join-Path $logsDir 'quick-tunnel-state.json')
$queueConnection = Get-EnvValueFromFile -EnvPath $envPath -Key 'QUEUE_CONNECTION'
$queueWorkerStarted = $false
$queueWorkerRunning = $false
$queueWorkerPid = $null
$laravelStarted = $false
if (-not (Test-HttpOk -Url $LocalUrl)) {
    $listener = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue |
        Select-Object -First 1
    if ($listener) {
        throw "Port 8000 is already occupied by process $($listener.OwningProcess)."
    }

    $laravelOut = Join-Path $logsDir 'codex-artisan-serve.out.log'
    $laravelErr = Join-Path $logsDir 'codex-artisan-serve.err.log'
    Start-Process -FilePath 'php' `
        -ArgumentList @('artisan', 'serve', '--host=0.0.0.0', '--port=8000') `
        -WorkingDirectory $ProjectPath `
        -RedirectStandardOutput $laravelOut `
        -RedirectStandardError $laravelErr `
        -WindowStyle Hidden | Out-Null

    for ($i = 0; $i -lt 20; $i++) {
        Start-Sleep -Seconds 2
        if (Test-HttpOk -Url $LocalUrl) {
            $laravelStarted = $true
            break
        }
    }

    if (-not (Test-HttpOk -Url $LocalUrl)) {
        throw "Laravel did not become reachable at $LocalUrl."
    }
}

if ($queueConnection -eq 'database') {
    $queueWorker = Ensure-QueueWorker -ProjectRoot $ProjectPath -LogsRoot $logsDir
    $queueWorkerStarted = [bool]$queueWorker.started
    $queueWorkerRunning = [bool]$queueWorker.running
    $queueWorkerPid = $queueWorker.pid
}

$cloudflaredPath = Get-CloudflaredPath
$service = Get-CloudflaredService
$serviceName = $null
$serviceStatus = 'missing'
$http2ConnectorStarted = $false
$http2ConnectorRunning = $false
$http2ConnectorPid = $null
$http2ConnectorProtocol = $null

if ($service) {
    $serviceName = $service.Name
    if ($service.Status -ne 'Running') {
        try {
            Start-Service -Name $service.Name
            $service.WaitForStatus('Running', [TimeSpan]::FromSeconds(15))
            $service = Get-CloudflaredService
        } catch {
            $serviceStatus = 'stopped_permission_denied'
        }
    }

    if ($serviceStatus -ne 'stopped_permission_denied') {
        $serviceStatus = [string]$service.Status
    }
}

$http2Connector = Ensure-Http2CloudflaredConnector -CloudflaredPath $cloudflaredPath -ServiceName $serviceName -LogsRoot $logsDir
$http2ConnectorStarted = [bool]$http2Connector.started
$http2ConnectorRunning = [bool]$http2Connector.running
$http2ConnectorPid = $http2Connector.pid
$http2ConnectorProtocol = $http2Connector.protocol

Update-AppUrl -EnvPath $envPath -NewUrl $PublicUrl -ProjectRoot $ProjectPath

$publicStatusCode = $null
$startedAt = Get-Date
while (((Get-Date) - $startedAt).TotalSeconds -lt $StartupTimeoutSeconds) {
    $publicStatusCode = Get-UrlStatusCode -Url $PublicUrl
    if ($publicStatusCode) {
        break
    }

    Start-Sleep -Seconds 2
}

$publicReachable = $false
if ($publicStatusCode -and $publicStatusCode -ge 200 -and $publicStatusCode -lt 400) {
    $publicReachable = $true
}

$statePath = Join-Path $logsDir 'cloudflare-tunnel-state.json'
$state = [pscustomobject]@{
    public_url                  = $PublicUrl
    local_url                   = $LocalUrl
    updated_at                  = (Get-Date).ToString('o')
    cloudflared_path            = $cloudflaredPath
    cloudflared_service_name    = $serviceName
    cloudflared_service_status  = $serviceStatus
    cloudflared_connector_protocol = $http2ConnectorProtocol
    cloudflared_http2_connector_running = $http2ConnectorRunning
    cloudflared_http2_connector_pid = $http2ConnectorPid
    public_status_code          = $publicStatusCode
    public_reachable            = $publicReachable
    legacy_quick_tunnel_stopped = $legacyQuickTunnelStopped
    queue_connection            = $queueConnection
    queue_worker_running        = $queueWorkerRunning
    queue_worker_pid            = $queueWorkerPid
}
$state | ConvertTo-Json | Set-Content -Path $statePath -Encoding UTF8

$lanUrl = $null
if ($lanIp) {
    $lanUrl = "http://$lanIp`:8000"
}

Write-Output ([pscustomobject]@{
    local_url                   = $LocalUrl
    lan_url                     = $lanUrl
    public_url                  = $PublicUrl
    public_status_code          = $publicStatusCode
    public_reachable            = $publicReachable
    cloudflared_path            = $cloudflaredPath
    cloudflared_service_name    = $serviceName
    cloudflared_service_status  = $serviceStatus
    cloudflared_connector_protocol = $http2ConnectorProtocol
    cloudflared_http2_connector_started = $http2ConnectorStarted
    cloudflared_http2_connector_running = $http2ConnectorRunning
    cloudflared_http2_connector_pid = $http2ConnectorPid
    legacy_quick_tunnel_stopped = $legacyQuickTunnelStopped
    queue_connection            = $queueConnection
    queue_worker_started        = $queueWorkerStarted
    queue_worker_running        = $queueWorkerRunning
    queue_worker_pid            = $queueWorkerPid
    laravel_started             = $laravelStarted
    firewall_ready              = $firewallReady
} | ConvertTo-Json -Compress)
