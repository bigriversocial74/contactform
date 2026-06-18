#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

echo "[pre-pr] Validating Composer metadata"
composer validate --strict

echo "[pre-pr] Checking PHP syntax"
find . -type f -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l

echo "[pre-pr] Running security regression tests"
composer test-security

echo "[pre-pr] Running frontend contract validation"
composer test-frontend-contracts

echo "[pre-pr] Running complete PHPUnit suite"
composer test

echo "[pre-pr] All required validation passed"
