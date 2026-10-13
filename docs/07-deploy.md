# Deploy — passo a passo

Este doc tem duas partes: **Parte 1** é o roteiro genérico pra colocar qualquer app Laravel + Postgres num VPS Ubuntu (serve de referência mesmo se você trocar de provedor depois). **Parte 2** é o passo a passo específico pro Radar Mensal, com os comandos exatos pra copiar e colar.

Cotação de referência: USD/BRL ≈ R$5,13 em 18/09/2026, só pra quem for comparar com provedor internacional — mas como o pedido aqui foi **provedor nacional**, todos os preços abaixo já são cobrados direto em reais, sem câmbio, sem IOF e sem surpresa na fatura do cartão.

## Qual VPS usar

Pesquisei hospedagens brasileiras conhecidas em 18/09/2026 (preços reais tirados do site de cada uma, não estimativa):

| Provedor | Plano | Specs | Preço promocional* | Renovação (preço real de longo prazo) |
|---|---|---|---|---|
| **Locaweb** | **VPS 1 GB Linux** | 2 vCPUs, **1 GB RAM**, 40 GB SSD, transferência ilimitada | **R$15,90/mês** | R$25,90/mês |
| Locaweb | VPS 2 GB Linux | 2 vCPUs, 2 GB RAM, 60 GB SSD, transferência ilimitada | R$23,90/mês | R$36,90/mês |
| Hostinger BR | KVM 1 | 1 vCPU, 4 GB RAM, 50 GB NVMe, 4 TB transferência | R$29,99/mês | R$59,99/mês |
| KingHost | Servidor VPS (menor) | — | R$32,90/mês | — |

\* **O preço promocional exige contratar e pagar o ciclo de 24 meses de uma vez** (é assim que praticamente toda hospedagem brasileira anuncia preço "por mês" — vale ler a letra miúda no carrinho antes de fechar). A coluna "renovação" é o valor que passa a valer depois desse ciclo promocional acabar, e dá uma ideia mais realista do custo recorrente se você não quiser prender dinheiro num contrato longo — vale checar no carrinho de cada uma se existe opção de ciclo mensal de verdade (cobrança mês a mês, sem compromisso) e quanto ela custa, porque normalmente é mais cara que a "por mês" da vitrine.

**Minha recomendação: Locaweb, VPS 1 GB Linux.** É a mais barata das opções nacionais que encontrei com RAM suficiente pra rodar Postgres + PHP-FPM + o worker da fila juntos, tem infraestrutura no Brasil (menor latência pra você, que é quem vai usar o app), cobrança 100% em reais com impostos inclusos ("sem pegadinha" é literalmente como eles anunciam), e suporte 24/7 em português caso emperre alguma coisa. A Hostinger BR e a KingHost são alternativas conhecidas, mas saem mais caras pra specs equivalentes ou piores — e no caso da Hostinger, a localização recomendada do servidor no cadastro é Estados Unidos, não Brasil, então vale conferir se existe opção de datacenter brasileiro antes de contratar achando que é local.

A Parte 2 deste doc assume Locaweb + Ubuntu. Se escolher outro provedor, troque só os passos de "criar o servidor" (Parte 2, passo 1) — o resto (pacotes, Nginx, Supervisor, etc.) é igual em qualquer VPS Ubuntu, brasileiro ou não.

## Parte 1 — Roteiro genérico (Laravel + Postgres em qualquer VPS Ubuntu)

