# Schema Snapshot Review

A MySQL schema export generated on June 17, 2026 was reviewed as part of the recovery baseline.

## Non-sensitive findings

- MySQL server family: 8.0
- Tables present: 235
- Foreign-key declarations: 557
- Recorded migration keys: 25
- Latest recorded migration: `stage_18e_engagement_mutations`
- No database views or triggers were present in the export
- Required Stage 18 operational, moderation, engagement, orchestration, commerce, PPPM, entitlement, Microgift, tip, subscription, and social tables were present

## Migration-history shape

The database records a consolidated early-build marker instead of every individual Stage 1–9 migration:

- `stage_9e4_consolidated_stage1_to_stage9_upgrade`

It also records `stage_11h_backend_hardening`, which is treated as evidence that the preceding Stage 10 and Stage 11G/addendum schema is already present. Later migrations are recorded individually through Stage 18E.

## Safety

The reviewed export contains production-like records. It is intentionally not committed. Use `scripts/validate_schema_dump.php` for future comparisons; the validator reports only counts and missing canonical items and does not print table data.
