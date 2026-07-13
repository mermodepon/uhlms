[CmdletBinding()]
param(
    [string]$ProjectPath = 'd:\xampp\htdocs\MIS\uhlms',
    [string]$LocalUrl = 'http://127.0.0.1:8000',
    [string]$PublicUrl = '',
    [int]$StartupTimeoutSeconds = 45,
    [switch]$EnforceCsp
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

function Get-UrlRedirectProbe {
    param([string]$Url)

    try {
        $result = & curl.exe -k -sS --max-time 10 -o NUL -w '%{http_code}|%{redirect_url}' $Url
        if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($result)) {
            return $null
        }

        $parts = $result.Trim() -split '\|', 2
        return [pscustomobject]@{
            status_code  = [int]$parts[0]
            redirect_url = if ($parts.Count -gt 1) { $parts[1] } else { '' }
        }
    } catch {
        return $null
    }
}

function Get-ResponseHeader {
    param(
        [string]$Url,
        [string]$Name
    )

    try {
        $headers = & curl.exe -k -sS -I --max-time 10 $Url
        if ($LASTEXITCODE -ne 0) {
            return $null
        }

        foreach ($line in $headers) {
            if ($line -match "^$([regex]::Escape($Name)):\s*(.+)$") {
                return $Matches[1].Trim()
            }
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

function Set-EnvValue {
    param(
        [string]$EnvPath,
        [string]$Key,
        [string]$Value
    )

    if (-not (Test-Path $EnvPath)) {
        throw "Environment file not found: $EnvPath"
    }

    $content = Get-Content $EnvPath -Raw
    $pattern = "(?m)^(?:\uFEFF)?$([regex]::Escape($Key))=.*$"
    $line = "$Key=$Value"
    $updated = if ([regex]::IsMatch($content, $pattern)) {
        [regex]::Replace($content, $pattern, $line)
    } else {
        $content.TrimEnd() + [Environment]::NewLine + $line + [Environment]::NewLine
    }

    if ($updated -ne $content) {
        Set-Content -Path $EnvPath -Value $updated -Encoding UTF8
    }
}

function Update-RuntimeEnvironment {
    param(
        [string]$EnvPath,
        [string]$AppUrl,
        [string]$TrustedHosts,
        [string]$ProjectRoot,
        [ValidateSet('report-only', 'enforce')]
        [string]$CspMode
    )

    Set-EnvValue -EnvPath $EnvPath -Key 'APP_URL' -Value $AppUrl
    Set-EnvValue -EnvPath $EnvPath -Key 'TRUSTED_HOSTS' -Value $TrustedHosts
    Set-EnvValue -EnvPath $EnvPath -Key 'PUBLIC_HTTPS_ENFORCED' -Value 'true'
    Set-EnvValue -EnvPath $EnvPath -Key 'CONTENT_SECURITY_POLICY_MODE' -Value $CspMode
    Set-EnvValue -EnvPath $EnvPath -Key 'LOG_CHANNEL' -Value 'stack'
    Set-EnvValue -EnvPath $EnvPath -Key 'LOG_STACK' -Value 'daily'
    Set-EnvValue -EnvPath $EnvPath -Key 'LOG_LEVEL' -Value 'info'
    Set-EnvValue -EnvPath $EnvPath -Key 'LOG_DAILY_DAYS' -Value '14'

    Push-Location $ProjectRoot
    try {
        & php artisan config:clear | Out-Null
        & php artisan view:clear | Out-Null
    } finally {
        Pop-Location
    }

    $savedAppUrl = Get-EnvValueFromFile -EnvPath $EnvPath -Key 'APP_URL'
    $savedTrustedHosts = Get-EnvValueFromFile -EnvPath $EnvPath -Key 'TRUSTED_HOSTS'
    $savedHttpsEnforced = Get-EnvValueFromFile -EnvPath $EnvPath -Key 'PUBLIC_HTTPS_ENFORCED'
    $savedCspMode = Get-EnvValueFromFile -EnvPath $EnvPath -Key 'CONTENT_SECURITY_POLICY_MODE'
    $savedLogChannel = Get-EnvValueFromFile -EnvPath $EnvPath -Key 'LOG_CHANNEL'
    $savedLogStack = Get-EnvValueFromFile -EnvPath $EnvPath -Key 'LOG_STACK'
    $savedLogLevel = Get-EnvValueFromFile -EnvPath $EnvPath -Key 'LOG_LEVEL'
    $savedLogDailyDays = Get-EnvValueFromFile -EnvPath $EnvPath -Key 'LOG_DAILY_DAYS'
    if (
        $savedAppUrl -ne $AppUrl `
        -or $savedTrustedHosts -ne $TrustedHosts `
        -or $savedHttpsEnforced -ne 'true' `
        -or $savedCspMode -ne $CspMode `
        -or $savedLogChannel -ne 'stack' `
        -or $savedLogStack -ne 'daily' `
        -or $savedLogLevel -ne 'info' `
        -or $savedLogDailyDays -ne '14'
    ) {
        throw 'Failed to verify canonical URL, trusted hosts, HTTPS/CSP, and safe logging settings after updating the environment.'
    }
}

function Invoke-CspEnforcementChecks {
    param([string]$ProjectRoot)

    Push-Location $ProjectRoot
    try {
        & composer audit --locked --abandoned=report
        if ($LASTEXITCODE -ne 0) {
            throw 'The locked Composer dependency security audit failed; CSP enforcement was not enabled.'
        }

        & npm.cmd ci --include=dev
        if ($LASTEXITCODE -ne 0) {
            throw 'The clean frontend dependency install failed; CSP enforcement was not enabled.'
        }

        & npm.cmd run audit:security
        if ($LASTEXITCODE -ne 0) {
            throw 'The frontend dependency security audit failed; CSP enforcement was not enabled.'
        }

        & php artisan test
        if ($LASTEXITCODE -ne 0) {
            throw 'Laravel tests failed; CSP enforcement was not enabled.'
        }

        & npm.cmd run build
        if ($LASTEXITCODE -ne 0) {
            throw 'The frontend production build failed; CSP enforcement was not enabled.'
        }

        & npm.cmd run test:browser-security
        if ($LASTEXITCODE -ne 0) {
            throw 'The Chromium security smoke suite failed; CSP enforcement was not enabled.'
        }
    } finally {
        Pop-Location
    }
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
        -ArgumentList @('-d', 'expose_php=Off', 'artisan', 'queue:work', 'database', '--queue=default', '--sleep=1', '--tries=3', '--timeout=120') `
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
    $PublicUrl = 'https://app.uhlms.uk'
}

$mysqlUp = Test-NetConnection 127.0.0.1 -Port 3306 -WarningAction SilentlyContinue
if (-not $mysqlUp.TcpTestSucceeded) {
    Start-Process -FilePath 'D:\xampp\mysql_start.bat' -WorkingDirectory 'D:\xampp'
    if (-not (Wait-ForMySql)) {
        throw 'MySQL did not become reachable on 127.0.0.1:3306.'
    }
}

$targetCspMode = if ($EnforceCsp) { 'enforce' } else { 'report-only' }
if ($EnforceCsp) {
    Invoke-CspEnforcementChecks -ProjectRoot $ProjectPath
}

$lanIp = Get-LanIp
$publicUri = [Uri]$PublicUrl
if (-not $publicUri.IsAbsoluteUri -or $publicUri.Scheme -ne 'https' -or [string]::IsNullOrWhiteSpace($publicUri.Host)) {
    throw 'Cloudflare PublicUrl must be an absolute HTTPS URL.'
}
$trustedHostValues = @($publicUri.Host, 'localhost', '127.0.0.1', '::1')
if ($lanIp) {
    $trustedHostValues += $lanIp
}
$trustedHosts = (($trustedHostValues | Select-Object -Unique) -join ',')
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
        -ArgumentList @('-d', 'expose_php=Off', 'artisan', 'serve', '--host=0.0.0.0', '--port=8000') `
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

Update-RuntimeEnvironment -EnvPath $envPath -AppUrl $PublicUrl -TrustedHosts $trustedHosts -ProjectRoot $ProjectPath -CspMode $targetCspMode

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

if (-not $publicReachable) {
    throw "The Cloudflare hostname did not become reachable at $PublicUrl."
}

$publicHttpUrl = 'http://' + $publicUri.Host + $publicUri.PathAndQuery
$publicHttpProbe = Get-UrlRedirectProbe -Url $publicHttpUrl
$publicHttpRedirectOk = $publicHttpProbe `
    -and $publicHttpProbe.status_code -in @(301, 302, 307, 308) `
    -and $publicHttpProbe.redirect_url.StartsWith("https://$($publicUri.Host)", [System.StringComparison]::OrdinalIgnoreCase)
if (-not $publicHttpRedirectOk) {
    throw "Public HTTP did not redirect to HTTPS: $publicHttpUrl"
}

$hstsHeader = Get-ResponseHeader -Url $PublicUrl -Name 'Strict-Transport-Security'
if ($hstsHeader -ne 'max-age=2592000') {
    throw "The public HTTPS response has an unexpected Strict-Transport-Security value: $hstsHeader"
}

$cspReportOnlyHeader = Get-ResponseHeader -Url $PublicUrl -Name 'Content-Security-Policy-Report-Only'
$cspEnforceHeader = Get-ResponseHeader -Url $PublicUrl -Name 'Content-Security-Policy'
if ($targetCspMode -eq 'enforce') {
    if ([string]::IsNullOrWhiteSpace($cspEnforceHeader) -or $cspEnforceHeader -notmatch "frame-ancestors 'none'") {
        throw "The public HTTPS response is missing the enforcing CSP or frame-ancestors 'none': $PublicUrl"
    }
    if (-not [string]::IsNullOrWhiteSpace($cspReportOnlyHeader)) {
        throw "The public HTTPS response still contains a report-only CSP after enforcement: $PublicUrl"
    }
} else {
    if ([string]::IsNullOrWhiteSpace($cspReportOnlyHeader) -or $cspReportOnlyHeader -notmatch "frame-ancestors 'none'") {
        throw "The public HTTPS response is missing the report-only CSP or frame-ancestors 'none': $PublicUrl"
    }
    if (-not [string]::IsNullOrWhiteSpace($cspEnforceHeader)) {
        throw "The public HTTPS response unexpectedly contains an enforcing CSP: $PublicUrl"
    }
}

$expectedHeaders = [ordered]@{
    'X-Frame-Options' = 'DENY'
    'X-Content-Type-Options' = 'nosniff'
    'Referrer-Policy' = 'strict-origin-when-cross-origin'
    'X-XSS-Protection' = '0'
    'Permissions-Policy' = 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), serial=(), hid=(), display-capture=()'
}
foreach ($expectedHeader in $expectedHeaders.GetEnumerator()) {
    $actualValue = Get-ResponseHeader -Url $PublicUrl -Name $expectedHeader.Key
    if ($actualValue -ne $expectedHeader.Value) {
        throw "The public HTTPS response has an unexpected $($expectedHeader.Key) value: $actualValue"
    }
}

$poweredByHeader = Get-ResponseHeader -Url $PublicUrl -Name 'X-Powered-By'
if (-not [string]::IsNullOrWhiteSpace($poweredByHeader)) {
    throw "The public HTTPS response discloses X-Powered-By: $poweredByHeader"
}

$statePath = Join-Path $logsDir 'cloudflare-tunnel-state.json'
$state = [pscustomobject]@{
    public_url                  = $PublicUrl
    trusted_hosts               = $trustedHosts
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
    public_http_status_code     = $publicHttpProbe.status_code
    public_http_redirect_url    = $publicHttpProbe.redirect_url
    public_http_redirect_ok     = $publicHttpRedirectOk
    hsts_header                 = $hstsHeader
    csp_mode                    = $targetCspMode
    csp_report_only_present     = -not [string]::IsNullOrWhiteSpace($cspReportOnlyHeader)
    csp_enforce_present         = -not [string]::IsNullOrWhiteSpace($cspEnforceHeader)
    x_powered_by_present        = $false
    public_https_enforced       = $true
    legacy_quick_tunnel_stopped = $legacyQuickTunnelStopped
    queue_connection            = $queueConnection
    queue_worker_running        = $queueWorkerRunning
    queue_worker_pid            = $queueWorkerPid
    log_channel                 = 'stack'
    log_stack                   = 'daily'
    log_level                   = 'info'
    log_daily_days              = 14
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
    trusted_hosts               = $trustedHosts
    public_status_code          = $publicStatusCode
    public_reachable            = $publicReachable
    public_http_status_code     = $publicHttpProbe.status_code
    public_http_redirect_url    = $publicHttpProbe.redirect_url
    public_http_redirect_ok     = $publicHttpRedirectOk
    hsts_header                 = $hstsHeader
    csp_mode                    = $targetCspMode
    csp_report_only_present     = -not [string]::IsNullOrWhiteSpace($cspReportOnlyHeader)
    csp_enforce_present         = -not [string]::IsNullOrWhiteSpace($cspEnforceHeader)
    x_powered_by_present        = $false
    public_https_enforced       = $true
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
    log_channel                 = 'stack'
    log_stack                   = 'daily'
    log_level                   = 'info'
    log_daily_days              = 14
    laravel_started             = $laravelStarted
    firewall_ready              = $firewallReady
} | ConvertTo-Json -Compress)
