# Deploy — passo a passo

Este doc tem duas partes. A **Parte 1** é o roteiro genérico para colocar qualquer app Laravel + Postgres num VPS Ubuntu. A **Parte 2** é o passo a passo do Radar Mensal, com comandos para copiar e colar, usando os arquivos versionados em `deploy/`:

| Arquivo | Onde roda | Para quê |
|---|---|---|
| `.env.production.example` | servidor | template do `.env` de produção (debug desligado, cookie seguro, log diário) |
| `deploy/deploy.sh` | servidor | deploy idempotente: `down` → pull → composer → migrate → caches → reinício do worker → `up` |
| `deploy/push-assets.sh` | sua máquina | `npm run build` + envio de `public/build/` via `tar`+`ssh` (o Git Bash do Windows não tem `rsync`) |
| `deploy/backup.sh` | servidor (cron) | `pg_dump` diário, guarda os 7 mais recentes |
| `deploy/nginx/monthly-radar.conf` | servidor | site do Nginx |
| `deploy/supervisor/radar-queue-worker.conf` | servidor | worker da fila (sem ele a varredura fica presa em `queued`) |

## Qual VPS usar

Preços conferidos no site oficial em **11/11/2026** ([locaweb.com.br/servidor-vps](https://www.locaweb.com.br/servidor-vps/)). Provedor nacional, cobrança em reais:

| Plano Locaweb | Specs | Preço promocional* | Renovação |
|---|---|---|---|
| VPS 1 GB Linux | 2 vCPUs, 1 GB RAM, 40 GB SSD | R$15,90/mês | R$23,92/mês |
| **VPS 2 GB Linux** | 2 vCPUs, **2 GB RAM**, 60 GB SSD | **R$21,90/mês** | R$31,92/mês |

\* O preço promocional exige o ciclo de **24 meses**. Confira no carrinho se há ciclo mensal e quanto custa.

**Recomendação: VPS 2 GB.** O de 1 GB roda Postgres + PHP-FPM + worker com o swap do passo 3, mas o `composer install` e a varredura (parse de HTML) disputam memória ali. Por R$6/mês a mais, o de 2 GB tira esse risco. Outras opções nacionais com 1 GB (Hostinger KVM 1, KingHost) aparecem entre R$22 e R$28/mês em agregadores, sem conferência no site oficial.

A Parte 2 assume Locaweb + Ubuntu 24.04. Em outro provedor, só o passo 1 muda.

## Parte 1 — Roteiro genérico (Laravel + Postgres em qualquer VPS Ubuntu)

1. **Provisionar**: Ubuntu 24.04 LTS, ≥ 1 GB RAM. Anote o IP público.
2. **Acesso**: chave SSH (nunca senha), usuário não-root com sudo, login de root por senha desabilitado.
3. **Firewall**: só SSH (22), HTTP (80) e HTTPS (443).
4. **Pacotes**: PHP (versão do `composer.json`) + extensões, PostgreSQL, Nginx, Composer, Supervisor. Sem Node no servidor.
5. **Banco**: usuário e banco dedicados, senha forte, só `localhost`.
6. **App**: `git clone`, `.env` de produção, `composer install --no-dev`.
7. **Dados**: `migrate --force` (nunca `migrate:fresh` em produção) ou restore de um dump.
8. **Assets**: build local, envio só de `public/build/`.
9. **Web**: Nginx → `public/`, PHP-FPM como upstream.
10. **Fila**: Supervisor rodando `queue:work`.
11. **HTTPS**: domínio apontado + Certbot.
12. **Backup**: dump diário do banco.
13. **Verificação**: logar, varrer, confirmar que o worker processou.

## Parte 2 — Passo a passo (Locaweb + Ubuntu 24.04)

### 1. Criar o servidor

1. Contrate o **VPS 2 GB Linux** (ou 1 GB) em [locaweb.com.br/servidor-vps](https://www.locaweb.com.br/servidor-vps/). Sistema: **Ubuntu 24.04**.
2. A ativação chega por e-mail com o IP e a senha de root.
3. Na sua máquina, gere uma chave SSH se não tiver: `ssh-keygen -t ed25519`.

### 2. Acesso inicial e segurança básica

```bash
# Na sua máquina:
ssh root@SEU_IP          # senha do e-mail; troque com `passwd` ao entrar
ssh-copy-id root@SEU_IP
```

No servidor, como root:

```bash
adduser deploy
usermod -aG sudo deploy
rsync --archive --chown=deploy:deploy ~/.ssh /home/deploy
exit
```

```bash
ssh deploy@SEU_IP   # confirme que entra com a chave ANTES do próximo passo
```

Desabilite login por senha: em `/etc/ssh/sshd_config`, `PermitRootLogin no` e `PasswordAuthentication no`, depois `sudo systemctl restart ssh`.

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y ufw fail2ban unattended-upgrades
sudo ufw allow OpenSSH && sudo ufw allow 80 && sudo ufw allow 443
sudo ufw enable
sudo dpkg-reconfigure -plow unattended-upgrades   # responda "Yes": patches de segurança automáticos
```

O `fail2ban` já vem protegendo o SSH com a configuração padrão.

### 3. Swap

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

### 4. PHP, Composer, Nginx, Supervisor

O app exige PHP `^8.3`, que o Ubuntu 24.04 já traz.

```bash
sudo apt install -y php8.3-fpm php8.3-pgsql php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-zip php8.3-bcmath php8.3-gd unzip git nginx supervisor
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 5. PostgreSQL 17

O banco local (Windows) é **Postgres 17**, e o Ubuntu 24.04 instala o 16. Um dump do 17 pode não restaurar no 16. Por isso, instale o 17 pelo repositório oficial do PostgreSQL (PGDG):

```bash
sudo apt install -y postgresql-common
sudo /usr/share/postgresql-common/pgdg/apt.postgresql.org.sh -y
sudo apt install -y postgresql-17

DB_PASS="$(openssl rand -base64 24)"; echo "Senha do banco: $DB_PASS"   # guarde: vai no .env
sudo -u postgres psql <<SQL
CREATE USER radar_app WITH ENCRYPTED PASSWORD '$DB_PASS';
CREATE DATABASE monthly_radar OWNER radar_app;
SQL
```

O Postgres só escuta em `localhost` por padrão.

### 6. Clonar e configurar o app

```bash
sudo mkdir -p /var/www/monthly-radar
sudo chown deploy:www-data /var/www/monthly-radar
git clone https://github.com/emanuelbrebal/monthly-radar /var/www/monthly-radar
cd /var/www/monthly-radar
git config core.fileMode false   # o chmod do passo 10 não pode "sujar" o git

composer install --no-dev --optimize-autoloader --no-interaction

cp .env.production.example .env
php artisan key:generate
nano .env
```

No `.env`, preencha:
- `APP_URL`: o subdomínio do passo 8.
- `DB_PASSWORD`: a senha do passo 5.
- `RADAR_USER_EMAIL` e `RADAR_USER_PASSWORD`. Gere a senha com `openssl rand -base64 18`. **Em produção o `UserSeeder` recusa senha com menos de 12 caracteres.**

### 7. Dados: restaurar o banco local

Isso leva pins, inscrições, resultados de prova, descartes e o histórico de raspagem para produção. Os `raw_listings` vão junto, então a próxima varredura não traz de volta o ruído que você descartou. Faça uma vez só, com o banco de produção ainda vazio.

**Na sua máquina** (Git Bash, no repo):

```bash
"/c/Program Files/PostgreSQL/17/bin/pg_dump.exe" -h 127.0.0.1 -U postgres \
  --format=custom --no-owner --no-acl \
  --exclude-table-data=sessions --exclude-table-data=cache --exclude-table-data=cache_locks \
  --exclude-table-data=jobs --exclude-table-data=job_batches --exclude-table-data=failed_jobs \
  --exclude-table-data=password_reset_tokens \
  --file=radar-local.dump monthly_radar
scp radar-local.dump deploy@SEU_IP:~/
```

**No servidor:**

```bash
cd /var/www/monthly-radar
pg_restore -h 127.0.0.1 -U radar_app -d monthly_radar --no-owner --no-acl --exit-on-error ~/radar-local.dump
php artisan migrate --force                            # aplica o que for mais novo que o dump
php artisan db:seed --force --class=UserSeeder         # troca a senha local pela do .env
shred -u ~/radar-local.dump                            # apaga o dump (tinha o hash da senha local)
```

O `UserSeeder` só atualiza a senha e não mexe no seu filtro de estados.

Se preferir **começar do zero**, pule este passo e rode:

```bash
php artisan migrate --force
php artisan db:seed --force --class=UserSeeder
php artisan db:seed --force --class=ContestSeeder
```

### 8. Subdomínio grátis (DuckDNS)

1. Entre em [duckdns.org](https://www.duckdns.org) (login com GitHub ou Google).
2. Crie um subdomínio, por exemplo `radar-manu`. Fica `radar-manu.duckdns.org`.
3. No campo **current ip**, ponha o IP do VPS e clique **update ip**. O IP do VPS é fixo, então não precisa de cliente de atualização.
4. Confira da sua máquina: `nslookup radar-manu.duckdns.org` tem que devolver o IP do VPS.
5. Ajuste `APP_URL=https://radar-manu.duckdns.org` no `.env`.

### 9. Nginx + HTTPS

```bash
cd /var/www/monthly-radar
sudo cp deploy/nginx/monthly-radar.conf /etc/nginx/sites-available/monthly-radar
sudo sed -i 's/SEU-SUBDOMINIO/radar-manu/' /etc/nginx/sites-available/monthly-radar
sudo ln -sf /etc/nginx/sites-available/monthly-radar /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx

sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d radar-manu.duckdns.org --redirect
```

O Certbot cria o bloco HTTPS, redireciona 80 → 443 e agenda a renovação. Com `SESSION_SECURE_COOKIE=true`, o login **só funciona por HTTPS**. Isso é intencional.

### 10. Permissões

```bash
sudo chown -R deploy:www-data /var/www/monthly-radar
sudo find /var/www/monthly-radar -type f -exec chmod 664 {} \;
sudo find /var/www/monthly-radar -type d -exec chmod 775 {} \;
# setgid: tudo criado dentro de storage/ e bootstrap/cache herda o grupo www-data
sudo find /var/www/monthly-radar/storage /var/www/monthly-radar/bootstrap/cache -type d -exec chmod g+s {} \;
sudo chmod 640 /var/www/monthly-radar/.env
```

O worker e o `deploy.sh` rodam como `deploy` com `umask 002`. O PHP-FPM roda como `www-data`. Com o setgid, os dois conseguem escrever nos mesmos logs e caches.

### 11. Worker da fila

```bash
sudo cp /var/www/monthly-radar/deploy/supervisor/radar-queue-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status   # radar-queue-worker_00 RUNNING
```

### 12. Primeiro deploy + assets

**No servidor:**

```bash
bash /var/www/monthly-radar/deploy/deploy.sh
```

**Na sua máquina** (Git Bash, no repo):

```bash
bash deploy/push-assets.sh deploy@SEU_IP
```

### 13. Backup diário

```bash
crontab -e
# acrescente a linha:
15 3 * * * bash /var/www/monthly-radar/deploy/backup.sh >> $HOME/backups/backup.log 2>&1
```

Teste uma vez na mão com `bash /var/www/monthly-radar/deploy/backup.sh`. Os dumps ficam em `~/backups` (7 mais recentes). Para restaurar um:

```bash
pg_restore -h 127.0.0.1 -U radar_app -d monthly_radar --clean --if-exists --no-owner --no-acl ~/backups/radar-AAAAMMDD-HHMM.dump
```

Copie um dump para a sua máquina de vez em quando (`scp deploy@SEU_IP:~/backups/*.dump .`). Backup só dentro do VPS não protege contra perder o VPS.

### 14. Verificação final

1. `https://radar-manu.duckdns.org` → tela de login, com cadeado.
2. Logue com `RADAR_USER_EMAIL`/`RADAR_USER_PASSWORD`. Concursos, maleta e resultados devem estar iguais aos locais.
3. **Ative o 2FA:** Configurações → Segurança → autenticação em dois fatores. O app fica na internet e só tem você como usuário.
4. Rode uma varredura. O status deve ir de "varrendo…" para "Varredura feita.". Se travar, confira o worker: `sudo supervisorctl status` e `tail -f storage/logs/queue-worker.log`.
5. Erros: `tail -f /var/www/monthly-radar/storage/logs/laravel-$(date +%F).log`.

**Esqueci a senha:** o app não envia e-mail. O link de reset vai para o log do dia, no servidor (`grep reset storage/logs/laravel-*.log`). Outra opção: troque `RADAR_USER_PASSWORD` no `.env` e rode `php artisan config:clear && php artisan db:seed --force --class=UserSeeder`.

## Deploys seguintes

```bash
# 1. Na sua máquina: push do código pra main no GitHub.
# 2. No servidor:
bash /var/www/monthly-radar/deploy/deploy.sh
# 3. Se o front mudou, na sua máquina:
bash deploy/push-assets.sh deploy@SEU_IP
```

O `deploy.sh` pode rodar quantas vezes quiser. Se algum passo falhar, ele tira o app do modo de manutenção e mostra o erro no terminal.

**Rollback:** `cd /var/www/monthly-radar && git checkout <commit-anterior> && composer install --no-dev -o && php artisan optimize && php artisan queue:restart`. Migration já aplicada só volta com `php artisan migrate:rollback --step=1 --force`; confira antes o que ela desfaz.
