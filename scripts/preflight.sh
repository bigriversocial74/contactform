#!/usr/bin/env bash
set -euo pipefail

BASE_REF="${1:-origin/integration-from-repair-20260628}"

if ! git rev-parse --verify "$BASE_REF" >/dev/null 2>&1; then
  if git rev-parse --verify integration-from-repair-20260628 >/dev/null 2>&1; then
    BASE_REF="integration-from-repair-20260628"
  elif git rev-parse --verify origin/main >/dev/null 2>&1; then
    BASE_REF="origin/main"
  else
    BASE_REF="main"
  fi
fi

mapfile -t changed_files < <(git diff --name-only --diff-filter=ACMRT "$BASE_REF"...HEAD)

if [ "${#changed_files[@]}" -eq 0 ]; then
  echo "No changed files detected against $BASE_REF."
  exit 0
fi

echo "Preflight base: $BASE_REF"
printf ' - %s\n' "${changed_files[@]}"

composer validate --strict
composer audit --locked --no-interaction

php_files=()
js_files=()
shell_files=()
frontend_changed=false
action_center_changed=false
migration_changed=false
homeserver_changed=false
declare -A test_map=()

add_test() {
  local test_file="$1"
  if [ -f "$test_file" ]; then
    test_map["$test_file"]=1
  fi
}

add_matching_tests() {
  local pattern="$1"
  while IFS= read -r test_file; do
    [ -n "$test_file" ] && add_test "$test_file"
  done < <(find tests/phpunit -maxdepth 1 -type f -name "$pattern" -print | sort)
}

for file in "${changed_files[@]}"; do
  case "$file" in
    *.php)
      php_files+=("$file")
      ;;
    *.js)
      case "$file" in
        */vendor/*|*/node_modules/*|*/third-party/*|*/dist/*|*.min.js) ;;
        *) js_files+=("$file") ;;
      esac
      ;;
    *.sh)
      shell_files+=("$file")
      ;;
  esac

  case "$file" in
    assets/*|includes/*|*.php|config/frontend-contracts.php)
      frontend_changed=true
      ;;
  esac

  case "$file" in
    tests/phpunit/*Test.php)
      add_test "$file"
      ;;
  esac

  case "$file" in
    api/account/action-center*.php|api/account/_action_center*.php|assets/js/gift-action-center*.js|assets/css/gift-action-center*.css|includes/gift-action-center.php)
      action_center_changed=true
      ;;
  esac

  case "$file" in
    database/*.sql|config/migrations.php|includes/migrations.php|scripts/run_migrations.php|scripts/build_full_upgrade_sql.php|scripts/validate_migration_manifest.php)
      migration_changed=true
      ;;
  esac

  case "$file" in
    api/homeserver/*|database/20260724_homeserver_cloud_pairing_sync_v1.sql|scripts/validate_homeserver_pairing_sync_v1.php)
      homeserver_changed=true
      ;;
  esac
done

if [ "${#php_files[@]}" -gt 0 ]; then
  echo "Running PHP syntax checks on changed files..."
  for file in "${php_files[@]}"; do
    php -l "$file"
  done
fi

if [ "${#js_files[@]}" -gt 0 ]; then
  echo "Running JavaScript syntax checks on changed files..."
  for file in "${js_files[@]}"; do
    node --check "$file"
  done
fi

if [ "${#shell_files[@]}" -gt 0 ]; then
  echo "Running shell syntax checks on changed files..."
  for file in "${shell_files[@]}"; do
    bash -n "$file"
  done
fi

if [ "$migration_changed" = true ]; then
  echo "Validating canonical migration manifest..."
  php scripts/validate_migration_manifest.php
  temp_upgrade="$(mktemp -t microgifter-upgrade-XXXXXX.sql)"
  php scripts/build_full_upgrade_sql.php "$temp_upgrade"
  rm -f "$temp_upgrade" "${temp_upgrade%.sql}.manifest.json"
fi

if [ "$homeserver_changed" = true ]; then
  echo "Validating HomeServer pairing and synchronization protocol..."
  php scripts/validate_homeserver_pairing_sync_v1.php
fi

if [ "$frontend_changed" = true ]; then
  echo "Running frontend contract validation..."
  php scripts/validate_frontend_contracts.php
fi

if [ "$action_center_changed" = true ]; then
  add_matching_tests '*ActionCenter*Test.php'
  add_matching_tests 'ActionCenter*Test.php'
  add_matching_tests 'GiftActionCenter*Test.php'
fi

if [ "${#test_map[@]}" -gt 0 ]; then
  if [ ! -x vendor/bin/phpunit ]; then
    echo "vendor/bin/phpunit is missing. Run composer install first." >&2
    exit 1
  fi
  mapfile -t test_files < <(printf '%s\n' "${!test_map[@]}" | sort)
  echo "Running targeted PHPUnit contracts..."
  printf ' - %s\n' "${test_files[@]}"
  vendor/bin/phpunit --configuration phpunit.xml.dist "${test_files[@]}"
fi

echo "Running repository production quality gate..."
php scripts/audit_repository_production_quality.php --gate

echo "Preflight passed."
