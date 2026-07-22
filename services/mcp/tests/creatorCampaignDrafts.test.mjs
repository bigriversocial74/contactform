import test from "node:test";
import assert from "node:assert/strict";
import { once } from "node:events";

import {
  InMemoryInvocationReceiptSink,
  createInternalMcpApp,
  hashBearerToken,
} from "../dist/index.js";

const token = "creator-campaign-v13b-test-token";
const allScopes = [
  "creator_campaigns:draft",
  "creator_campaign_products:draft",
  "creator_campaign_eligibility:draft",
  "creator_campaign_deliverables:draft",
  "creator_campaign_compensation:draft",
  "creator_campaign_attribution:draft",
  "creator_campaign_budget:draft",
  "creator_campaign_rights:draft",
  "creator_campaign_terms:draft",
  "creator_campaign_invitations:draft",
  "creator_campaign_messages:draft",
  "creator_campaign_submission_feedback:draft",
];

const expectedTools = [
  "microgifter.creator_campaigns.draft.create",
  "microgifter.creator_campaigns.draft.update",
  "microgifter.creator_campaigns.products.propose",
  "microgifter.creator_campaigns.eligibility.propose",
  "microgifter.creator_campaigns.deliverables.propose",
  "microgifter.creator_campaigns.compensation.propose",
  "microgifter.creator_campaigns.attribution.propose",
  "microgifter.creator_campaigns.budget.propose",
  "microgifter.creator_campaigns.rights.propose",
  "microgifter.creator_campaigns.terms.propose",
  "microgifter.creator_campaigns.invitation.draft",
  "microgifter.creator_campaigns.message.draft",
  "microgifter.creator_campaigns.submission_feedback.draft",
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
      connectionId: "creator-campaign-draft-connection",
      clientKey: "creator-campaign-draft-client",
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

test("Creator Campaign draft tools are deterministic, non-destructive, and scope filtered", async () => {
  const bridge = { createDraft: async () => ({ id: "proposal-id", status: "pending_review" }) };
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

  await withServer(config(["creator_campaign_budget:draft"]), bridge, async ({ baseUrl }) => {
    const response = await rpc(baseUrl, { jsonrpc: "2.0", id: 2, method: "tools/list", params: {} });
    const payload = await response.json();
    assert.deepEqual(payload.result.tools.map((tool) => tool.name), ["microgifter.creator_campaigns.budget.propose"]);
  });

  await withServer(config(["profile:read"], "read"), bridge, async ({ baseUrl }) => {
    const response = await rpc(baseUrl, { jsonrpc: "2.0", id: 3, method: "tools/list", params: {} });
    const payload = await response.json();
    assert.deepEqual(
      payload.result.tools.map((tool) => tool.name),
      ["microgifter.account.get_connection_context"],
    );
  });
});

test("budget proposals use the canonical review ledger and record a draft receipt", async () => {
  const calls = [];
  const bridge = {
    createDraft: async (connectionId, input) => {
      calls.push({ connectionId, input });
      return {
        id: "proposal-public-id",
        type: "campaign",
        status: "pending_review",
        proposal: { kind: "budget.propose", native_conversion_enabled: false },
      };
    },
  };

  await withServer(config(["creator_campaign_budget:draft"]), bridge, async ({ baseUrl, receipts }) => {
    const response = await rpc(baseUrl, {
      jsonrpc: "2.0",
      id: 4,
      method: "tools/call",
      params: {
        name: "microgifter.creator_campaigns.budget.propose",
        arguments: {
          title: "Summer creator campaign budget",
          summary: "Review a $5,000 maximum campaign budget.",
          campaign_id: "cc_12345678",
          currency: "usd",
          limit_minor: 500000,
          allow_overage: false,
          overage_limit_minor: 0,
          idempotency_key: "phase13b-budget-001",
          risk_level: "high",
        },
      },
    });
    assert.equal(response.status, 200);
    const payload = await response.json();
    assert.equal(payload.result.structuredContent.ok, true);
    assert.equal(calls.length, 1);
    assert.equal(calls[0].connectionId, "creator-campaign-draft-connection");
    assert.equal(calls[0].input.type, "campaign");
    assert.equal(calls[0].input.payload.creator_campaign_proposal, true);
    assert.equal(calls[0].input.payload.proposal_kind, "budget.propose");
    assert.equal(calls[0].input.payload.campaign_id, "cc_12345678");
    assert.equal(calls[0].input.payload.proposed_values.currency, "USD");
    assert.equal(calls[0].input.payload.proposed_values.limit_minor, 500000);
    assert.equal(calls[0].input.payload.proposed_values.allow_overage, false);
    assert.equal(receipts.all().length, 1);
    assert.equal(receipts.all()[0].toolName, "microgifter.creator_campaigns.budget.propose");
    assert.equal(receipts.all()[0].requiredScope, "creator_campaign_budget:draft");
    assert.equal(receipts.all()[0].operationClass, "draft");
    assert.equal(receipts.all()[0].resultStatus, "success");
  });
});

test("Creator Campaign proposals fail closed when the canonical draft bridge is disabled", async () => {
  await withServer(config(["creator_campaigns:draft"]), undefined, async ({ baseUrl }) => {
    const response = await rpc(baseUrl, {
      jsonrpc: "2.0",
      id: 5,
      method: "tools/call",
      params: {
        name: "microgifter.creator_campaigns.draft.create",
        arguments: {
          title: "Draft proposal",
          summary: "Review-only proposal",
          internal_reference: "CC-TEST-001",
          name: "Creator campaign",
          objective: "Awareness",
          category: "hospitality",
          campaign_focus: "general_brand_campaign",
          access_mode: "open",
          timezone: "UTC",
          idempotency_key: "phase13b-create-001",
          risk_level: "medium",
        },
      },
    });
    const payload = await response.json();
    assert.equal(payload.result.isError, true);
    assert.match(payload.result.content[0].text, /MICROGIFTER_TOOL_DISABLED/);
  });
});
