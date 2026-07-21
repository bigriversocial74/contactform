#!/usr/bin/env bash
set -Eeuo pipefail

DOMAIN="mcp.microgifter.com"
APP_ROOT="/opt/microgifter-mcp"
ENV_FILE="/etc/microgifter/mcp.env"
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_ROOT="$(cd -- "${SCRIPT_DIR}/../../.." && pwd)"
INSTALL_NGINX=1

usage() {
  cat <<USAGE
Usage: sudo $0 [--domain mcp.example.com] [--source /path/to/contactform] [--app-root /opt/microgifter-mcp] [--env-file /etc/microgifter/mcp.env] [--skip-nginx]
USAGE
}

while (($#)); do
  case "$1" in
    --domain) DOMAIN="${2:?Missing domain}"; shift 2 ;;
    --source) SOURCE_ROOT="$(cd -- "${2:?Missing source path}" && pwd)"; shift 2 ;;
    --app-root) APP_ROOT="${2:?Missing app root}"; shift 2 ;;
    --env-file) ENV_FILE="${2:?Missing environment file}"; shift 2 ;;
    --skip-nginx) INSTALL_NGINX=0; shift ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown option: $1" >&2; usage >&2; exit 64 ;;
  esac
done

if [[ ${EUID} -ne 0 ]]; then
  echo "Run this installer as root." >&2
  exit 77
fi

for command_name in node npm systemctl curl tar install; do
  command -v "$command_name" >/dev/null 2>&1 || { echo "Missing required command: $command_name" >&2; exit 69; }
done

NODE_BIN="$(command -v node)"
NODE_MAJOR="$($NODE_BIN -p 'Number(process.versions.node.split(".")[0])')"
if [[ "$NODE_MAJOR" -lt 20 ]]; then
  echo "Node.js 20 or newer is required; found $($NODE_BIN --version)." >&2
  exit 69
fi

if [[ ! -f "$SOURCE_ROOT/services/mcp/package-lock.json" || ! -f "$SOURCE_ROOT/services/mcp/src/server.ts" ]]; then
  echo "The source path does not contain the Microgifter MCP service." >&2
  exit 66
fi

if ! id microgifter-mcp >/dev/null 2>&1; then
  useradd --system --home-dir "$APP_ROOT" --shell /usr/sbin/nologin microgifter-mcp
fi

install -d -m 0755 "$APP_ROOT/releases" "$APP_ROOT"
install -d -m 0700 "$(dirname -- "$ENV_FILE")"
RELEASE_ID="$(date -u +%Y%m%dT%H%M%SZ)-$(git -C "$SOURCE_ROOT" rev-parse --short=12 HEAD 2>/dev/null || echo package)"
RELEASE_DIR="$APP_ROOT/releases/$RELEASE_ID"
install -d -m 0755 "$RELEASE_DIR"

tar -C "$SOURCE_ROOT/services/mcp" \
  --exclude='./node_modules' --exclude='./dist' --exclude='./.env' --exclude='./.env.*' \
  -cf - . | tar -C "$RELEASE_DIR" -xf -

(
  cd "$RELEASE_DIR"
  npm ci --ignore-scripts
  npm run check
  npm prune --omit=dev
)

chown -R root:root "$RELEASE_DIR"
find "$RELEASE_DIR" -type d -exec chmod 0755 {} +
find "$RELEASE_DIR" -type f -exec chmod 0644 {} +

PREVIOUS_TARGET="$(readlink -f "$APP_ROOT/current" 2>/dev/null || true)"
TEMP_LINK="$APP_ROOT/.current-$RELEASE_ID"
ln -s "$RELEASE_DIR" "$TEMP_LINK"
mv -Tf "$TEMP_LINK" "$APP_ROOT/current"

UNIT_SOURCE="$SOURCE_ROOT/deploy/vps/systemd/microgifter-mcp.service"
UNIT_TARGET="/etc/systemd/system/microgifter-mcp.service"
sed "s#ExecStart=/usr/bin/node #ExecStart=${NODE_BIN} #" "$UNIT_SOURCE" > "$UNIT_TARGET"
chmod 0644 "$UNIT_TARGET"
systemctl daemon-reload
systemctl enable microgifter-mcp.service >/dev/null

