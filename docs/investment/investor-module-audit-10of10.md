# Investor Module Full Audit and 10/10 Hardening

## Scope

This audit covers the complete Microgifter investor module from Investor Access through post-close Governance:

1. Investor role and access requests
2. Investment Wizard and official rounds
3. Investor Pipeline and portal publication
4. Due diligence, data room, Q&A and communications
5. Closing, compliance and maker/checker verification
6. Investor relations reporting
7. Governance, rights, obligations, holdings, tax documents and notices
8. Investor Portal data and event boundaries
9. SQL migrations, permissions, audit records and validation workflows

## Scoring history

| Review pass | Score | Main reason |
|---|---:|---|
| Initial architecture review | 8.3/10 | Strong phased structure, but several inherited controls were inconsistent across phases. |
| Financial-integrity discovery | 7.9/10 | Pipeline and official-round editors could bypass Phase 4 maker/checker signed/funded authority. |
| First repair pass | 9.1/10 | Read/write separation, portal privacy and initial publication controls repaired. |
| Full backend hardening | 9.7/10 | Provenance, immutability, audience validation and versioning added; exact approval and regression proof remained. |
| Final audited build | 10.0/10 target | Requires 100/100 audit contract plus Phase 1–5, PHP 8.2/8.3, JavaScript, SQL and app-layout validation. |

## Critical findings and fixes

### 1. Internal review metadata exposed to applicants

**Finding:** The self-service investor-access response included Super Admin `review_notes`.

**Fix:** Applicant responses now use an explicit public whitelist and remove internal review notes from both status and write responses.

### 2. Multiple signed/funded authorities

**Finding:** The Pipeline and official-round editor could directly overwrite signed and funded values after Closing introduced maker/checker verification.

**Fix:**

- Closing maker/checker is now the sole signed/funded authority.
- Pipeline money fields are read-only and server-enforced.
- Official round updates preserve canonical totals.
- Signed/funded stages require proven closing records.
- The migration reconciles legacy relationship and round totals.

### 3. Legacy money treated as verified

**Finding:** Closing sync imported historical Pipeline values into `verified_funded_cents` and the Closing dashboard performed sync work during GET.

**Fix:**

- New records import historical funded money only as `reported_funded_cents`.
- `signed_verification_source` and `funding_verification_source` distinguish unverified values from maker/checker proof.
- GET dashboards are read-only.
- Explicit protected sync remains available.
- Only maker/checker-proven money unlocks closing, holdings, governance and funded-investor access.

### 4. Portal event inflation

**Finding:** Standard document, metric and round events could be submitted without proving that the subject was published and accessible.

**Fix:** Every event now validates the authenticated investor, accessible round, enabled publication section, document/metric visibility and funded provenance before recording engagement.

### 5. Raw investor profile returned by the portal

**Finding:** The portal returned the underlying investor profile row.

**Fix:** The portal returns an explicit public profile whitelist.

### 6. Weak publication permission boundaries

**Finding:** Some data-room documents, Q&A, communications and reports could be published without a dedicated publication authority or without a separate approval step.

**Fix:**

- Data-room, Q&A and communication publication require the dedicated diligence publish permission.
- Legal-review records must first reach approved status.
- Investor relations has a separate `admin.investment.relations.publish` permission.
- Published reports must exactly match a previously approved immutable version.

### 7. Published records could be silently rewritten

**Finding:** Published Q&A, communications, meeting summaries, consents, notices, rights, reporting periods, investor-visible use-of-funds actuals and obligations could be edited in place.

**Fix:** Published records are immutable. Corrections require archive/supersede plus a new version or record.

### 8. No official publication/document revision history

**Finding:** Official portal publication settings and document URLs were overwritten without an immutable revision trail.

**Fix:**

- `investment_round_publication_versions`
- `investment_document_versions`
- Required revision reasons
- Baseline versions for existing records
- Current-version pointers on canonical records

### 9. Inconsistent audience enforcement

**Finding:** Folder visibility was not enforced, funded communications used pre-verification money, and executed consents appeared in the portal by default.

**Fix:**

- A document cannot be less restricted than its data-room folder.
- Funded audiences require maker/checker provenance.
- Specific and selected recipients are prevalidated.
- Executed consents require an explicit `investor_visible` publication action.
- Portal data is filtered again at the final response boundary.

### 10. Read endpoints performed writes

**Finding:** Pipeline and Governance dashboards created or updated rows during GET.

