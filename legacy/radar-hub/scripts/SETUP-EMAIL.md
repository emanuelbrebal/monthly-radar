# Setup do envio de email (one-time, ~5min)

O `radar-reporter` usa o script `send-email.ps1` que fala SMTP direto com o Gmail. Precisa de um **app password** (não a senha normal da conta).

## Passos

### 1. Habilitar 2FA (se ainda não tem)
- Vai em https://myaccount.google.com/security
- Ative "Verificação em duas etapas"

### 2. Gerar app password
- Em https://myaccount.google.com/apppasswords
- Nome do app: `monthly-radar`
- Copia os 16 caracteres gerados (formato: `xxxx xxxx xxxx xxxx`)

### 3. Salvar como variável de ambiente (User scope, persiste entre sessões)

No PowerShell:

```powershell
[System.Environment]::SetEnvironmentVariable("GMAIL_APP_PASSWORD", "xxxx xxxx xxxx xxxx", "User")
```

(Substitui pelos 16 chars reais. Pode manter ou remover os espaços — Gmail aceita os dois.)

### 4. Fechar e reabrir o terminal
Pra a variável carregar na próxima sessão.

### 5. Verificar
```powershell
$env:GMAIL_APP_PASSWORD  # deve imprimir os chars
```

### 6. Testar envio
```powershell
# Cria HTML e plain de teste
"<h1>Teste</h1><p>Funcionou</p>" | Out-File -FilePath test.html -Encoding UTF8
"Teste plain" | Out-File -FilePath test.txt -Encoding UTF8

.\send-email.ps1 -Subject "[radar] teste" -HtmlPath test.html -PlainPath test.txt

# Limpa
Remove-Item test.html, test.txt
```

Se chegou na inbox: pronto. Se deu erro de auth, regenera app password.

## Notas

- App password é específico da máquina onde tu rodar. Se trocar de máquina, gera outro.
- Tu pode revogar a qualquer momento em https://myaccount.google.com/apppasswords
- Se a Anthropic um dia oferecer Gmail MCP nativo no Pro, migramos. Por enquanto, SMTP é mais desacoplado.
