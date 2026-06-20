#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUTPUT_DIR="${ROOT}/build/release-evidence"
KEEP_BACKUP=0

usage() {
  cat <<'EOF'
Usage: bash scripts/validate_database_backup_restore.sh [options]

Creates a consistent database backup, restores it into an isolated database,
verifies migration state and a canary record, and removes the temporary restore.

Options:
  --output-dir <path>  Evidence and optional backup destination.
  --keep-backup        Retain the compressed SQL backup after validation.
  --help               Show this help text.
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --output-dir)
      OUTPUT_DIR="${2:?Missing value for --output-dir}"
      shift 2
      ;;
    --keep-backup)
      KEEP_BACKUP=1
      shift
      ;;
    --help|-h)
      usage
      exit 0
      ;;
    *)
      echo "Unknown option: $1" >&2
      usage >&2
      exit 2
      ;;
  esac
done

for command in mysql mysqldump gzip gunzip php sha256sum; do
  command -v "${command}" >/dev/null 2>&1 || {
    echo "Required command is unavailable: ${command}" >&2
    exit 1
  }
done

DB_HOST="${MG_DB_HOST:-127.0.0.1}"
DB_PORT="${MG_DB_PORT:-3306}"
SOURCE_DB="${MG_DB_NAME:-}"
ADMIN_USER="${MG_MIGRATION_DB_USER:-${MG_DB_USER:-}}"
ADMIN_PASS="${MG_MIGRATION_DB_PASS:-${MG_DB_PASS:-}}"
DB_CHARSET="${MG_DB_CHARSET:-utf8mb4}"

[[ -n "${SOURCE_DB}" && -n "${ADMIN_USER}" ]] || {
  echo "MG_DB_NAME and a migration/database user are required." >&2
  exit 1
}
[[ "${SOURCE_DB}" =~ ^[A-Za-z0-9_]+$ ]] || {
  echo "MG_DB_NAME contains unsupported characters." >&2
  exit 1
}

RUN_ID="$(date -u +%Y%m%dT%H%M%SZ)-$$"
RESTORE_DB="${MG_RESTORE_DB_NAME:-${SOURCE_DB}_restore_${RANDOM}_$$}"
[[ "${RESTORE_DB}" =~ ^[A-Za-z0-9_]+$ ]] || {
  echo "Restore database name contains unsupported characters." >&2
  exit 1
}
[[ "${RESTORE_DB}" != "${SOURCE_DB}" ]] || {
  echo "Restore validation database must differ from the source database." >&2
  exit 1
}

OUTPUT_DIR="$(mkdir -p "${OUTPUT_DIR}" && cd "${OUTPUT_DIR}" && pwd)"
BACKUP_PATH="${OUTPUT_DIR}/${SOURCE_DB}-${RUN_ID}.sql.gz"
CHECKSUM_PATH="${BACKUP_PATH}.sha256"
REPORT_PATH="${OUTPUT_DIR}/backup-restore-validation.json"
CANARY_KEY="backup_restore_canary_${RUN_ID//[^A-Za-z0-9]/_}"
CANARY_ID=""
RESTORE_CREATED=0

mysql_admin() {
  MYSQL_PWD="${ADMIN_PASS}" mysql \
    --protocol=TCP \
    --host="${DB_HOST}" \
    --port="${DB_PORT}" \
    --user="${ADMIN_USER}" \
    --default-character-set="${DB_CHARSET}" \
    "$@"
}

cleanup() {
  set +e
  if [[ -n "${CANARY_ID}" ]]; then
    mysql_admin "${SOURCE_DB}" --execute="DELETE FROM operational_check_results WHERE public_id='${CANARY_ID}'" >/dev/null 2>&1
  fi
  if [[ "${RESTORE_CREATED}" -eq 1 ]]; then
    mysql_admin --execute="DROP DATABASE IF EXISTS \`${RESTORE_DB}\`" >/dev/null 2>&1
  fi
  if [[ "${KEEP_BACKUP}" -ne 1 ]]; then
    rm -f "${BACKUP_PATH}" "${CHECKSUM_PATH}"
  fi
}
trap cleanup EXIT

CANARY_ID="$(mysql_admin --batch --skip-column-names "${SOURCE_DB}" --execute="SELECT UUID()")"
mysql_admin "${SOURCE_DB}" --execute="
INSERT INTO operational_check_results
(public_id,check_key,check_scope,status,summary,details_json,checked_at,expires_at,created_at)
VALUES
('${CANARY_ID}','${CANARY_KEY}','platform','pass','Backup restore validation canary',JSON_OBJECT('run_id','${RUN_ID}'),NOW(),DATE_ADD(NOW(),INTERVAL 1 DAY),NOW())"

SOURCE_TABLE_COUNT="$(mysql_admin --batch --skip-column-names --execute="SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='${SOURCE_DB}'")"
SOURCE_MIGRATION_COUNT="$(mysql_admin --batch --skip-column-names "${SOURCE_DB}" --execute="SELECT COUNT(*) FROM schema_migrations")"