**Fix:** All audited GET dashboards are read-only. Sync, reconciliation and status mutations are explicit protected POST actions.

### 11. Weak input and workflow constraints

**Finding:** Planning money used floating-point conversion; assignees were not consistently validated; diligence submissions had no per-user application limits.

**Fix:**

- Exact decimal-string validation before money conversion
- Admin/Super Admin assignee validation
- Diligence and interest submission limits
- Closing and round state-machine validation
- Evidence/reference requirements for financial, compliance and investor-visible records

### 12. Approved content could change during publication

**Finding:** Several endpoints accepted an approved record and allowed investor-visible fields or approval metadata to change in the same request that published, activated or executed it. The tax wrapper also queried a nonexistent version column, board packet documents lacked an operator path for publishing a specific approved version, and concurrent admin writes could race between the approval check and the underlying transaction.

**Fix:**

- Official documents, tax documents, data-room documents, Q&A, communications and reports must exactly match approved content.
- Board meeting summaries must preserve approved public content, meeting status and counsel status.
- Board packet publication locks and publishes the exact approved version row atomically.
- Written consent execution preserves the exact consent terms approved for external execution.
- Material notices preserve approved content, audience and counsel status.
- Active or investor-visible rights preserve counsel-approved terms.
- Reporting obligations preserve approved content, status and counsel-review requirements.
- Tax publication validates only real schema columns and freezes title, provider, investor, type, year, URL and external reference.
- The packet creation form no longer offers direct publication; an explicit **Publish Approved Packet** control publishes the selected approved version.
- Existing official, diligence, governance and tax records use per-record database advisory locks, serializing approval and publication against concurrent administrator edits.

## New migration

Import once after the Phase 1–5 migrations:

```text
database/20260724_investor_module_audit_hardening_v1.sql
```

The migration is additive and idempotent. It:

- Adds the relations publish permission
- Adds consent portal visibility
- Adds signed/funded verification provenance
- Adds publication and document version tables
- Seeds baseline versions
- Reconciles legacy relationship and official-round totals from maker/checker closing records

Do not reimport the Phase 1–5 migrations.

## Deployment order

1. Merge the audit PR into `integration-from-repair-20260628`.
2. Import `database/20260724_investor_module_audit_hardening_v1.sql` once.
3. Deploy the latest integration branch.
4. Open `/admin/investment-closing.php` and review any legacy provenance warning.
5. Use maker/checker verification for every legacy signed/funded record that should remain official.
6. Refresh holdings from `/admin/investor-governance.php` after verification.
7. Smoke-test the Investor Portal with approved, selected and maker/checker-funded investor accounts.

## Required smoke tests

- Applicant cannot see internal investor-access review notes.
- Pipeline cannot change signed or funded money.
- Official-round editor cannot change signed or funded totals.
- Closing GET does not create records.
- Legacy reported money does not unlock funded sections.
- Maker and checker must be different administrators.
- Approved funded decision updates provenance and official totals.
- Financial reversals reconcile the overall Pipeline stage.
- Published data-room records require publish permission and exact approved content.
- Published Q&A, communications, notices, consents and reports cannot be rewritten.
- Executed consents remain private until explicitly shown in the portal.
- Investor Portal rejects inaccessible document, metric, communication and governance events.
- Published report must match its approved version.
- Tax publication must match the approved version and canonical metadata.
- Board packet creation cannot publish directly.
- An approved funded-investor packet version can be published through its exact version action.
- Meeting, notice and obligation approval metadata cannot change during publication.
- Concurrent administrator edits to the same approval/publication record are serialized or rejected with a conflict.
- Publication and document changes create immutable versions with reasons.

## 10/10 acceptance standard

The investor module receives a final 10/10 only when all of the following are true:

- Weighted audit validator: **100/100**
- Approved publication v12/v15 contract: pass on PHP 8.2 and 8.3
- Serialized exact approval v13–v15 contract: pass on PHP 8.2 and 8.3
- Phase 1 contract: pass on PHP 8.2 and 8.3
- Phase 2 contract: pass on PHP 8.2 and 8.3
- Phase 3 contract: pass on PHP 8.2 and 8.3
- Phase 4 contract: pass on PHP 8.2 and 8.3
- Phase 5 contract: pass on PHP 8.2 and 8.3
- PHP syntax: pass
- JavaScript syntax: pass
- Additive SQL contract: pass
- App Layout Validation: pass

A green workflow without the weighted 100/100 audit contract is not considered a final 10/10 result.
