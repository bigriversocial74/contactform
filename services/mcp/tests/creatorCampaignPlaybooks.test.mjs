import test from "node:test";
import assert from "node:assert/strict";
import { once } from "node:events";

import {
  InMemoryInvocationReceiptSink,
  createInternalMcpApp,
  hashBearerToken,
} from "../dist/index.js";

const token = "creator-campaign-v13d-test-token";
const allScopes = [
  "creator_campaign_playbooks:campaign_preparation",
  "creator_campaign_playbooks:application_review",
  "creator_campaign_playbooks:content_review",
  "creator_campaign_playbooks:campaign_health",
  "creator_campaign_playbooks:earnings_review",
  "creator_campaign_playbooks:creator_outreach",
];
const expectedTools = [
  "microgifter.creator_campaigns.playbooks.campaign_preparation.run",
  "microgifter.creator_campaigns.playbooks.application_review.run",
  "microgifter.creator_campaigns.playbooks.content_review.run",
  "microgifter.creator_campaigns.playbooks.campaign_health.run",
  "microgifter.creator_campaigns.playbooks.earnings_review.run",
  "microgifter.creator_campaigns.playbooks.creator_outreach.run",
];

function config(scopes = allScopes, maximumOperationClass = "draft") {
  return {
    platformEnabled: true,
    internalHttpEnabled: true,
    host: "127.0.0.1",
    port: 0,
    allowedOrigins: ["https://internal.microgifter.test"],
    allowedHosts: [],
    tokenSha256: hashBearerToken(token),
    rateLimitRequests: 100,
    rateLimitWindowMs: 60_000,
    connection: {
      connectionId: "creator-campaign-playbook-connection",
      clientKey: "creator-campaign-playbook-client",
      userId: "creator-campaign-merchant-user",
      workspace: { type: "merchant", id: "merchant-workspace-public-id" },
      scopes,
      maximumOperationClass,
      tokenVersion: 1,
    },
    bridge: { enabled: false, url: "", secret: "", timeoutMs: 8_000 },
  };
}

async function withServer(configuration, bridge, callback) {
  const receipts = new InMemoryInvocationReceiptSink();
  const app = createInternalMcpApp(configuration, receipts, bridge);
  const server = app.listen(0, "127.0.0.1");
  await once(server, "listening");
  const address = server.address();
  assert.equal(typeof address, "object");
  try {
    await callback({ baseUrl: `http://127.0.0.1:${address.port}`, receipts });
  } finally {
    server.close();
    await once(server, "close");
  }
}

async function rpc(baseUrl, body) {
  return fetch(`${baseUrl}/mcp`, {
    method: "POST",
    headers: {
      "content-type": "application/json",
      accept: "application/json, text/event-stream",
      authorization: `Bearer ${token}`,
      origin: "https://internal.microgifter.test",
    },
    body: JSON.stringify(body),
  });
}

test("Phase 13D playbook tools are exact, non-destructive, and scope filtered", async () => {
  const bridge = { runCreatorCampaignPlaybook: async () => ({ id: "run-id", status: "succeeded" }) };
  await withServer(config(), bridge, async ({ baseUrl }) => {
    const response = await rpc(baseUrl, { jsonrpc: "2.0", id: 1, method: "tools/list", params: {} });
    assert.equal(response.status, 200);
    const payload = await response.json();
    assert.deepEqual(payload.result.tools.map((tool) => tool.name), expectedTools);
    for (const tool of payload.result.tools) {
      assert.equal(tool.annotations.readOnlyHint, false);
      assert.equal(tool.annotations.destructiveHint, false);
      assert.equal(tool.annotations.idempotentHint, true);
      assert.equal(tool.annotations.openWorldHint, false);
    }
  });

  await withServer(config(["creator_campaign_playbooks:campaign_health"]), bridge, async ({ baseUrl }) => {
    const response = await rpc(baseUrl, { jsonrpc: "2.0", id: 2, method: "tools/list", params: {} });
    const payload = await response.json();
    assert.deepEqual(payload.result.tools.map((tool) => tool.name), [
      "microgifter.creator_campaigns.playbooks.campaign_health.run",
    ]);
  });

  await withServer(config(["profile:read"], "read"), bridge, async ({ baseUrl }) => {
    const response = await rpc(baseUrl, { jsonrpc: "2.0", id: 3, method: "tools/list", params: {} });
    const payload = await response.json();
    assert.deepEqual(payload.result.tools.map((tool) => tool.name), [
      "microgifter.account.get_connection_context",
    ]);
  });
});

