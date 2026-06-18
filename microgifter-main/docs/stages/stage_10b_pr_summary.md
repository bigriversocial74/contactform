# Stage 10B PR Summary

This stage adds canonical merchant-location claim authority and an immutable Microgift claim-attempt ledger.

## Included

- location-scoped merchant staff assignments;
- canonical location and merchant claim-code references on Microgift claim/redemption records;
- normalized success and failure result catalog;
- reusable authority service with row locks, merchant ownership checks, active-location checks, actor authorization, constant-time hash comparison, validity windows, and usage limits;
- explicit approval-only usage increment hook;
- append-only attempt recording with fingerprint fields and no submitted credential storage;
- PHPUnit contract coverage and CLI smoke artifact validation.

## Deferred

Stage 10C will integrate these contracts into the final atomic claim/redemption, PPPM, inbox, and event transaction.
