# Investor Closing, Compliance & Post-Investment Relations v4

Phase 4 extends the existing Investor Wizard, Pipeline, Diligence, and Investor Portal authorities with controlled closing operations and funded-investor reporting.

## Required installation order

1. `database/20260723_investor_role_investment_wizard_v1_single_install.sql`
2. `database/20260723_investor_pipeline_portal_publishing_v2_single_install.sql`
3. `database/20260723_investor_diligence_communications_v3_single_install.sql`
4. `database/20260724_investor_closing_compliance_relations_v4_single_install.sql`

Phase 4 is additive. It does not drop or truncate Phase 1–3 records.

## Main pages

- `/admin/investment-closing.php`
- `/admin/investor-pipeline.php`
- `/admin/investor-diligence.php`
- `/investor-portal.php`

## Closing Command Center

The Closing Command Center contains:

- round closing profiles and deterministic readiness
- investor closing records
- rolling and final closing batches
- externally supplied onboarding-review statuses
- counsel-supplied compliance and filing tracking
- externally executed closing-document packets
- maker/checker signed and funded verification
- capitalization reconciliation snapshots
- post-investment reporting periods and immutable report versions
- investor-visible use-of-funds actuals
- internal Claude closing drafts

## Financial verification authority

Signed and verified-funded amounts are not directly editable through the closing-record editor.

The workflow is:

1. An administrator submits a financial-verification request with an evidence reference and reason.
2. A different authorized administrator approves or rejects it.
3. Approval updates the closing record and canonical `investor_round_interests` record.
4. Official `investment_rounds` totals are recalculated from canonical active investor-round records.
5. The decision and amount change are retained in immutable event and audit history.

The submitting administrator cannot decide their own request.

Investor Portal submissions never alter signed or funded totals.

## Closing batches

Closing batches support planning, review, readiness, rolling closes, and final closes.

A batch cannot be completed unless:

- counsel status is approved
- board status is approved
- at least one investor is included
- every included investor has verified funded money
- every existing closing packet for an included investor is complete

Completing a batch locks it and records its actual closing date. Reopening is restricted to Super Admin and requires an audited reason.

## Compliance and onboarding

The module records statuses supplied by counsel, accountants, banks, identity providers, accreditation providers, or other authorized external parties.

It does not:

- perform identity verification
- perform KYC or AML review
- verify accreditation
- determine exemption eligibility
- submit Form D or state notices
- provide legal or tax advice

## Closing documents

The module stores governed metadata and approved external URLs. It does not implement electronic signing or place unrestricted uploads in public paths.

Tracked document types include questionnaires, subscription agreements, SAFEs or notes, accreditation evidence, tax forms, side letters, board consents, countersigned agreements, funding confirmations, closing certificates, Form D receipts, and state-notice receipts.

## Capitalization reconciliation

Reconciliation snapshots compare official round targets with approved signed and verified-funded amounts. Dilution outputs are administrative estimates only.

They do not replace:

- the corporate stock ledger
- counsel-approved capitalization records
- accountant records
- dedicated cap-table software

## Post-investment relations

A funded investor receives the Investment Relations portal section only after an authorized maker/checker decision records verified funded money.

The portal can display:

- closing status and verified funded amount
- published agreement and funding references
- approved closing documents
- immutable published investor reports
- investor-visible use-of-funds actuals
- the latest capitalization reconciliation summary

Internal compliance notes, verification evidence, onboarding restrictions, and draft reports are not exposed.

## Claude Closing Assistant

Claude creates internal editable drafts for:

- closing-readiness reviews
- missing-document summaries
- investor closing briefings
- compliance-deadline summaries
- closing instructions and announcements
- post-close investor updates
- scenario-versus-actual explanations
- use-of-funds variance summaries

Claude cannot verify money, approve documents, submit filings, sign agreements, process payments, issue securities, or change the official stock ledger.

## Environment

Claude actions require the existing Anthropic configuration, normally `MG_ANTHROPIC_API_KEY`. Deterministic closing, verification, compliance, reconciliation, and reporting functions do not require Claude.

## Deployment

1. Merge the Phase 4 pull request into `integration-from-repair-20260628`.
2. Import only the Phase 4 SQL if Phase 1–3 are already installed.
3. Deploy the latest integration ZIP.
4. Open `/admin/investment-closing.php`.
5. Select an official round and run **Sync closing records**.
6. Seed the standard compliance checklist.
7. Recalculate readiness.
8. Create a test closing packet and document reference.
9. Submit a signed or funded verification request as one administrator.
10. Approve it with a different authorized administrator.
11. Create a reconciliation snapshot.
12. Create and publish a funded-investor reporting period and report version.
13. Verify the Investment Relations tab with the funded Investor account.

## Production smoke test

- Closing Command Center loads without console or API errors.
- Phase 2 investor-round relationships synchronize into closing records.
- Direct closing-record edits cannot alter signed or funded amounts.
- Self-approval of a financial request is rejected.
- Approval by a different authorized administrator updates canonical totals.
- Completed batches cannot be edited without a Super Admin reopen action.
- Only published report versions appear to funded investors.
- Only investor-visible use-of-funds actuals appear in the portal.
- Non-funded investors do not receive the Investment Relations section.
- Phase 1–3 validation workflows remain green.
