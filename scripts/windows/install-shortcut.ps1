# Radar Mensal - cria os atalhos "Radar Mensal" e "Parar Radar" na Area de Trabalho.
# Rode uma vez:  powershell -ExecutionPolicy Bypass -File scripts\windows\install-shortcut.ps1
# Arquivo em ASCII de proposito (PowerShell 5.1 le .ps1 sem BOM como ANSI).

$Root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$Desktop = [Environment]::GetFolderPath('Desktop')
$PowerShell = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
$Icon = Join-Path $Root 'public\favicon.ico'
$Shell = New-Object -ComObject WScript.Shell

function New-RadarShortcut([string]$Name, [string]$Script, [string]$Description) {
    $link = $Shell.CreateShortcut((Join-Path $Desktop "$Name.lnk"))
    $link.TargetPath = $PowerShell
    $link.Arguments = "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$(Join-Path $PSScriptRoot $Script)`""
    $link.WorkingDirectory = $Root
    $link.WindowStyle = 7
    $link.Description = $Description
    if (Test-Path $Icon) { $link.IconLocation = $Icon }
    $link.Save()
    Write-Output "Atalho criado: $(Join-Path $Desktop "$Name.lnk")"
}

New-RadarShortcut 'Radar Mensal' 'start.ps1' 'Abre o Radar Mensal no navegador'
New-RadarShortcut 'Parar Radar' 'stop.ps1' 'Para o server e a fila do Radar Mensal'
