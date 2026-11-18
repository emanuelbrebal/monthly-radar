# Radar Mensal - para o server e a fila que o start.ps1 subiu.
# Arquivo em ASCII de proposito (PowerShell 5.1 le .ps1 sem BOM como ANSI).

$Root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$RunFile = Join-Path $Root 'storage\app\local-run.json'
$port = 8000

function Stop-Tree([int]$ProcessId) {
    # artisan serve e queue:work sobem processos php filhos; mata a arvore.
    Get-CimInstance Win32_Process -Filter "ParentProcessId = $ProcessId" -ErrorAction SilentlyContinue |
        ForEach-Object { Stop-Tree $_.ProcessId }
    Stop-Process -Id $ProcessId -Force -ErrorAction SilentlyContinue
}

if (Test-Path $RunFile) {
    $run = Get-Content -LiteralPath $RunFile -Raw | ConvertFrom-Json
    if ($run.port) { $port = [int]$run.port }
    foreach ($id in @($run.server, $run.worker)) {
        if ($id) { Stop-Tree ([int]$id) }
    }
    Remove-Item $RunFile -Force
}

# Fallback: quem ainda escuta na porta (ex.: app subido a mao).
Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue |
    Select-Object -ExpandProperty OwningProcess -Unique |
    ForEach-Object { Stop-Tree $_ }