1. **Provisionar o servidor**: Ubuntu 24.04 LTS, o menor plano com RAM suficiente (≥ 1 GB recomendado). Anote o IP público.
2. **Acesso**: configurar chave SSH (nunca senha), criar um usuário não-root com sudo, desabilitar login de root por senha.
3. **Firewall**: liberar só SSH (22), HTTP (80) e HTTPS (443).
4. **Pacotes**: PHP (versão exigida pelo `composer.json` do projeto) + extensões que o Laravel precisa, PostgreSQL, Nginx, Composer, Supervisor. Node só se for buildar os assets *no* servidor — evite isso num VPS pequeno (ver passo 8).
5. **Banco de dados**: criar um usuário e um banco Postgres dedicados ao app, senha forte, sem acesso de fora do próprio servidor (`listen_addresses = 'localhost'`).
6. **Clonar e configurar**: `git clone`, `.env` de produção (`APP_ENV=production`, `APP_DEBUG=false`, credenciais reais), `composer install --no-dev --optimize-autoloader`.
7. **Banco**: `php artisan migrate --force` (nunca `migrate:fresh` em produção — apaga tudo).
8. **Assets do front**: builde localmente (`npm run build` na sua máquina) e copie só `public/build/` pro servidor via `scp`/`rsync` — mais rápido, mais seguro, e não exige Node instalado no VPS pequeno. Alternativa: instalar Node no servidor e buildar lá, mais simples de automatizar mas mais pesado pra RAM de 1 GB.
9. **Web server**: Nginx apontando pra `public/`, PHP-FPM como upstream.
10. **Processo de fila**: Supervisor rodando `php artisan queue:work` — sem isso, nenhuma varredura nunca termina, porque os jobs (`HarvestSourcesJob` etc.) ficam presos na fila.
11. **HTTPS**: Certbot (Let's Encrypt), gratuito, renovação automática.
12. **Cache de produção**: `php artisan config:cache`, `route:cache`, `view:cache` — acelera a resposta e evita reler `.env`/rotas a cada request.
13. **Verificação**: acessar pelo domínio/IP, logar, rodar uma varredura de teste, confirmar que o worker processou (`php artisan queue:work` tem que estar de fato rodando, não só configurado).

## Parte 2 — Passo a passo específico (Locaweb + Ubuntu)

### 1. Criar o servidor na Locaweb

1. Acesse [locaweb.com.br/servidor-vps/](https://www.locaweb.com.br/servidor-vps/) e contrate o plano **VPS 1 GB Linux** (ou o de 2 GB, se preferir folga extra de RAM).
2. No carrinho, confira o ciclo de contratação — o preço da vitrine (R$15,90/mês) é pelo ciclo bienal pago à vista; se preferir não prender o dinheiro por 2 anos, veja se há ciclo mensal ou anual disponível e quanto sai.
3. No provisionamento, escolha **Ubuntu** como sistema operacional (a Locaweb lista AlmaLinux, Debian, Rocky Linux, Ubuntu e Windows Server) — pegue a versão LTS mais recente disponível no painel (idealmente 24.04; se só tiver 22.04, ela também funciona, já que o app só exige PHP ≥ 8.3, disponível em ambas via os pacotes padrão do Ubuntu).
4. Finalize a compra. A ativação costuma vir por e-mail com o IP do servidor e as credenciais de acesso — normalmente root com senha inicial, que você troca por autenticação por chave SSH no próximo passo.
5. Gere um par de chaves SSH na sua máquina se ainda não tiver uma: `ssh-keygen -t ed25519`.

Os passos daqui pra frente (acesso, pacotes, banco, deploy) são os mesmos independente de qual VPS Linux você escolheu — o resto do doc assume só que você já tem um IP e acesso root/sudo funcionando.

### 2. Acesso inicial e segurança básica

Primeiro acesso, com a senha que veio por e-mail:

```bash
ssh root@SEU_IP
```

Assim que entrar, troque a senha (`passwd`) e copie sua chave SSH pública pra não depender mais de senha:

```bash
# Na sua máquina, não no servidor:
ssh-copy-id root@SEU_IP
```

De volta ao servidor, como root:

```bash
# Usuário não-root pra rodar o app e fazer deploy
adduser deploy
usermod -aG sudo deploy

# Copia sua chave SSH pro novo usuário
rsync --archive --chown=deploy:deploy ~/.ssh /home/deploy

# A partir daqui, saia e entre de novo como 'deploy'
exit
ssh deploy@SEU_IP
```

Desabilite login root por senha e senha em geral (edite `/etc/ssh/sshd_config`, `PermitRootLogin no`, `PasswordAuthentication no`, depois `sudo systemctl restart ssh`) — só depois de confirmar que `ssh deploy@SEU_IP` com a chave está funcionando, senão você se tranca pra fora.

```bash
sudo apt update && sudo apt upgrade -y

sudo apt install -y ufw
sudo ufw allow OpenSSH
sudo ufw allow 80
sudo ufw allow 443
sudo ufw enable
```

### 3. Swap (recomendado no plano de 1 GB, pra folga sob picos)

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

### 4. PHP, extensões, Composer

O app exige `"php": "^8.3"` (`composer.json`). Ubuntu 24.04 já traz PHP 8.3 nos repositórios oficiais, sem precisar de PPA de terceiros — mas se seu servidor veio com **Ubuntu 22.04** (que só tem PHP 8.1 por padrão), adicione o PPA `ondrej/php` antes de instalar.

```bash
php -v   # veja o que já está instalado antes de mais nada

# Só se a versão acima for menor que 8.3 (Ubuntu 22.04 ou mais antigo):
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update

sudo apt install -y php8.3-fpm php8.3-pgsql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-gd unzip

php -v   # confirma 8.3.x

curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

Se o `php -v` do início já mostrou 8.3 ou mais novo (caso do Ubuntu 24.04), pode instalar os pacotes sem o nome da versão (`php-fpm`, `php-pgsql`, etc.) e pular o PPA — o comando acima com `php8.3-*` funciona nos dois casos, então é o mais seguro de copiar e colar direto.

### 5. PostgreSQL

```bash
sudo apt install -y postgresql

sudo -u postgres psql <<'SQL'
CREATE DATABASE monthly_radar;
CREATE USER radar_app WITH ENCRYPTED PASSWORD 'TROQUE_ESSA_SENHA';
GRANT ALL PRIVILEGES ON DATABASE monthly_radar TO radar_app;
ALTER DATABASE monthly_radar OWNER TO radar_app;
SQL
```

Guarde a senha — vai pro `.env` daqui a pouco. Por padrão o Postgres só escuta em `localhost`, então não precisa mexer em `pg_hba.conf`/`postgresql.conf` pra isso.

### 6. Clonar e configurar o app

```bash
sudo mkdir -p /var/www/monthly-radar
sudo chown deploy:deploy /var/www/monthly-radar
git clone https://github.com/emanuelbrebal/monthly-radar /var/www/monthly-radar
cd /var/www/monthly-radar

composer install --no-dev --optimize-autoloader

cp .env.example .env
php artisan key:generate
```

Edite `.env` (`nano .env`):

```env
APP_NAME="Radar Mensal"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seu-dominio.com.br

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=monthly_radar
DB_USERNAME=radar_app
DB_PASSWORD=TROQUE_ESSA_SENHA

RADAR_USER_EMAIL=seu@email.com
RADAR_USER_PASSWORD=uma-senha-forte-de-verdade

ANTHROPIC_API_KEY=   # opcional — deixe vazio se não quiser enriquecimento por LLM

QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database
```

```bash
php artisan migrate --force
php artisan db:seed --force --class=UserSeeder
php artisan db:seed --force --class=ContestSeeder   # só no PRIMEIRO deploy: são os concursos da pesquisa (CGU, ANPD, BACEN, TJPB...)
# Não rode o ContestSeeder de novo depois: ele faz updateOrCreate por slug e
# sobrescreve o que você mudou pela tela (status de inscrição, maleta, descartes).

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 7. Assets do front (buildar local, subir pro servidor)

Na sua máquina, dentro do repo:

```bash
npm ci
npm run build
rsync -avz public/build/ deploy@SEU_IP:/var/www/monthly-radar/public/build/
```

Repita esse `rsync` sempre que atualizar o front em produção (junto com o `git pull` + `composer install` do próximo deploy).

### 8. Nginx

```bash
sudo apt install -y nginx
sudo nano /etc/nginx/sites-available/monthly-radar
```

```nginx
server {
    listen 80;
    server_name seu-dominio.com.br;
    root /var/www/monthly-radar/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/monthly-radar /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl restart nginx
```

Antes de continuar, aponte o DNS do seu domínio (registro `A`) pro IP do servidor — o Certbot do próximo passo precisa disso resolvendo já.

### 9. HTTPS com Certbot

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d seu-dominio.com.br
```

Ele já reconfigura o Nginx pra HTTPS e agenda a renovação automática sozinho.

### 10. Permissões

```bash
sudo chown -R deploy:www-data /var/www/monthly-radar
sudo find /var/www/monthly-radar -type f -exec chmod 664 {} \;
sudo find /var/www/monthly-radar -type d -exec chmod 775 {} \;
sudo chmod -R ug+rwx /var/www/monthly-radar/storage /var/www/monthly-radar/bootstrap/cache
```

### 11. Worker da fila (Supervisor)

Sem isso, o botão "varrer" cria a `SearchRun` mas ela fica presa em `queued` pra sempre — os jobs do pipeline (`docs/02-pipeline.md`) nunca rodam.

```bash
sudo apt install -y supervisor
sudo nano /etc/supervisor/conf.d/radar-queue-worker.conf
```

```ini
[program:radar-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/monthly-radar/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=/var/www/monthly-radar
user=deploy
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/monthly-radar/storage/logs/queue-worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status radar-queue-worker:*   # deve mostrar RUNNING
```

### 12. Verificação final

1. Acesse `https://seu-dominio.com.br` — deve cair na tela de login.
2. Logue com `RADAR_USER_EMAIL`/`RADAR_USER_PASSWORD`.
3. Escolha uma área, aperte **varrer**.
4. Acompanhe o status mudar de "varrendo…" pra "Varredura feita." — se travar em "varrendo…" pra sempre, o worker do Supervisor não está rodando (volte ao passo 11).
5. Confira `sudo supervisorctl status` e `tail -f /var/www/monthly-radar/storage/logs/laravel.log` se algo der errado.

## Deploys seguintes (depois do primeiro)

```bash
cd /var/www/monthly-radar
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo supervisorctl restart radar-queue-worker:*
```

E, se o front mudou, repita o `npm run build` + `rsync` local do passo 7.
