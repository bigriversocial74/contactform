#!/usr/bin/env bash
set -euo pipefail

DB_HOST="${MG_DB_HOST:-db}"
DB_USER="${MG_DB_USER:-microgifter}"
DB_PASS="${MG_DB_PASS:-microgifter}"

until mysqladmin ping -h"${DB_HOST}" -u"${DB_USER}" -p"${DB_PASS}" --silent; do
  echo "Waiting for MySQL at ${DB_HOST}..."
  sleep 2
done

composer install --no-interaction --prefer-dist
php scripts/validate_migration_manifest.php
php scripts/run_migrations.php

exec php -S 0.0.0.0:8000 -t .
