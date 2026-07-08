[CmdletBinding()]
param(
    [string]$ProjectPath = 'd:\xampp\htdocs\MIS\uhlms',
    [int]$LocalPort = 8000,
    [switch]$KeepMySql,
    [switch]$KeepCloudflaredService
)

$ErrorActionPreference = 'Stop'

function Get-State {
    param([string]$Path)

    if (-not (Test-Path $Path)) {
        return $null
    }

    try {
        return Get-Content $Path -Raw | ConvertFrom-Json
    } catch {
        return $null
    }
}

function Stop-MatchingProcess {
    param(
        [Nullable[int]]$ProcessId,
        [string]$Label,
        [string]$CommandPattern
    )

    if (-not $ProcessId) {
        return [pscustomobject]@{
            label = $Label
            pid = $null
            stopped = $false
            status = 'missing_pid'
        }
    }

    $process = Get-CimInstance Win32_Process -Filter "ProcessId = $ProcessId" -ErrorAction SilentlyContinue
    if (-not $process) {
        return [pscustomobject]@{
            label = $Label
            pid = [int]$ProcessId
            stopped = $false
            status = 'not_running'
        }
    }

    $commandLine = [string]$process.CommandLine
    if ($commandLine -notmatch $CommandPattern) {
        return [pscustomobject]@{
            label = $Label
            pid = [int]$ProcessId
            stopped = $false
            status = 'command_mismatch'
        }
    }

    Stop-Process -Id ([int]$ProcessId) -Force -ErrorAction Stop

    return [pscustomobject]@{
        label = $Label
        pid = [int]$ProcessId
        stopped = $true
        status = 'stopped'
    }
}

