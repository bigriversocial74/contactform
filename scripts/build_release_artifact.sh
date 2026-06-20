#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REF="HEAD"
VERSION=""
OUTPUT_DIR="${ROOT}/build/releases"

usage() {
  cat <<'EOF'
Usage: bash scripts/build_release_artifact.sh [options]

Options:
  --ref <git-ref>          Git ref or commit to package. Default: HEAD
  --version <label>        Release version label. Default: v1-<short-sha>
  --output-dir <path>      Destination directory. Default: build/releases
  --help                   Show this help text.
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --ref)
      REF="${2:?Missing value for --ref}"
      shift 2
      ;;
    --version)
      VERSION="${2:?Missing value for --version}"
      shift 2
      ;;
    --output-dir)
      OUTPUT_DIR="${2:?Missing value for --output-dir}"
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

for command in git php composer tar gzip sha256sum find sort touch; do
  command -v "${command}" >/dev/null 2>&1 || {
    echo "Required command is unavailable: ${command}" >&2
    exit 1
  }
done

cd "${ROOT}"
COMMIT_SHA="$(git rev-parse "${REF}^{commit}")"
SHORT_SHA="$(git rev-parse --short=12 "${COMMIT_SHA}")"
COMMIT_EPOCH="$(git show -s --format=%ct "${COMMIT_SHA}")"
COMMIT_ISO="$(git show -s --format=%cI "${COMMIT_SHA}")"

if [[ -z "${VERSION}" ]]; then
  VERSION="v1-${SHORT_SHA}"
fi
SAFE_VERSION="$(printf '%s' "${VERSION}" | tr -cs 'A-Za-z0-9._-' '-')"
SAFE_VERSION="${SAFE_VERSION#-}"
SAFE_VERSION="${SAFE_VERSION%-}"
[[ -n "${SAFE_VERSION}" ]] || {
  echo "Release version does not contain a usable artifact name." >&2
  exit 1
}

OUTPUT_DIR="$(mkdir -p "${OUTPUT_DIR}" && cd "${OUTPUT_DIR}" && pwd)"
ARTIFACT_BASENAME="microgifter-${SAFE_VERSION}-${SHORT_SHA}"
ARTIFACT_PATH="${OUTPUT_DIR}/${ARTIFACT_BASENAME}.tar.gz"
CHECKSUM_PATH="${ARTIFACT_PATH}.sha256"
MANIFEST_PATH="${OUTPUT_DIR}/${ARTIFACT_BASENAME}.manifest.json"

TMP_ROOT="$(mktemp -d)"
STAGE_DIR="${TMP_ROOT}/release"
ARCHIVE_TAR="${TMP_ROOT}/source.tar"
cleanup() {
  rm -rf "${TMP_ROOT}"
}
trap cleanup EXIT
mkdir -p "${STAGE_DIR}"

git archive --format=tar --output="${ARCHIVE_TAR}" "${COMMIT_SHA}"
tar -xf "${ARCHIVE_TAR}" -C "${STAGE_DIR}"

rm -rf \
  "${STAGE_DIR}/.github" \
  "${STAGE_DIR}/build" \
  "${STAGE_DIR}/docs" \
  "${STAGE_DIR}/microgifter-main" \
  "${STAGE_DIR}/node_modules" \
  "${STAGE_DIR}/tests"
rm -f \
  "${STAGE_DIR}/.gitattributes" \
  "${STAGE_DIR}/.gitignore" \
  "${STAGE_DIR}/docker-compose.yml" \
  "${STAGE_DIR}/package.json" \
  "${STAGE_DIR}/package-lock.json" \
  "${STAGE_DIR}/phpunit.xml.dist" \
  "${STAGE_DIR}/playwright.config.js"

if [[ -e "${STAGE_DIR}/.env" || -e "${STAGE_DIR}/api/config.local.php" ]]; then
  echo "Server-local secret configuration was included in the source archive." >&2
  exit 1
fi

composer install \
  --working-dir="${STAGE_DIR}" \
  --no-dev \
  --no-interaction \
  --no-progress \
  --prefer-dist \
  --optimize-autoloader

