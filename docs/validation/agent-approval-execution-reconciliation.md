# Agent approval → execution → failure reconciliation

This validation vertical exercises the canonical Stage 16 agent workflow authorities against real MySQL.

It proves:

- high-risk actions require owner approval;
- exact approval replay is idempotent;
- conflicting approval replay is rejected;
- approved actions execute exactly once;
- execution uses the allowlisted canonical action adapter;
- successful actions reconcile their workflow run to `completed`;
- forced post-effect failures roll back target mutations before failure state is persisted;
- failed actions retain durable failure receipts and append-only execution events;
- runs containing only failed actions reconcile to `failed`;
- all validation fixtures are removed by the owning transaction rollback.

The CLI worker and HTTP approval endpoint delegate to `api/agents/_workflow.php`, keeping one canonical transaction and replay contract for production and behavioral validation.