function Stop-LaravelServer {
    param(
        [int]$Port,
        [string]$ProjectRoot
    )

    $connections = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue |
        Select-Object -ExpandProperty OwningProcess -Unique

    $projectPattern = [regex]::Escape($ProjectRoot)
    $phpProcesses = Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" -ErrorAction SilentlyContinue |
        Where-Object {
            $_.CommandLine -match $projectPattern -and
            (
                $_.CommandLine -match 'artisan\s+serve' -or
                $_.CommandLine -match "\s-S\s+\S+:$Port\b"
            )
        } |
        Select-Object -ExpandProperty ProcessId -Unique

    $processIds = @($connections) + @($phpProcesses) | Where-Object { $_ } | Select-Object -Unique

    if (-not $processIds) {
        return @([pscustomobject]@{
            label = 'laravel'
            pid = $null
            stopped = $false
            status = 'not_listening'
        })
    }

    $results = @()
    foreach ($listeningProcessId in $processIds) {
        $results += Stop-MatchingProcess `
            -ProcessId ([int]$listeningProcessId) `
            -Label 'laravel' `
            -CommandPattern "$projectPattern.*(artisan\s+serve|\s-S\s+\S+:$Port\b)|(artisan\s+serve|\s-S\s+\S+:$Port\b).*$projectPattern"
    }

    return $results
}

function Stop-XamppMySql {
    param([string]$XamppRoot = 'D:\xampp')

    $mysqlProcesses = Get-CimInstance Win32_Process -Filter "Name = 'mysqld.exe'" -ErrorAction SilentlyContinue
    if (-not $mysqlProcesses) {
        return [pscustomobject]@{
            label = 'mysql'
            pid = $null
            stopped = $true
            status = 'stopped'
        }
    }

    $stoppedAny = $false
    foreach ($mysqlProcess in $mysqlProcesses) {
        Stop-Process -Id ([int]$mysqlProcess.ProcessId) -Force -ErrorAction SilentlyContinue
        $stoppedAny = $true
    }

    return [pscustomobject]@{
        label = 'mysql'
        pid = $null
        stopped = $stoppedAny
        status = if ($stoppedAny) { 'stopped_forced' } else { 'not_xampp_mysql' }
    }
}

function Stop-CloudflaredService {
    param([string]$ServiceName)

    $service = $null
    if (-not [string]::IsNullOrWhiteSpace($ServiceName)) {
        $service = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
    }

    if (-not $service) {
        $service = Get-Service *cloudflared* -ErrorAction SilentlyContinue | Select-Object -First 1
    }

    if (-not $service) {
        return [pscustomobject]@{
            label = 'cloudflared_service'
            pid = $null
            stopped = $false
            status = 'missing'
        }
    }

    if ($service.Status -eq 'Stopped') {
        return [pscustomobject]@{
            label = 'cloudflared_service'
            pid = $null
            stopped = $false
            status = 'already_stopped'
        }
    }

    try {
        $scOutput = & sc.exe stop $service.Name 2>&1
        if ($LASTEXITCODE -ne 0) {
            $status = if (($scOutput | Out-String) -match 'Access is denied') { 'permission_denied' } else { 'stop_failed' }
            return [pscustomobject]@{
                label = 'cloudflared_service'
                pid = $null
                stopped = $false
                status = $status
            }
        }

        Start-Sleep -Seconds 5
        $service = Get-Service -Name $service.Name -ErrorAction SilentlyContinue

        return [pscustomobject]@{
            label = 'cloudflared_service'
            pid = $null
            stopped = ($service.Status -eq 'Stopped')
            status = if ($service.Status -eq 'Stopped') { 'stopped' } else { 'stop_requested' }
        }
    } catch {
        return [pscustomobject]@{
            label = 'cloudflared_service'
            pid = $null
            stopped = $false
            status = 'stop_failed'
        }
    }
}

function Stop-CloudflaredProcesses {
    param([Nullable[int]]$ExcludeProcessId)

    $processes = Get-CimInstance Win32_Process -Filter "Name = 'cloudflared.exe'" -ErrorAction SilentlyContinue |
        Where-Object { -not $ExcludeProcessId -or $_.ProcessId -ne $ExcludeProcessId }

    if (-not $processes) {
        return [pscustomobject]@{
            label = 'cloudflared_processes'
            pid = $null
            stopped = $false
            status = 'not_running'
        }
    }

    foreach ($process in $processes) {
        Stop-Process -Id ([int]$process.ProcessId) -Force -ErrorAction SilentlyContinue
    }

    return [pscustomobject]@{
        label = 'cloudflared_processes'
        pid = $null
        stopped = $true
        status = 'stopped'
    }
}

$projectRoot = (Resolve-Path -LiteralPath $ProjectPath).Path
$logsDir = Join-Path $projectRoot 'storage\logs'
$statePath = Join-Path $logsDir 'cloudflare-tunnel-state.json'
$quickTunnelStatePath = Join-Path $logsDir 'quick-tunnel-state.json'
$state = Get-State -Path $statePath
$quickTunnelState = Get-State -Path $quickTunnelStatePath

$results = @()
$results += Stop-LaravelServer -Port $LocalPort -ProjectRoot $projectRoot

if ($state -and $state.queue_worker_pid) {
    $results += Stop-MatchingProcess `
        -ProcessId ([int]$state.queue_worker_pid) `
        -Label 'queue_worker' `
        -CommandPattern 'artisan\s+queue:work'
}

if ($state -and $state.cloudflared_http2_connector_pid) {
    $results += Stop-MatchingProcess `
        -ProcessId ([int]$state.cloudflared_http2_connector_pid) `
        -Label 'cloudflared_http2_connector' `
        -CommandPattern 'cloudflared.*--protocol\s+http2'
}

if ($quickTunnelState -and $quickTunnelState.pid) {
    $results += Stop-MatchingProcess `
        -ProcessId ([int]$quickTunnelState.pid) `
        -Label 'legacy_quick_tunnel' `
        -CommandPattern 'cloudflared.*\stunnel\s.*\s--url\s'
}

if (-not $KeepCloudflaredService) {
    $cloudflaredService = Get-CimInstance Win32_Service -Filter "Name = 'Cloudflared'" -ErrorAction SilentlyContinue
    $cloudflaredServiceResult = Stop-CloudflaredService -ServiceName $state.cloudflared_service_name
    $results += $cloudflaredServiceResult
    $excludeCloudflaredServicePid = if ($cloudflaredServiceResult.status -eq 'permission_denied' -and $cloudflaredService) {
        [int]$cloudflaredService.ProcessId
    } else {
        $null
    }
    $results += Stop-CloudflaredProcesses -ExcludeProcessId $excludeCloudflaredServicePid
}

if (-not $KeepMySql) {
    $results += Stop-XamppMySql
}

if (Test-Path $statePath) {
    try {
        $updatedState = if ($state) { $state } else { [pscustomobject]@{} }
        $updatedState | Add-Member -NotePropertyName stopped_at -NotePropertyValue ((Get-Date).ToString('o')) -Force
        $updatedState | Add-Member -NotePropertyName stop_results -NotePropertyValue $results -Force
        $updatedState | ConvertTo-Json -Depth 5 | Set-Content -Path $statePath -Encoding UTF8
    } catch {
    }
}

Write-Output ([pscustomobject]@{
    local_port = $LocalPort
    project_path = $projectRoot
    mysql_stopped = -not [bool]$KeepMySql
    cloudflared_service_stopped = -not [bool]$KeepCloudflaredService
    results = $results
} | ConvertTo-Json -Depth 5 -Compress)
