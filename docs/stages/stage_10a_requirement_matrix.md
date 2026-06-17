# Stage 10A Requirement Matrix

| Official Stage 10 requirement | Current state | Classification | Stage 10 action |
|---|---|---|---|
| Redeem voucher lookup | Microgift instance and credential lookup exist, but no official redeem-view endpoint contract | Partial | Add safe claim-session/redeem lookup API |
| Merchant claim verification | Generic redemption exists | Partial | Add canonical merchant/location authority service |
| Location claim-code hash validation | Stage 3 hashed codes exist | Missing integration | Validate active code hash inside redemption transaction |
| Claim attempts logged, success and failure | Credential failures create events; successful claims/redemptions create records | Partial | Add immutable claim-attempt ledger for every submission |
| Gift claim record | `microgift_claims` exists | Built early | Reuse; add Stage 10 canonical location context where required |
| Gift redemption record | `microgift_redemptions` exists | Built early | Reuse; replace string-only location with canonical relation |
| Received/claimable to claimed | Current claim goes to `redeemable`, redemption goes to `redeemed` | Misaligned terminology | Preserve canonical model and add compatibility mapping/events |
| Inbox Received to Claimed | No proven atomic side effect | Missing | Add outbox/transactional inbox movement |
| Location claim history | Merchant operation list exists, location is string reference | Partial | Add location-scoped list/detail APIs using canonical IDs |
| Claim audit logs | Microgift events/admin timeline exist | Partial | Add attempt/audit records with normalized results |
| Fraud signals | Credential attempt counter and lockout exist | Partial | Add location/gift/actor/IP velocity controls and review escalation |
| Post-claim tip flag | Not present | Missing | Add non-financial eligibility flag/read-model field only |
| PSR/Future Demand redeemed event | Microgift and PPPM redemption events exist | Partial | Add compatibility source event; no scoring |
| Paid-order requirement | Issuance checks commerce foundation | Mostly complete | Revalidate source order/item before merchant claim if needed |
| Gift not expired/refunded/revoked | Lifecycle guards exist | Mostly complete | Add explicit Stage 10 reason codes and tests |
| Merchant match | Caller supplies merchant ID | Missing canonical proof | Resolve merchant from location and instance/template policy |
| Location active | Location table has active/inactive/archived | Missing integration | Lock and verify location row |
| Location allowed | JSON location policy exists | Partial | Evaluate against canonical location public ID/DB ID |
| Authorized merchant staff | Permissions exist; no location assignment proof in redemption | Missing | Add owner/admin/location staff authorization service |
| Transaction and row lock | Instance row lock and endpoint transactions exist | Built early | Extend transaction to attempt, location code, inbox, events |
| Concurrent claim prevention | Unique keys and row locking exist | Mostly complete | Add behavioral concurrency tests |
| No plaintext credentials | Hash-only Microgift and merchant codes | Complete | Preserve and add event/log scanning tests |
| Merchant claim APIs | Merchant Microgift operations API exists | Partial | Add claim list/detail/location history contracts |
| Prior-stage regression | Consolidated CI exists | Complete foundation | Keep all Stage 1–9 tests green |

## Summary

- Complete/built early: 6
- Mostly complete: 3
- Partial: 8
- Missing integration: 6
- Misaligned: 1

The principal Stage 10 gap is not basic claiming or redemption. It is authoritative merchant-location validation, complete attempt logging, atomic inbox/event side effects, and merchant operational contracts.
