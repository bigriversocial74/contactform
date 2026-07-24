# Investor Pipeline, Portal Publishing & Live Evidence v2

## Purpose

Phase 2 turns the Phase 1 Investment Wizard foundation into an operating workspace for approved-investor relationships, round access, controlled portal publishing, evidence snapshots, and draft-only Claude support.

This system remains administrative. It does not process investment funds, verify accreditation, issue securities, execute legal agreements, or replace the official corporate stock ledger.

## Required migration

Import once:

```text
database/20260723_investor_pipeline_portal_publishing_v2_single_install.sql
```

The migration is additive and requires the Phase 1 Investment Wizard tables.

## Main page

```text
/admin/investor-pipeline.php
```

The workspace includes three tabs:

1. **Pipeline** — approved investor directory, stage, score, assignment, next follow-up, tasks, activity, round relationships and access.
2. **Portal Publishing** — per-round publication state, section visibility, founder update, important notice and Investor View Preview.
3. **Live Evidence** — governed adapters, manual refresh, dated snapshots and forecast-versus-actual target variance.

## Pipeline stages

```text
approved
qualified
contacted
meeting_scheduled
due_diligence
interested
soft_committed
signed
funded
passed
declined
archived
```

## Round relationship values

The pipeline stores indicated interest, soft commitment, signed amount and funded amount as administrative records. Updating these values recalculates the official round’s displayed progress totals. It does not move money or issue an investment instrument.

## Selected-round access

Selected-round access uses the existing `investment_round_access` authority. Granting access does not change the Investor role. Revoking selected-round access removes access only to that round.

## Portal publishing

Publication states:

```text
draft
internal_preview
private_preview
published
paused
archived
```

Private preview or published status requires:

- Official round status at `private_preview` or later.
- Counsel status set to `approved`.
- Investor visibility configured on the official round.

Each section can be independently enabled:

- Company summary
- Round terms
- Raise progress
- Use of funds
- Goals and milestones
- Evidence metrics
- Documents
- Founder update
- Important notice

The actual Investor Portal reads the publication record and does not expose unpublished sections.

## Document visibility

Published documents remain filtered by relationship:

- `approved_investors` — any approved investor who can view the round
- `selected_investors` — investors with an active selected-round grant
- `funded_investors` — investors with a funded relationship record
- `public_summary` — approved portal viewers for the published round

## Evidence adapters

The v2 registry seeds governed adapters for:

- Registered users
- Approved investors
- Active merchants
- Published products
- Active campaigns
- Completed orders
- Total funded-round amount

Adapters use canonical tables only when those tables are available. Every refresh creates a dated `investment_metric_snapshots` record with source reference and confidence.

## Claude Investor Operations Assistant

Available drafts include investor briefing, follow-up email, meeting questions, objection analysis and stalled-opportunity review. Claude reads a saved pipeline snapshot and writes a timeline draft. It cannot send email, alter access, change round terms, or record a commitment.

## Privacy and audit

Pipeline records attach to the canonical Investor profile and user identity. Existing account-erasure handling remains authoritative. Pipeline writes, access grants, publication changes, evidence refreshes and AI drafts are audited.

## Deployment sequence

1. Import the Phase 2 SQL.
2. Upload the latest integration code.
3. Open `/admin/investor-pipeline.php`.
4. Click **Sync approved investors**.
5. Select an official round in **Portal Publishing**.
6. Keep publication status at `draft` or `internal_preview` until counsel and visibility are ready.
7. Use **Live Evidence** to create the first baseline snapshot.
8. Test the Investor Portal with an approved Investor account.
