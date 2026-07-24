# Investor Closing, Compliance & Post-Investment Relations v4 — Build Summary

## Branch

`feature/investor-closing-compliance-relations-v4-20260724`

## Target

`integration-from-repair-20260628`

## Required SQL

`database/20260724_investor_closing_compliance_relations_v4_single_install.sql`

## Entry points

- `/admin/investment-closing.php`
- `/api/admin/investment-closing.php`
- `/investor-portal.php`
- `/api/investment/portal.php`

## Primary controls

- maker/checker financial verification
- immutable closing-batch completion and audited reopening
- counsel- and provider-supplied compliance/onboarding statuses
- external closing-document references
- administrative capitalization reconciliation
- immutable funded-investor report versions
- investor-visible use-of-funds actuals
- draft-only Claude closing support

## Explicit exclusions

- payment processing
- identity, KYC, AML, beneficial-owner, or accreditation verification
- electronic signing
- filing submission
- securities issuance
- official stock-ledger replacement
- legal, tax, accounting, or investment advice
