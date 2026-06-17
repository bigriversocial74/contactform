# Stage 10 Merchant Scope Hardening

## Defect

`mg_claim_execute_operation()` used PHP array union to add trusted merchant and correlation values to request input. Because the left-hand request array wins on duplicate keys, a submitted `merchant_user_id` or `correlation_id` could override the server-authoritative values.

## Repair

The claim operation now copies the request input and explicitly overwrites both trusted fields before invoking the atomic merchant redemption service.

## Regression contract

`Stage10MerchantScopeHardeningTest` verifies that authenticated merchant scope and the generated correlation identifier are assigned explicitly and that the unsafe array-union pattern does not return.

## Scope

No schema, migration, UI, ledger, entitlement, PPPM, or lifecycle changes.
