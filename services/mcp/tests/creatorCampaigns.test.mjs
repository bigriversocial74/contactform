import test from "node:test";
import assert from "node:assert/strict";
import { once } from "node:events";

import {
  InMemoryInvocationReceiptSink,
  createInternalMcpApp,
  hashBearerToken,
} from "../dist/index.js";

const token = "creator-campaign-v13a-test-token";
const allScopes = [
  "creator_campaigns:read",
  "creator_campaigns_analytics:read",
  "creator_campaign_applications:read",
  "creator_campaign_participants:read",
  "creator_campaign_deliverables:read",
  "creator_campaign_submissions:read",
  "creator_campaign_tracking:read",
  "creator_campaign_attributions:read",
  "creator_campaign_earnings:read",
  "creator_campaign_payouts:read",
  "creator_campaign_disputes:read",
];

function config(scopes = allScopes) {
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
      connectionId: "creator-campaign-connection",
      clientKey: "creator-campaign-test-client",
      userId: "creator-campaign-user",
      workspace: { type: "merchant", id: "merchant-workspace-public-id" },
      scopes,
      maximumOperationClass: "read",
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

const expectedTools = [
  "microgifter.creator_campaigns.list",
  "microgifter.creator_campaigns.get",
  "microgifter.creator_campaigns.validate",
  "microgifter.creator_campaigns.analytics.get",
  "microgifter.creator_campaigns.applications.list",
  "microgifter.creator_campaigns.participants.list",
  "microgifter.creator_campaigns.deliverables.list",
  "microgifter.creator_campaigns.submissions.list",
  "microgifter.creator_campaigns.tracking.get",
  "microgifter.creator_campaigns.attributions.list",
  "microgifter.creator_campaigns.earnings.list",
  "microgifter.creator_campaigns.payouts.list",
  "microgifter.creator_campaigns.disputes.list",
];

test("Creator Campaign read tools are deterministic and scope filtered", async () => {
  const bridge = { creatorCampaignRead: async () => ({ items: [] }) };
  await withServer(config(), bridge, async ({ baseUrl }) => {
    const response = await rpc(baseUrl, { jsonrpc: "2.0", id: 1, method: "tools/list", params: {} });
    assert.equal(response.status, 200);
    const payload = await response.json();
    assert.deepEqual(payload.result.tools.map((tool) => tool.name), expectedTools);
    for (const tool of payload.result.tools) {
      assert.equal(tool.annotations.readOnlyHint, true);
      assert.equal(tool.annotations.destructiveHint, false);
    }
  });

  await withServer(config(["creator_campaign_tracking:read"]), bridge, async ({ baseUrl }) => {
    const response = await rpc(baseUrl, { jsonrpc: "2.0", id: 2, method: "tools/list", params: {} });
    const payload = await response.json();
    assert.deepEqual(payload.result.tools.map((tool) => tool.name), ["microgifter.creator_campaigns.tracking.get"]);
  });
});

test("Creator Campaign reads call the canonical bridge and record receipts", async () => {
  const calls = [];
  const bridge = {
    creatorCampaignRead: async (connectionId, operation, input) => {
      calls.push({ connectionId, operation, input });
      return {
        items: [{ id: "source-public-id", accepted_events: 8, unique_clicks: 3, conversions: 1 }],
        accepted_event_summary: [{ event_type: "click", total: 3 }],
        limit: 25,
        next_cursor: null,
      };
    },
  };

  await withServer(config(["creator_campaign_tracking:read"]), bridge, async ({ baseUrl, receipts }) => {
    const response = await rpc(baseUrl, {
      jsonrpc: "2.0",
      id: 3,
      method: "tools/call",
      params: {
        name: "microgifter.creator_campaigns.tracking.get",
        arguments: { campaign_id: "campaign-public-id", limit: 25 },
      },
    });
    assert.equal(response.status, 200);
    const payload = await response.json();
    assert.equal(payload.result.structuredContent.ok, true);
    assert.equal(payload.result.structuredContent.data.items[0].accepted_events, 8);
    assert.equal(calls.length, 1);
    assert.equal(calls[0].connectionId, "creator-campaign-connection");
    assert.equal(calls[0].operation, "creator_campaigns.tracking.get");
    assert.equal(calls[0].input.campaign_id, "campaign-public-id");
    assert.equal(calls[0].input.limit, 25);
    assert.equal(receipts.all().length, 1);
    assert.equal(receipts.all()[0].toolName, "microgifter.creator_campaigns.tracking.get");
    assert.equal(receipts.all()[0].requiredScope, "creator_campaign_tracking:read");
    assert.equal(receipts.all()[0].resultStatus, "success");
  });
});

test("Creator Campaign tools fail closed when the canonical bridge is disabled", async () => {
  await withServer(config(["creator_campaigns:read"]), undefined, async ({ baseUrl }) => {
    const response = await rpc(baseUrl, {
      jsonrpc: "2.0",
      id: 4,
      method: "tools/call",
      params: { name: "microgifter.creator_campaigns.list", arguments: { limit: 10 } },
    });
    const payload = await response.json();
    assert.equal(payload.result.isError, true);
    assert.match(payload.result.content[0].text, /MICROGIFTER_TOOL_DISABLED/);
  });
});