DUMP_OPTIONS=(
  --protocol=TCP
  --host="${DB_HOST}"
  --port="${DB_PORT}"
  --user="${ADMIN_USER}"
  --default-character-set="${DB_CHARSET}"
  --single-transaction
  --quick
  --routines
  --triggers
  --events
  --hex-blob
  --no-tablespaces
)
if mysqldump --help 2>/dev/null | grep -q -- '--set-gtid-purged'; then
  DUMP_OPTIONS+=(--set-gtid-purged=OFF)
fi

MYSQL_PWD="${ADMIN_PASS}" mysqldump "${DUMP_OPTIONS[@]}" "${SOURCE_DB}" | gzip -n > "${BACKUP_PATH}"
[[ -s "${BACKUP_PATH}" ]] || {
  echo "Database backup is empty." >&2
  exit 1
}
BACKUP_SHA="$(sha256sum "${BACKUP_PATH}" | awk '{print $1}')"
printf '%s  %s\n' "${BACKUP_SHA}" "$(basename "${BACKUP_PATH}")" > "${CHECKSUM_PATH}"
(cd "${OUTPUT_DIR}" && sha256sum --check "$(basename "${CHECKSUM_PATH}")") >/dev/null

after_create_sql="CREATE DATABASE \`${RESTORE_DB}\` CHARACTER SET ${DB_CHARSET} COLLATE utf8mb4_unicode_ci"
mysql_admin --execute="DROP DATABASE IF EXISTS \`${RESTORE_DB}\`; ${after_create_sql};"
RESTORE_CREATED=1

gunzip -c "${BACKUP_PATH}" | mysql_admin "${RESTORE_DB}"

RESTORE_CANARY_COUNT="$(mysql_admin --batch --skip-column-names "${RESTORE_DB}" --execute="SELECT COUNT(*) FROM operational_check_results WHERE public_id='${CANARY_ID}' AND check_key='${CANARY_KEY}'")"
RESTORE_TABLE_COUNT="$(mysql_admin --batch --skip-column-names --execute="SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='${RESTORE_DB}'")"
RESTORE_MIGRATION_COUNT="$(mysql_admin --batch --skip-column-names "${RESTORE_DB}" --execute="SELECT COUNT(*) FROM schema_migrations")"

[[ "${RESTORE_CANARY_COUNT}" == "1" ]] || {
  echo "Restored database is missing the backup canary." >&2
  exit 1
}
[[ "${RESTORE_TABLE_COUNT}" == "${SOURCE_TABLE_COUNT}" ]] || {
  echo "Restored table count does not match the source database." >&2
  exit 1
}
[[ "${RESTORE_MIGRATION_COUNT}" == "${SOURCE_MIGRATION_COUNT}" ]] || {
  echo "Restored migration count does not match the source database." >&2
  exit 1
}

MG_DB_HOST="${DB_HOST}" \
MG_DB_PORT="${DB_PORT}" \
MG_DB_NAME="${RESTORE_DB}" \
MG_DB_USER="${ADMIN_USER}" \
MG_DB_PASS="${ADMIN_PASS}" \
MG_DB_CHARSET="${DB_CHARSET}" \
php "${ROOT}/scripts/validate_migration_manifest.php" >/dev/null

BACKUP_SIZE="$(wc -c < "${BACKUP_PATH}" | tr -d ' ')"
php -r '
$payload = [
    "status" => "passed",
    "run_id" => $argv[1],
    "source_database" => $argv[2],
    "restore_database" => $argv[3],
    "backup_file" => $argv[4],
    "backup_sha256" => $argv[5],
    "backup_size_bytes" => (int) $argv[6],
    "source_table_count" => (int) $argv[7],
    "restore_table_count" => (int) $argv[8],
    "source_migration_count" => (int) $argv[9],
    "restore_migration_count" => (int) $argv[10],
    "canary_verified" => $argv[11] === "1",
    "canonical_migration_manifest_verified" => true,
    "backup_retained" => $argv[12] === "1",
];
file_put_contents($argv[13], json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
' "${RUN_ID}" "${SOURCE_DB}" "${RESTORE_DB}" "$(basename "${BACKUP_PATH}")" "${BACKUP_SHA}" "${BACKUP_SIZE}" "${SOURCE_TABLE_COUNT}" "${RESTORE_TABLE_COUNT}" "${SOURCE_MIGRATION_COUNT}" "${RESTORE_MIGRATION_COUNT}" "${RESTORE_CANARY_COUNT}" "${KEEP_BACKUP}" "${REPORT_PATH}"

echo "Database backup and restore validation passed."
echo "Evidence: ${REPORT_PATH}"
if [[ "${KEEP_BACKUP}" -eq 1 ]]; then
  echo "Backup retained: ${BACKUP_PATH}"
  echo "Checksum retained: ${CHECKSUM_PATH}"
fi
