#!/usr/bin/env bash
# Deploy no VPS (roda NO SERVIDOR, como o usuário 'deploy'). Idempotente:
# rodar duas vezes seguidas não quebra nada. Passo a passo em docs/07-deploy.md.
#
#   bash deploy/deploy.sh
#
# Os assets do front NÃO são buildados aqui (VPS de 1 GB, sem Node): rode
# deploy/push-assets.sh na sua máquina antes ou depois deste script.
set -euo pipefail
# Arquivos criados aqui (cache, log do dia) precisam ser graváveis pelo
# grupo www-data do PHP-FPM.
umask 002

APP_DIR="${APP_DIR:-/var/www/monthly-radar}"
BRANCH="${BRANCH:-main}"

cd "$APP_DIR"

if [[ ! -f .env ]]; then
    echo "ERRO: $APP_DIR/.env não existe (copie de .env.production.example)." >&2
    exit 1
fi

php artisan down --retry=15 || true
# Volta ao ar mesmo se algum passo falhar — o app antigo ainda está inteiro
# quando o erro é no pull/composer, e a mensagem de erro fica no terminal.
trap 'php artisan up' EXIT

git fetch --quiet origin "$BRANCH"
git checkout --quiet "$BRANCH"
git merge --ff-only "origin/$BRANCH"

composer install --no-dev --optimize-autoloader --no-interaction --no-progress

php artisan migrate --force
php artisan optimize

# Reinício gracioso do worker: termina o job atual e o Supervisor sobe de novo
# com o código novo. Não precisa de sudo.
php artisan queue:restart

if [[ ! -f public/build/manifest.json ]]; then
    echo "AVISO: public/build/manifest.json ausente — rode deploy/push-assets.sh na sua máquina." >&2
fi

echo "Deploy ok: $(git log --oneline -1)"