ENV_CREATED=0
if [[ ! -f "$ENV_FILE" ]]; then
  install -m 0600 "$SOURCE_ROOT/deploy/vps/mcp.env.example" "$ENV_FILE"
  ENV_CREATED=1
else
  chmod 0600 "$ENV_FILE"
fi

if [[ "$INSTALL_NGINX" -eq 1 ]]; then
  if ! command -v nginx >/dev/null 2>&1; then
    echo "Nginx is not installed; skipping reverse-proxy installation." >&2
  elif [[ -r "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" && -r "/etc/letsencrypt/live/$DOMAIN/privkey.pem" ]]; then
    install -d -m 0755 /etc/nginx/sites-available /etc/nginx/sites-enabled /var/www/letsencrypt
    sed "s/__MCP_DOMAIN__/$DOMAIN/g" "$SOURCE_ROOT/deploy/vps/nginx/mcp.microgifter.com.conf.template" \
      > "/etc/nginx/sites-available/microgifter-mcp.conf"
    ln -sfn /etc/nginx/sites-available/microgifter-mcp.conf /etc/nginx/sites-enabled/microgifter-mcp.conf
    nginx -t
    systemctl reload nginx
  else
    install -d -m 0755 /etc/nginx/sites-available /etc/nginx/sites-enabled /var/www/letsencrypt
    sed "s/__MCP_DOMAIN__/$DOMAIN/g" "$SOURCE_ROOT/deploy/vps/nginx/mcp-bootstrap.conf.template" \
      > "/etc/nginx/sites-available/microgifter-mcp.conf"
    ln -sfn /etc/nginx/sites-available/microgifter-mcp.conf /etc/nginx/sites-enabled/microgifter-mcp.conf
    nginx -t
    systemctl reload nginx
    echo "TLS bootstrap is active. Run: certbot certonly --webroot -w /var/www/letsencrypt -d $DOMAIN" >&2
    echo "Then rerun this installer to enable the final HTTPS reverse proxy." >&2
  fi
fi

if [[ "$ENV_CREATED" -eq 1 ]] || grep -q 'REPLACE_WITH_' "$ENV_FILE"; then
  echo "MCP code installed at $RELEASE_DIR. Complete $ENV_FILE, then run deploy/vps/scripts/activate-systemd.sh." >&2
  exit 2
fi

set -a
# shellcheck disable=SC1090
source "$ENV_FILE"
set +a
"$NODE_BIN" "$APP_ROOT/current/dist/cli/validateEnvironment.js"

if ! systemctl restart microgifter-mcp.service; then
  if [[ -n "$PREVIOUS_TARGET" && -d "$PREVIOUS_TARGET" ]]; then
    ln -sfn "$PREVIOUS_TARGET" "$APP_ROOT/current"
    systemctl restart microgifter-mcp.service || true
  fi
  echo "MCP service failed to restart; previous release restored when available." >&2
  exit 1
fi

READY=0
for _ in {1..30}; do
  if curl --fail --silent --show-error http://127.0.0.1:8787/ready >/dev/null; then
    READY=1
    break
  fi
  sleep 1
done
if [[ "$READY" -ne 1 ]]; then
  journalctl -u microgifter-mcp.service -n 80 --no-pager >&2 || true
  if [[ -n "$PREVIOUS_TARGET" && -d "$PREVIOUS_TARGET" ]]; then
    ln -sfn "$PREVIOUS_TARGET" "$APP_ROOT/current"
    systemctl restart microgifter-mcp.service || true
  fi
  echo "Readiness failed; previous release restored when available." >&2
  exit 1
fi

mapfile -t OLD_RELEASES < <(find "$APP_ROOT/releases" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' | sort -nr | tail -n +6 | cut -d' ' -f2-)
for old_release in "${OLD_RELEASES[@]:-}"; do
  [[ -n "$old_release" && "$old_release" != "$(readlink -f "$APP_ROOT/current")" ]] && rm -rf -- "$old_release"
done

echo "Microgifter MCP release $RELEASE_ID is active on 127.0.0.1:8787."
