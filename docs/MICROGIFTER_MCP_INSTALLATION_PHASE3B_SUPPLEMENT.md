# Microgifter MCP Installation Supplement — Phase 3B

Use this supplement with `docs/MICROGIFTER_MCP_INSTALLATION_AND_ACTIVATION.md`.

## Required SQL

Import after the Phase 3A migration:

```text
database/20260720_mcp_approved_draft_conversion_phase3b_v1.sql
```

Current MCP migration order:

```text
20260720_microgifter_mcp_automation_foundation_v1
20260720_mcp_external_agent_authorization_phase2a_v1
20260720_mcp_approval_gated_drafts_phase3a_v1
20260720_mcp_approved_draft_conversion_phase3b_v1
```

## Deployment before a VPS is available

1. Deploy the latest `integration-from-repair-20260628` files.
2. Import the Phase 3B SQL.
3. Open `/account-agent-drafts.php` with a test account.
4. Verify that an approved draft requires **Prepare conversion** before **Create inactive draft** appears.
5. Keep external OAuth and the public MCP endpoint disabled.

## Validation commands

```bash
vendor/bin/phpunit tests/phpunit/McpApprovedDraftConversionPhase3bV1ContractTest.php
php scripts/run_migrations.php
php scripts/test_mcp_approved_draft_conversion_phase3b.php
```

From `services/mcp`:

```bash
npm ci --ignore-scripts
npm run check
node scripts/simulate-external-agent.mjs
node scripts/external-agent-readiness.mjs
```

## Native draft destinations

| Source proposal | Inactive Microgifter destination |
|---|---|
| Gift | Private gift draft linked to the selected product |
| Campaign | Merchant CRM campaign draft |
| Reward | Reward template with distribution disabled |
| Message | Merchant message draft |

## Future VPS activation

After the Node.js VPS, DNS, TLS, Nginx, and environment values are configured, follow the production VPS guide and run:

```bash
node scripts/external-agent-readiness.mjs --strict
```

Then validate a real client connection, owner review, and owner-only Phase 3B conversion. External MCP clients do not receive a conversion tool.
