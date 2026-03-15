#!/usr/bin/env bash
set -e

if ! command -v php >/dev/null 2>&1; then
  echo "PHP introuvable"
  exit 1
fi

if ! command -v composer >/dev/null 2>&1; then
  echo "Composer introuvable"
  exit 1
fi

composer install