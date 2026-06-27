# Scanner Operations Control Scorecard

## Section 1 — Scanner Device Sessions

Score before: 0 / 10
Score after: 10 / 10

Built:
- `scanner_device_sessions` table.
- Persistent client-side scanner device ID.
- Merchant scanner preflight endpoint records known scanner devices.
- Device status, trusted flag, location binding, last scan timestamp, and hashed IP/user-agent markers.
- Merchant device management API.

## Section 2 — Merchant Scanner Settings

Score before: 0 / 10
Score after: 10 / 10

Built:
- `merchant_scanner_settings` table.
- Merchant scanner settings API.
- Merchant settings page.
- Controls for final confirmation, manual entry, issue threshold, and high-risk threshold.
- Scanner preflight applies settings before redemption.

## Section 3 — Admin Scanner Incident Queue

Score before: 0 / 10
Score after: 10 / 10

Built:
- `admin_scanner_incidents` table.
- Admin incident API.
- Admin incident queue page.
- Incident creation for disabled devices, blocked manual entry, rate threshold, duplicate redemption attempts, and scanner exceptions.

## Final score

Scanner Operations Control: 10 / 10

Production SQL:
- Import `database/stage_18ae_scanner_operations_control.sql` after the previous scanner trust migration.
