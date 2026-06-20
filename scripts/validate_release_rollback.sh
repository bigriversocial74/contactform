#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CURRENT_ARTIFACT=""
ROLLBACK_ARTIFACT=""
OUTPUT_PATH="${ROOT}/build/release-evidence/rollback-validation.json"

usage() {
  cat <<'EOF'
Usage: bash scripts/validate_release_rollback.sh [options]

Options:
  --current <artifact.tar.gz>   Candidate release artifact.
  --rollback <artifact.tar.gz>  Previously approved rollback artifact.
  --output <report.json>        Evidence report destination.
  --help                        Show this help text.
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --current)
      CURRENT_ARTIFACT="${2:?Missing value for --current}"
      shift 2
      ;;
    --rollback)
      ROLLBACK_ARTIFACT="${2:?Missing value for --rollback}"
      shift 2
      ;;
    --output)
      OUTPUT_PATH="${2:?Missing value for --output}"
      shift 2
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

[[ -f "${CURRENT_ARTIFACT}" && -f "${ROLLBACK_ARTIFACT}" ]] || {
  echo "Both current and rollback artifacts are required." >&2
  exit 1
}

for command in php tar gzip sha256sum find xargs grep; do
  command -v "${command}" >/dev/null 2>&1 || {
    echo "Required command is unavailable: ${command}" >&2
    exit 1
  }
done

verify_checksum() {
  local artifact="$1"
  local checksum="${artifact}.sha256"
  [[ -f "${checksum}" ]] || {
    echo "Missing checksum sidecar: ${checksum}" >&2
    return 1
  }
  (cd "$(dirname "${artifact}")" && sha256sum --check "$(basename "${checksum}")") >/dev/null
}

safe_listing() {
  local artifact="$1"
  local listing="$2"
  tar -tzf "${artifact}" > "${listing}"
  if grep -Eq '(^/)|(^|/)\.\.(/|$)' "${listing}"; then
    echo "Archive contains an unsafe path: ${artifact}" >&2
    return 1
  fi
}

required_paths() {
  local listing="$1"
  for required in \
    './RELEASE.json' \
    './index.php' \
    './api/health.php' \
    './config/migrations.php' \
    './scripts/run_migrations.php' \
    './vendor/autoload.php'; do
    grep -Fxq "${required}" "${listing}" || {
      echo "Artifact is missing required path ${required}." >&2
      return 1
    }
  done
  if grep -Eq '(^|/)(\.env|config\.local\.php)$|^\./(\.github|build|docs|microgifter-main|node_modules|tests)/' "${listing}"; then
    echo "Artifact contains a forbidden development or secret path." >&2
    return 1
  fi
}

verify_checksum "${CURRENT_ARTIFACT}"
verify_checksum "${ROLLBACK_ARTIFACT}"

TMP_ROOT="$(mktemp -d)"
trap 'rm -rf "${TMP_ROOT}"' EXIT
CURRENT_DIR="${TMP_ROOT}/current"
ROLLBACK_DIR="${TMP_ROOT}/rollback"
mkdir -p "${CURRENT_DIR}" "${ROLLBACK_DIR}" "$(dirname "${OUTPUT_PATH}")"

safe_listing "${CURRENT_ARTIFACT}" "${TMP_ROOT}/current.list"
safe_listing "${ROLLBACK_ARTIFACT}" "${TMP_ROOT}/rollback.list"
required_paths "${TMP_ROOT}/current.list"
required_paths "${TMP_ROOT}/rollback.list"

tar -xzf "${CURRENT_ARTIFACT}" -C "${CURRENT_DIR}"
tar -xzf "${ROLLBACK_ARTIFACT}" -C "${ROLLBACK_DIR}"

CURRENT_SHA="$(php -r '$data=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); echo $data["git_commit_sha"]??"";' "${CURRENT_DIR}/RELEASE.json")"
ROLLBACK_SHA="$(php -r '$data=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); echo $data["git_commit_sha"]??"";' "${ROLLBACK_DIR}/RELEASE.json")"
CURRENT_VERSION="$(php -r '$data=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); echo $data["release_version"]??"";' "${CURRENT_DIR}/RELEASE.json")"
ROLLBACK_VERSION="$(php -r '$data=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); echo $data["release_version"]??"";' "${ROLLBACK_DIR}/RELEASE.json")"

[[ "${CURRENT_SHA}" =~ ^[a-f0-9]{40}$ && "${ROLLBACK_SHA}" =~ ^[a-f0-9]{40}$ ]] || {
  echo "Release metadata does not contain valid commit SHAs." >&2
  exit 1
}
[[ "${CURRENT_SHA}" != "${ROLLBACK_SHA}" ]] || {
  echo "Rollback artifact must reference a different commit." >&2
  exit 1
}

find "${CURRENT_DIR}" -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
find "${ROLLBACK_DIR}" -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null

CURRENT_ARTIFACT_SHA="$(sha256sum "${CURRENT_ARTIFACT}" | awk '{print $1}')"
ROLLBACK_ARTIFACT_SHA="$(sha256sum "${ROLLBACK_ARTIFACT}" | awk '{print $1}')"
CURRENT_MIGRATION_SHA="$(sha256sum "${CURRENT_DIR}/config/migrations.php" | awk '{print $1}')"
ROLLBACK_MIGRATION_SHA="$(sha256sum "${ROLLBACK_DIR}/config/migrations.php" | awk '{print $1}')"
CURRENT_FILES="$(find "${CURRENT_DIR}" -type f | wc -l | tr -d ' ')"
ROLLBACK_FILES="$(find "${ROLLBACK_DIR}" -type f | wc -l | tr -d ' ')"

php -r '
$payload = [
    "status" => "passed",
    "current" => [
        "release_version" => $argv[1],
        "git_commit_sha" => $argv[2],
        "artifact_file" => $argv[3],
        "artifact_sha256" => $argv[4],
        "migration_manifest_sha256" => $argv[5],
        "file_count" => (int) $argv[6],
        "php_syntax_verified" => true,
    ],
    "rollback" => [
        "release_version" => $argv[7],
        "git_commit_sha" => $argv[8],
        "artifact_file" => $argv[9],
        "artifact_sha256" => $argv[10],
        "migration_manifest_sha256" => $argv[11],
        "file_count" => (int) $argv[12],
        "php_syntax_verified" => true,
    ],
    "rollback_method" => [
        "restore_predeployment_database_backup",
        "restore_predeployment_persistent_media_snapshot_if_media_changed",
        "replace_candidate_code_with_verified_rollback_artifact",
        "run_media_storage_check",
        "run_health_and_readiness_checks",
    ],
    "database_downgrade_policy" => "Do not run reverse migrations. Restore the matching predeployment database backup.",
];
file_put_contents($argv[13], json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
' "${CURRENT_VERSION}" "${CURRENT_SHA}" "$(basename "${CURRENT_ARTIFACT}")" "${CURRENT_ARTIFACT_SHA}" "${CURRENT_MIGRATION_SHA}" "${CURRENT_FILES}" "${ROLLBACK_VERSION}" "${ROLLBACK_SHA}" "$(basename "${ROLLBACK_ARTIFACT}")" "${ROLLBACK_ARTIFACT_SHA}" "${ROLLBACK_MIGRATION_SHA}" "${ROLLBACK_FILES}" "${OUTPUT_PATH}"

echo "Release rollback package validation passed."
echo "Evidence: ${OUTPUT_PATH}"
