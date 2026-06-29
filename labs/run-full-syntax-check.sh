#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

status=0

check_file() {
  local file="$1"
  if php -l "$file"; then
    return 0
  fi
  status=1
}

for file in ./*.php ./app/*.php ./admin/*.php; do
  check_file "$file"
done

exit "$status"
