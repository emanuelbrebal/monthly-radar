# Envia email via Gmail SMTP com app password.
#
# Pré-requisitos:
#   - 2FA habilitado em myaccount.google.com
#   - App password gerado e salvo em $env:GMAIL_APP_PASSWORD (User scope)
#   - Ver scripts/SETUP-EMAIL.md
#
# Uso:
#   .\send-email.ps1 -Subject "Radar — Maio 2026" -HtmlPath ".\email-out\2026-05.html" -PlainPath ".\email-out\2026-05.txt"

[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][string]$Subject,
    [Parameter(Mandatory=$true)][string]$HtmlPath,
    [Parameter(Mandatory=$true)][string]$PlainPath,
    [string]$To = "manu.brebal@gmail.com",
    [string]$From = "manu.brebal@gmail.com",
    [string]$FromDisplayName = "Monthly Radar",
    [string[]]$Attachments = @(),
    [switch]$MarkImportant
)

$ErrorActionPreference = "Stop"

# --- Validar credenciais ---
if (-not $env:GMAIL_APP_PASSWORD) {
    Write-Error "Variavel de ambiente GMAIL_APP_PASSWORD nao definida. Veja scripts/SETUP-EMAIL.md"
    exit 1
}

# --- Validar arquivos ---
if (-not (Test-Path $HtmlPath)) { Write-Error "HTML nao encontrado: $HtmlPath"; exit 1 }
if (-not (Test-Path $PlainPath)) { Write-Error "Plain text nao encontrado: $PlainPath"; exit 1 }

$htmlBody  = Get-Content -Path $HtmlPath  -Raw -Encoding UTF8
$plainBody = Get-Content -Path $PlainPath -Raw -Encoding UTF8

# --- Construir mensagem ---
$mail = New-Object System.Net.Mail.MailMessage
$mail.From = New-Object System.Net.Mail.MailAddress($From, $FromDisplayName, [System.Text.Encoding]::UTF8)
$mail.To.Add($To)
$mail.Subject = $Subject
$mail.SubjectEncoding = [System.Text.Encoding]::UTF8
$mail.BodyEncoding    = [System.Text.Encoding]::UTF8

# Plain como body principal (fallback)
$mail.Body = $plainBody
$mail.IsBodyHtml = $false

# HTML como AlternateView
$htmlView = [System.Net.Mail.AlternateView]::CreateAlternateViewFromString(
    $htmlBody,
    [System.Text.Encoding]::UTF8,
    "text/html"
)
$mail.AlternateViews.Add($htmlView)

# Importance flag (headers + X-Priority pra Gmail tratar como destacado)
if ($MarkImportant) {
    $mail.Priority = [System.Net.Mail.MailPriority]::High
    $mail.Headers.Add("X-Priority", "1 (Highest)")
    $mail.Headers.Add("X-MSMail-Priority", "High")
    $mail.Headers.Add("Importance", "High")
}

# Anexos
foreach ($att in $Attachments) {
    if (Test-Path $att) {
        $mail.Attachments.Add((New-Object System.Net.Mail.Attachment($att)))
    } else {
        Write-Warning "Anexo nao encontrado, ignorando: $att"
    }
}

# --- Enviar ---
$smtp = New-Object System.Net.Mail.SmtpClient("smtp.gmail.com", 587)
$smtp.EnableSsl = $true
$smtp.Credentials = New-Object System.Net.NetworkCredential($From, $env:GMAIL_APP_PASSWORD)

try {
    $smtp.Send($mail)
    Write-Host "[OK] Email enviado: $Subject -> $To" -ForegroundColor Green
    exit 0
}
catch {
    Write-Error "Falha ao enviar: $($_.Exception.Message)"
    exit 2
}
finally {
    $mail.Dispose()
    $smtp.Dispose()
}