test("campaign health uses the canonical bounded-playbook bridge and records a draft receipt", async () => {
  const calls = [];
  const bridge = {
    runCreatorCampaignPlaybook: async (connectionId, toolName, input) => {
      calls.push({ connectionId, toolName, input });
      return {
        id: "playbook-run-public-id",
        status: "succeeded",
        artifact: { id: "review-artifact-public-id", status: "pending_review" },
        execution: { performed: false, canonical_mutation: false, external_effect: false },
      };
    },
  };

  await withServer(config(["creator_campaign_playbooks:campaign_health"]), bridge, async ({ baseUrl, receipts }) => {
    const response = await rpc(baseUrl, {
      jsonrpc: "2.0",
      id: 4,
      method: "tools/call",
      params: {
        name: "microgifter.creator_campaigns.playbooks.campaign_health.run",
        arguments: {
          automation_id: "55d5ad76-3b70-48ff-80bf-84df49d7b0ce",
          campaign_id: "cc_12345678",
          days: 30,
          assessment_notes: "Review campaign performance and operating risks.",
          recommended_actions: [{ type: "review_submissions", priority: "medium" }],
          idempotency_key: "phase13d-health-001",
          requested_reason: "Prepare a merchant review report.",
        },
      },
    });
    assert.equal(response.status, 200);
    const payload = await response.json();
    assert.equal(payload.result.structuredContent.ok, true);
    assert.equal(payload.result.structuredContent.execution.performed, false);
    assert.equal(payload.result.structuredContent.execution.canonical_action_request_created, false);
    assert.equal(payload.result.structuredContent.execution.canonical_mutation, false);
    assert.equal(payload.result.structuredContent.execution.external_effect, false);
    assert.equal(calls.length, 1);
    assert.equal(calls[0].connectionId, "creator-campaign-playbook-connection");
    assert.equal(calls[0].toolName, "microgifter.creator_campaigns.playbooks.campaign_health.run");
    assert.equal(calls[0].input.campaign_id, "cc_12345678");
    assert.match(calls[0].input.source_request_id, /^[0-9a-f-]{36}$/);
    assert.equal(receipts.all().length, 1);
    assert.equal(receipts.all()[0].operationClass, "draft");
    assert.equal(receipts.all()[0].requiredScope, "creator_campaign_playbooks:campaign_health");
    assert.equal(receipts.all()[0].resultStatus, "success");
  });
});

test("Phase 13D playbooks fail closed when the canonical bridge is disabled", async () => {
  await withServer(config(["creator_campaign_playbooks:application_review"]), undefined, async ({ baseUrl, receipts }) => {
    const response = await rpc(baseUrl, {
      jsonrpc: "2.0",
      id: 5,
      method: "tools/call",
      params: {
        name: "microgifter.creator_campaigns.playbooks.application_review.run",
        arguments: {
          automation_id: "55d5ad76-3b70-48ff-80bf-84df49d7b0ce",
          campaign_id: "cc_12345678",
          application_id: "cca_12345678",
          recommendation: "needs_review",
          fit_score: 75,
          rationale: "The application needs owner review.",
          idempotency_key: "phase13d-app-001",
          requested_reason: "Prepare an application review.",
        },
      },
    });
    const payload = await response.json();
    assert.equal(payload.result.isError, true);
    assert.match(payload.result.content[0].text, /MICROGIFTER_TOOL_DISABLED/);
    assert.equal(receipts.all()[0].resultStatus, "failed");
  });
});
