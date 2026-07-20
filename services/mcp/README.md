# Microgifter MCP Service

This directory is the separately deployable TypeScript control plane for Microgifter Platform Phase 5.

Phase 1 foundation posture:

- disabled by default;
- no public endpoint;
- no external OAuth;
- no scheduled execution;
- no write-capable tools;
- no database credentials;
- canonical data and commands must pass through protected PHP bridge contracts.

The service foundation defines durable automation grants, automations, triggers, runs, actions, approvals, receipts, idempotency, queue/worker ports and canonical bridge ports. MCP transport and the first read-only tools are added in the next scoped Phase 1 section.

## Local validation

```bash
npm ci
npm run check
```
