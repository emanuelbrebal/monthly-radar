#!/usr/bin/env bash
# Builda o front NA SUA MÁQUINA e envia public/build/ pro VPS. Usa tar+ssh
# (funciona no Git Bash do Windows, que não tem rsync). Troca a pasta de uma
# vez no servidor, sem deixar build pela metade no ar.
#
#   bash deploy/push-assets.sh deploy@SEU_IP
set -euo pipefail

TARGET="${1:?uso: bash deploy/push-assets.sh deploy@SEU_IP}"
APP_DIR="${APP_DIR:-/var/www/monthly-radar}"

cd "$(dirname "$0")/.."

npm ci
npm run build

tar -czf - -C public build | ssh "$TARGET" "set -e
    cd '$APP_DIR/public'
    rm -rf build.new build.old
    mkdir build.new
    tar -xzf - -C build.new --strip-components=1
    if [ -d build ]; then mv build build.old; fi
    mv build.new build
    rm -rf build.old"

echo "Assets enviados para $TARGET:$APP_DIR/public/build"
