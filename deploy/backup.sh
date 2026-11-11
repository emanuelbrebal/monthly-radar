#!/usr/bin/env bash
# Backup diário do Postgres (roda NO SERVIDOR via cron do sistema — não tem
# relação com varredura; o app continua sem agendamento de busca).
#
#   crontab -e  →  15 3 * * * bash /var/www/monthly-radar/deploy/backup.sh
#
# Guarda os últimos 7 dumps em ~/backups. Restaurar: docs/07-deploy.md.
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/monthly-radar}"
BACKUP_DIR="${BACKUP_DIR:-$HOME/backups}"
KEEP="${KEEP:-7}"

env_value() {
    grep -E "^$1=" "$APP_DIR/.env" | tail -1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/'
}

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

file="$BACKUP_DIR/radar-$(date +%Y%m%d-%H%M).dump"

PGPASSWORD="$(env_value DB_PASSWORD)" pg_dump \
    --host="$(env_value DB_HOST)" \
    --port="$(env_value DB_PORT)" \
    --username="$(env_value DB_USERNAME)" \
    --format=custom --no-owner --no-acl \
    --file="$file" \
    "$(env_value DB_DATABASE)"

# Apaga os mais antigos, mantendo os $KEEP mais recentes.
ls -1t "$BACKUP_DIR"/radar-*.dump | tail -n +"$((KEEP + 1))" | xargs -r rm --

echo "Backup ok: $file"