MIGRATION_SHA="$(sha256sum "${STAGE_DIR}/config/migrations.php" | awk '{print $1}')"
FILE_COUNT="$(( $(find "${STAGE_DIR}" -type f | wc -l | tr -d ' ') + 1 ))"

php -r '
$payload = [
    "release_version" => $argv[1],
    "git_commit_sha" => $argv[2],
    "git_commit_time" => $argv[3],
    "runtime_profile" => "hostgator",
    "migration_manifest_sha256" => $argv[4],
    "file_count" => (int) $argv[5],
    "artifact_format" => "tar.gz",
    "source_of_truth" => "bigriversocial74/contactform repository root",
];
file_put_contents($argv[6], json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
' "${VERSION}" "${COMMIT_SHA}" "${COMMIT_ISO}" "${MIGRATION_SHA}" "${FILE_COUNT}" "${STAGE_DIR}/RELEASE.json"

while IFS= read -r -d '' path; do
  touch -h -d "@${COMMIT_EPOCH}" "${path}"
done < <(find "${STAGE_DIR}" -print0)

rm -f "${ARTIFACT_PATH}" "${CHECKSUM_PATH}" "${MANIFEST_PATH}"
tar \
  --sort=name \
  --owner=0 \
  --group=0 \
  --numeric-owner \
  --mtime="@${COMMIT_EPOCH}" \
  -cf - \
  -C "${STAGE_DIR}" . \
  | gzip -n > "${ARTIFACT_PATH}"

ARTIFACT_SHA="$(sha256sum "${ARTIFACT_PATH}" | awk '{print $1}')"
ARTIFACT_SIZE="$(wc -c < "${ARTIFACT_PATH}" | tr -d ' ')"
printf '%s  %s\n' "${ARTIFACT_SHA}" "$(basename "${ARTIFACT_PATH}")" > "${CHECKSUM_PATH}"

php -r '
$payload = [
    "release_version" => $argv[1],
    "git_commit_sha" => $argv[2],
    "git_commit_time" => $argv[3],
    "artifact_file" => $argv[4],
    "artifact_sha256" => $argv[5],
    "artifact_size_bytes" => (int) $argv[6],
    "migration_manifest_sha256" => $argv[7],
    "file_count" => (int) $argv[8],
    "excluded" => [
        ".github", "build", "docs", "microgifter-main", "node_modules", "tests",
        ".env", "api/config.local.php", "docker-compose.yml", "package.json",
        "package-lock.json", "phpunit.xml.dist", "playwright.config.js"
    ],
];
file_put_contents($argv[9], json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
' "${VERSION}" "${COMMIT_SHA}" "${COMMIT_ISO}" "$(basename "${ARTIFACT_PATH}")" "${ARTIFACT_SHA}" "${ARTIFACT_SIZE}" "${MIGRATION_SHA}" "${FILE_COUNT}" "${MANIFEST_PATH}"

LISTING="${TMP_ROOT}/listing.txt"
tar -tzf "${ARTIFACT_PATH}" > "${LISTING}"
for required in \
  './RELEASE.json' \
  './index.php' \
  './api/health.php' \
  './config/migrations.php' \
  './scripts/run_migrations.php' \
  './vendor/autoload.php'; do
  grep -Fxq "${required}" "${LISTING}" || {
    echo "Release artifact is missing required path: ${required}" >&2
    exit 1
  }
done

if grep -Eq '(^|/)(\.env|config\.local\.php)$|^\./(\.github|build|docs|microgifter-main|node_modules|tests)/' "${LISTING}"; then
  echo "Release artifact contains a forbidden development or secret path." >&2
  exit 1
fi

(cd "${OUTPUT_DIR}" && sha256sum --check "$(basename "${CHECKSUM_PATH}")") >/dev/null

echo "Release artifact created: ${ARTIFACT_PATH}"
echo "Checksum: ${CHECKSUM_PATH}"
echo "Manifest: ${MANIFEST_PATH}"
