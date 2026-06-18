#!/usr/bin/env bash
set -euo pipefail

BASE_REF="${1:-origin/main}"

if ! git rev-parse --verify "$BASE_REF" >/dev/null 2>&1; then
  BASE_REF="main"
fi

mapfile -t changed_files < <(git diff --name-only --diff-filter=ACMRT "$BASE_REF"...HEAD)

if [ "${#changed_files[@]}" -eq 0 ]; then
  echo "No changed files detected against $BASE_REF."
  exit 0
fi

echo "Preflight base: $BASE_REF"
printf ' - %s\n' "${changed_files[@]}"

composer validate --strict

php_files=()
frontend_changed=false
action_center_changed=false
migration_changed=false
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
    api/account/action-center*.php|api/account/_action_center.php|assets/js/gift-action-center.js|includes/gift-action-center.php)
      action_center_changed=true
      ;;
  esac

  case "$file" in
    database/*.sql|config/migrations.php|includes/migrations.php|scripts/run_migrations.php|scripts/build_full_upgrade_sql.php|scripts/validate_migration_manifest.php)
      migration_changed=true
      ;;
  esac
done

if [ "${#php_files[@]}" -gt 0 ]; then
  echo "Running PHP syntax checks on changed files..."
  for file in "${php_files[@]}"; do
    php -l "$file"
  done
fi

if [ "$migration_changed" = true ]; then
  echo "Validating canonical migration manifest..."
  php scripts/validate_migration_manifest.php
  temp_upgrade="$(mktemp -t microgifter-upgrade-XXXXXX.sql)"
  php scripts/build_full_upgrade_sql.php "$temp_upgrade"
  rm -f "$temp_upgrade" "${temp_upgrade%.sql}.manifest.json"
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

echo "Preflight passed."
