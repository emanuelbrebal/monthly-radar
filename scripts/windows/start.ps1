# Radar Mensal - sobe o app local com um clique (Windows, PowerShell 5.1+).
#
#   Atalho criado por install-shortcut.ps1 chama este script sem janela.
#   Ordem: Postgres -> assets -> backup -> server + fila -> navegador.
#   Se o app ja estiver no ar, so abre o navegador.
#
# Arquivo em ASCII de proposito: o PowerShell 5.1 le .ps1 sem BOM como ANSI.

$ErrorActionPreference = 'Stop'

$Root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$BackupDir = Join-Path $env:USERPROFILE 'OneDrive\Backups\monthly-radar'
$BackupKeep = 14
$BackupMinAgeHours = 12
$BootTimeoutSeconds = 30
$RunFile = Join-Path $Root 'storage\app\local-run.json'
$LogDir = Join-Path $Root 'storage\logs'

Add-Type -AssemblyName System.Windows.Forms

function Show-Failure([string]$Message) {
    [System.Windows.Forms.MessageBox]::Show($Message, 'Radar Mensal', 'OK', 'Error') | Out-Null
    exit 1
}

function Read-DotEnv([string]$Path) {
    $values = @{}
    foreach ($line in Get-Content -LiteralPath $Path -Encoding UTF8) {
        if ($line -match '^\s*([A-Z0-9_]+)\s*=\s*(.*)$') {
            $values[$Matches[1]] = $Matches[2].Trim().Trim('"')
        }
    }
    return $values
}

function Test-AppUp([string]$BaseUrl) {
    try {
        $response = Invoke-WebRequest -Uri "$BaseUrl/up" -UseBasicParsing -TimeoutSec 3
        return $response.StatusCode -eq 200
    } catch {
        return $false
    }
}

function Resolve-PgBin {
    $cmd = Get-Command pg_dump.exe -ErrorAction SilentlyContinue
    if ($cmd) { return Split-Path $cmd.Source }
    $candidates = Get-ChildItem 'C:\Program Files\PostgreSQL' -Directory -ErrorAction SilentlyContinue |
        Sort-Object { [int]($_.Name -replace '\D', '0') } -Descending
    foreach ($dir in $candidates) {
        $bin = Join-Path $dir.FullName 'bin'
        if (Test-Path (Join-Path $bin 'pg_dump.exe')) { return $bin }
    }
    return $null
}

Set-Location $Root
New-Item -ItemType Directory -Force $LogDir | Out-Null

if (-not (Test-Path (Join-Path $Root '.env'))) {
    Show-Failure "Arquivo .env nao encontrado em $Root."
}
$envValues = Read-DotEnv (Join-Path $Root '.env')
$appUrl = if ($envValues['APP_URL']) { $envValues['APP_URL'].TrimEnd('/') } else { 'http://localhost:8000' }
$uri = [System.Uri]$appUrl
$appHost = $uri.Host
$appPort = $uri.Port

# 1. Ja esta no ar? So abre o navegador.
if (Test-AppUp $appUrl) {
    Start-Process $appUrl
    exit 0
}

$php = Get-Command php.exe -ErrorAction SilentlyContinue
if (-not $php) { Show-Failure 'php.exe nao esta no PATH.' }

# 2. Postgres.
$pgService = Get-Service -Name 'postgresql*' -ErrorAction SilentlyContinue | Select-Object -First 1
if ($pgService -and $pgService.Status -ne 'Running') {
    try {
        Start-Service $pgService.Name
        $pgService.WaitForStatus('Running', [TimeSpan]::FromSeconds(20))
    } catch {
        Show-Failure "O Postgres ($($pgService.Name)) esta parado e nao subiu. Inicie pelo services.msc (pode exigir administrador)."
    }
}

# 3. Assets: hot orfao quebra a tela; build velho mostra front desatualizado.
$hotFile = Join-Path $Root 'public\hot'
if (Test-Path $hotFile) { Remove-Item $hotFile -Force }

