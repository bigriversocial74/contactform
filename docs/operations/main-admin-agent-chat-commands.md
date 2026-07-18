# Main Admin Agent chat commands

The Phase 1 chat is deterministic and database-first.

| Command | Result |
|---|---|
| `Overview` | Overall health, domain health, findings, and latest scan. |
| `What changed?` | Recent normalized system changes. |
| `Active findings` | Open, acknowledged, and under-review findings. |
| `Security report` | Security-domain events and findings. |
| `Operations report` | Operations, support, notification, and automation findings. |
| `AI credit accounting` | Active AI accounting reconciliation findings. |
| `Migration report` | Canonical migration readiness. |
| `Recent activity` | Recent normalized system events. |
| `Help` | Available commands and safety boundaries. |

No command contacts an external model. No command consumes AI credits. Action requests are stored for review and are not executed by Phase 1.
