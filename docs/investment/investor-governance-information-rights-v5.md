# Investor Governance, Information Rights & Board Operations v5

## Purpose

Phase 5 extends the deployed Investor Wizard, Pipeline, Diligence and Closing system into governed post-close administration.

It provides administrative records for:

- Governance participants and approved appointments.
- Board meetings, attendance, agendas and packet documents.
- Immutable meeting-minute versions.
- Externally executed written consents and response references.
- Counsel-confirmed investor rights.
- Reporting and notice obligations.
- Holdings references generated from verified Phase 4 closing records.
- Externally prepared tax and annual documents.
- Counsel-reviewed material notices.
- Funded-investor governance access and receipt acknowledgements.

The module does **not** appoint directors, determine legal rights, cast votes, provide electronic signatures, prepare tax forms, issue securities, replace the official stock ledger, or provide legal, tax, accounting or investment advice.

## Required migration

Import after the Phase 1–4 investment migrations:

```text
database/20260723_investor_governance_information_rights_v5_single_install.sql
```

The migration is additive. It does not drop or truncate prior investment records.

Do not reimport the Phase 1–4 migrations when installing Phase 5.

## Main entry points

```text
/admin/investor-governance.php
/api/admin/investor-governance.php
/investor-portal.php
/api/investment/portal.php
```

## Administrative workflow

### 1. Refresh holdings references

Select an official round and click **Refresh holdings references**.

Holdings references are generated only from Phase 4 closing records with `verified_funded_cents > 0`. They are administrative reference reports and are not the official stock ledger, transfer-agent record, cap-table provider record or legal ownership record.

Refreshing holdings is required before the funded-investor selectors can be used efficiently for rights and tax-document records.

### 2. Create governance participants

Record directors, observers, officers, committee members, investor representatives, counsel and administrative participants.

A participant record does not appoint anyone. Add an appointment only after an approved corporate or contractual source exists.

### 3. Record appointments

Appointments can reference an approved board resolution, stockholder consent, financing agreement, side letter, employment record, engagement letter or other externally authorized source.

Record voting status, term dates, counsel status and the external appointment-document reference.

### 4. Run board meetings

Meeting workflow:

```text
Planning
Agenda Review
Packet Preparation
Ready
Held
Minutes Drafted
Minutes Approved
Archived
Cancelled
```

For each meeting, administrators can:

- Add attendees and attendance/conflict status.
- Add ordered agenda items.
- Create versioned packet-document references.
- Create immutable minutes versions.
- Publish an explicitly approved funded-investor summary.

Internal minutes and internal notes never appear in the Investor Portal.

### 5. Track written consents

Written consents track externally executed board, stockholder, officer, committee, financing, compensation, contract, banking or other resolutions.

Responses record an external signature or approval reference. Microgifter does not provide electronic signatures or cast votes.

### 6. Record investor rights

Investor rights can only be assigned to an investor with verified funded money in the selected round.

An active right requires counsel status `approved` or `not_applicable`.

Rights may be marked investor-visible. Internal notes never appear in the Investor Portal.

### 7. Manage reporting obligations

Obligations support:

- Quarterly reports.
- Annual reports.
- Annual budgets.
- Tax documents.
- Material-event notices.
- Board-meeting notices.
- Financing and pro-rata notices.
- Information requests.
- Custom obligations.

The dashboard deterministically marks unfinished past-due obligations as overdue. Completion requires an explicit evidence reference.

No investor communication is emailed automatically.

### 8. Publish tax and annual documents

Tax and annual records support immutable external document versions.

Only published document records with a published current version are visible to the assigned funded investor.

Microgifter tracks externally prepared documents and does not prepare tax forms or provide tax advice.

### 9. Publish material notices

Material notices use this workflow:

```text
Draft
Internal Review
Counsel Review
Approved
Published
Archived
```

Publishing requires governance-publish permission and counsel status `approved` or `not_applicable`.

The audience is resolved at publication time and stored as explicit recipient records. Investors may view and acknowledge receipt in the Investor Portal.

No email is sent automatically.

## Investor Portal visibility

The Governance tab appears only when:

- The authenticated account has the Investor role and governance permission.
- The investor has verified funded money in the round.
- The official round is otherwise visible through the existing portal publication rules.

The Governance tab can show:

- Holdings reference summary.
- Active investor-visible rights.
- Published reporting obligations.
- Published board-meeting summaries.
- Published funded-investor packet documents.
- Published tax and annual documents assigned to that investor.
- Assigned material notices.
- Limited executed-consent references.

It does not expose:

- Internal governance notes.
- Conflict details.
- Draft or full board minutes.
- Draft resolutions.
- Consent response notes.
- Counsel comments.
- Internal obligation notes.
- Other investors' rights, holdings or tax documents.

## Claude Governance Assistant

Claude produces internal editable drafts only.

Supported draft actions include:

- Board agenda.
- Meeting briefing.
- Draft minutes.
- Resolution summary.
- Governance action list.
- Investor-rights summary.
- Obligation review.
- Material-event notice.
- Quarterly board update.
- Annual investor update.
- Missing-governance-document review.

Claude cannot determine legal rights, appoint directors, cast votes, approve resolutions, sign documents, publish notices, prepare tax forms, issue securities, modify the official stock ledger, or provide legal or tax advice.

## Deployment sequence

1. Merge the Phase 5 pull request into `integration-from-repair-20260628`.
2. Import `database/20260723_investor_governance_information_rights_v5_single_install.sql` once.
3. Deploy the latest integration branch ZIP.
4. Open `/admin/investor-governance.php` as an authorized administrator.
5. Select a funded official round.
6. Refresh holdings references.
7. Create a test participant and appointment.
8. Create a test meeting, attendee, agenda item and draft minutes version.
9. Create an investor-visible right for a verified funded investor.
10. Create and complete a test reporting obligation.
11. Add a tax-document record but keep it in review until an approved external version exists.
12. Create a material notice and keep it in draft until counsel review is complete.
13. Test `/investor-portal.php` using the assigned funded Investor account.

## Production smoke test

Confirm:

- Unauthorized accounts cannot load the Governance Command Center.
- All write actions require CSRF and the correct permission.
- A non-funded investor cannot receive a Phase 5 right or tax document.
- Active rights require approved or not-applicable counsel status.
- Publishing a material notice requires approved or not-applicable counsel status.
- Consent responses cannot be recorded before approval for external execution.
- New minutes and tax-document updates create new versions.
- The Investor Portal does not reveal internal notes or other investors' records.
- Notice view and acknowledgement timestamps update correctly.
- Phase 2, Phase 3 and Phase 4 portal behavior remains intact.