$manifest = Join-Path $Root 'public\build\manifest.json'
$sources = @(
    Get-ChildItem (Join-Path $Root 'resources\js'), (Join-Path $Root 'resources\css') -Recurse -File -ErrorAction SilentlyContinue
    Get-Item (Join-Path $Root 'vite.config.ts'), (Join-Path $Root 'package-lock.json') -ErrorAction SilentlyContinue
)
$newestSource = ($sources | Sort-Object LastWriteTime -Descending | Select-Object -First 1).LastWriteTime
$needsBuild = -not (Test-Path $manifest) -or ((Get-Item $manifest).LastWriteTime -lt $newestSource)
if ($needsBuild) {
    $npm = Get-Command npm.cmd -ErrorAction SilentlyContinue
    if (-not $npm) { Show-Failure 'O front precisa ser recompilado, mas npm nao esta no PATH.' }
    $build = Start-Process -FilePath $npm.Source -ArgumentList 'run', 'build' -WorkingDirectory $Root -WindowStyle Hidden -Wait -PassThru `
        -RedirectStandardOutput (Join-Path $LogDir 'local-build.log') -RedirectStandardError (Join-Path $LogDir 'local-build.err.log')
    if ($build.ExitCode -ne 0) { Show-Failure "npm run build falhou. Veja storage\logs\local-build.err.log." }
}

# 4. Backup (no maximo um a cada $BackupMinAgeHours horas, guarda $BackupKeep).
$pgBin = Resolve-PgBin
if ($pgBin) {
    New-Item -ItemType Directory -Force $BackupDir | Out-Null
    $latest = Get-ChildItem $BackupDir -Filter 'radar-*.dump' -ErrorAction SilentlyContinue |
        Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if (-not $latest -or $latest.LastWriteTime -lt (Get-Date).AddHours(-$BackupMinAgeHours)) {
        $dumpFile = Join-Path $BackupDir ('radar-{0}.dump' -f (Get-Date -Format 'yyyyMMdd-HHmm'))
        $env:PGPASSWORD = $envValues['DB_PASSWORD']
        try {
            & (Join-Path $pgBin 'pg_dump.exe') `
                "--host=$($envValues['DB_HOST'])" "--port=$($envValues['DB_PORT'])" "--username=$($envValues['DB_USERNAME'])" `
                --format=custom --no-owner --no-acl "--file=$dumpFile" $envValues['DB_DATABASE'] 2>> (Join-Path $LogDir 'local-backup.log')
        } finally {
            Remove-Item Env:PGPASSWORD -ErrorAction SilentlyContinue
        }
        Get-ChildItem $BackupDir -Filter 'radar-*.dump' | Sort-Object LastWriteTime -Descending |
            Select-Object -Skip $BackupKeep | Remove-Item -Force
    }
}

# 5. Server + fila, sem janela.
$server = Start-Process -FilePath $php.Source -ArgumentList 'artisan', 'serve', "--host=$appHost", "--port=$appPort" `
    -WorkingDirectory $Root -WindowStyle Hidden -PassThru `
    -RedirectStandardOutput (Join-Path $LogDir 'local-server.log') -RedirectStandardError (Join-Path $LogDir 'local-server.err.log')
$worker = Start-Process -FilePath $php.Source -ArgumentList 'artisan', 'queue:work', '--tries=1', '--timeout=0' `
    -WorkingDirectory $Root -WindowStyle Hidden -PassThru `
    -RedirectStandardOutput (Join-Path $LogDir 'local-queue.log') -RedirectStandardError (Join-Path $LogDir 'local-queue.err.log')

@{ server = $server.Id; worker = $worker.Id; port = $appPort; started_at = (Get-Date).ToString('s') } |
    ConvertTo-Json | Set-Content -LiteralPath $RunFile -Encoding UTF8

# 6. Espera responder e abre o navegador.
$deadline = (Get-Date).AddSeconds($BootTimeoutSeconds)
while ((Get-Date) -lt $deadline) {
    if (Test-AppUp $appUrl) {
        Start-Process $appUrl
        exit 0
    }
    Start-Sleep -Milliseconds 500
}

Show-Failure "O app nao respondeu em $BootTimeoutSeconds s. Veja storage\logs\local-server.err.log e laravel.log."
