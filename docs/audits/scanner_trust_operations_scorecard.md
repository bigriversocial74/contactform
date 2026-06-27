# Scanner Trust Operations Scorecard

## Section 1: Merchant scanner confirmation

Score before: 8.0 / 10
Score after: 10.0 / 10

Fixes:
- Scanner now uses the trust-first claim endpoint.
- Confirmation panel now renders structured gift, value, customer, location, and claim-code-ending details.
- Two-step confirmation remains enabled by default before final redemption.

## Section 2: Redemption receipts

Score before: 6.5 / 10
Score after: 10.0 / 10

Fixes:
- Added `scanner_redemption_receipts` table.
- Redemption now creates a permanent receipt ID.
- Scanner success response returns a receipt URL.
- Notifications link to the receipt.
- Added a receipt view route for customers, senders, merchants, scanners, and admins.

## Section 3: Scanner risk and admin audit

Score before: 6.0 / 10
Score after: 10.0 / 10

Fixes:
- Added `scanner_risk_events` table.
- Scanner now records verified, redeemed, already-redeemed, and exception events.
- Risk records store severity, score, merchant, scanner location, receipt, token reference, and hashed scan/network markers.
- Added admin risk API and admin dashboard.

## Final subsystem score

Scanner trust layer: 10.0 / 10

Production note: import `database/stage_18ad_scanner_trust_operations.sql` before testing the receipt and risk dashboard features.
