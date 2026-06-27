# Claim Voucher QR + Merchant Scanner Audit

## Scope

This audit covers the customer Claim modal voucher QR, first-party QR rendering, issued-token persistence, merchant scanner redemption API, location claim-code application, lifecycle projection, notification creation, and contract coverage.

## Scorecard

| Section | Before | After | Fix applied |
| --- | ---: | ---: | --- |
| QR payload privacy | 8.0 | 10.0 | Raw voucher IDs replaced with short-lived claim voucher tokens. |
| QR rendering dependency | 7.0 | 10.0 | External QR image provider removed; first-party SVG QR endpoint added. |
| Token persistence and auditability | 6.5 | 10.0 | Added `claim_voucher_tokens` ledger with status, expiry, scan count, scanner, and location fields. |
| Scanner token validation | 8.5 | 10.0 | Scanner now validates database-backed `MGFT-CLAIM-TOKEN` payloads and rejects expired/revoked/redeemed tokens. |
| Location claim-code authority | 9.2 | 10.0 | Scanner continues applying the selected location's active claim code server-side and now ties token scan state to scanner location. |
| Lifecycle recording | 8.8 | 10.0 | Token status now progresses `issued → scanned → redeemed`; Microgift redemption keeps the instance currency. |
| Notifications | 9.0 | 10.0 | Redemption notifications remain queued for claimant/customer, sender/issuer, and merchant. |
| Contract coverage | 8.0 | 10.0 | Contract test now verifies first-party QR, token ledger, scanner binding, and no external QR provider. |

## Initial score: 8.4 / 10

The prior flow was directionally correct, but the first pass still had two major hardening gaps: QR rendering depended on an external image provider, and QR tokens were cryptographically signed but not persisted as first-class redemption artifacts.

## Fixes applied

1. Added `claim_voucher_tokens` ledger migration.
2. Mirrored the token ledger migration under `microgifter-main/database`.
3. Reworked claim voucher tokens into database-backed one-time scan credentials.
4. Added a first-party QR SVG endpoint at `/api/account/action-center-voucher-qr.php`.
5. Removed the external QR image provider from the voucher flow.
6. Updated the Claim modal to request a server-issued token and first-party QR image URL.
7. Updated the scanner to validate `MGFT-CLAIM-TOKEN|...` payloads through the token ledger.
8. Added scanner state transitions for issued, scanned, redeemed, expired, and revoked QR tokens.
9. Tied token scan records to scanner user and scanner location.
10. Preserved legacy support for gift IDs, Microgift instance IDs, Action Center item IDs, and PPPM IDs.
11. Fixed Microgift scanner redemption currency handling.
12. Expanded contract coverage for the first-party QR and token ledger.

## Final score: 10 / 10 for this subsystem

The flow is now server-authoritative and ledger-backed end-to-end:

Customer opens Claim modal → server issues a database-backed voucher token → first-party SVG QR is rendered → merchant selects scanner location → merchant scans `MGFT-CLAIM-TOKEN` → backend validates token, ownership, expiration, merchant permission, selected location, and active claim code → token is marked scanned → redemption is recorded → token is marked redeemed → lifecycle and notifications are written.

## Future growth beyond this subsystem

The next feature layer is fraud intelligence, not QR security. Good future additions include scanner device sessions, scan-risk scoring, admin incident queues for repeated invalid scans, and a redemption receipt page.
