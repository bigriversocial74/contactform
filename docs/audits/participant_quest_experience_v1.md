# Participant Quest Experience v1

## Initial audit score: 4.8/10

The initial branch contained a useful participation skeleton, but it was not production-complete. The audit identified these gaps:

- invite-only and segmented audiences were not enforced
- verification values did not consistently match the campaign builder
- signed QR payloads did not validate campaign identity or expiration
- signed codes had no merchant issuance path
- static, staff, event, and invite codes were not stored as one-way hashes
- location verification lacked a configurable accuracy ceiling
- duplicate external references, daily limits, cooldowns, and campaign budgets were incomplete
- merchant evidence review lacked a working UI and rejection guidance
- participant review notes and historical quest states were not surfaced
- the shared identity and participation migrations were absent from the canonical upgrade manifest
- participant and merchant account navigation did not expose the new experiences

## Completion gate

The section is accepted only when PHP 8.2 and 8.3 syntax, the Participant Quest Experience contract, canonical migration coverage, App Layout, Loyalty Quest Campaign Type, Merchant Quest Management, Public Marketplace, Stage 12 Campaigns, and relevant repository regressions pass.
