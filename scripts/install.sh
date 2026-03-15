#!/usr/bin/env bash
set -e

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

if ! command -v php >/dev/null 2>&1; then
  echo "PHP introuvable"
  exit 1
fi

if ! command -v composer >/dev/null 2>&1; then
  echo "Composer introuvable"
  exit 1
fi

composer install

if [ ! -f ".env" ] && [ -f ".env.example" ]; then
  cp .env.example .env
fi

mkdir -p storage/cache storage/logs storage/pdf
touch storage/cache/.gitkeep storage/logs/.gitkeep storage/pdf/.gitkeep

chmod -R 775 storage || true

echo "Installation terminée."
echo "Étapes suivantes :"
echo "1) Configurer .env"
echo "2) Importer database/schema.sql"
echo "3) Lancer le worker PDF : php worker/pdf_worker.php"