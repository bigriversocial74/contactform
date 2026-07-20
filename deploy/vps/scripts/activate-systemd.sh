#!/usr/bin/env bash
set -Eeuo pipefail

APP_ROOT="${MICROGIFTER_MCP_APP_ROOT:-/opt/microgifter-mcp}"
ENV_FILE="${MICROGIFTER_MCP_ENV_FILE:-/etc/microgifter/mcp.env}"
NODE_BIN="$(command -v node)"

if [[ ${EUID} -ne 0 ]]; then
  echo "Run this activation script as root." >&2
  exit 77
fi
if [[ ! -r "$ENV_FILE" ]]; then
  echo "Missing environment file: $ENV_FILE" >&2
  exit 66
fi
if grep -q 'REPLACE_WITH_' "$ENV_FILE"; then
  echo "The environment file still contains replacement placeholders." >&2
  exit 78
fi

chmod 0600 "$ENV_FILE"
set -a
# shellcheck disable=SC1090
source "$ENV_FILE"
set +a
"$NODE_BIN" "$APP_ROOT/current/dist/cli/validateEnvironment.js"
systemctl daemon-reload
systemctl enable --now microgifter-mcp.service
systemctl restart microgifter-mcp.service

for _ in {1..30}; do
  if curl --fail --silent --show-error http://127.0.0.1:8787/ready >/dev/null; then
    systemctl --no-pager --full status microgifter-mcp.service
    echo "Microgifter MCP is ready on 127.0.0.1:8787."
    exit 0
  fi
  sleep 1
done

journalctl -u microgifter-mcp.service -n 100 --no-pager >&2 || true
echo "Microgifter MCP did not become ready." >&2
exit 1
