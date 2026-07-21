import test from "node:test";
import assert from "node:assert/strict";
import { once } from "node:events";

import {
  InMemoryInvocationReceiptSink,
  createInternalMcpApp,
  hashBearerToken,
} from "../dist/index.js";

const token = "phase3a-review-only-draft-token";
const draftId = "7e0caf3f-b5f0-4f22-9ed5-f54bb8e06320";
const connection = {
  connectionId: "bd7feaae-f87c-4ec0-a750-8ebbc4ae7e83",
  clientKey: "phase3a-draft-client",
  userId: "42",
  workspace: { type: "merchant", id: "16fc318b-d23b-4dcb-998f-e04e4480a5c2" },
  scopes: ["profile:read", "catalog:read", "gift:draft", "campaign:draft", "reward:draft", "message:draft"],
  maximumOperationClass: "draft",
  tokenVersion: 1,
};

function config(connectionOverride = connection) {
  return {
    platformEnabled: true,
    internalHttpEnabled: true,
    host: "127.0.0.1",
    port: 0,
    allowedOrigins: [],
    allowedHosts: [],
    tokenSha256: hashBearerToken(token),
    rateLimitRequests: 100,
    rateLimitWindowMs: 60_000,
    connection: connectionOverride,
    bridge: { enabled: false, url: "", secret: "", timeoutMs: 8_000 },
    externalOAuth: {
      enabled: false,
      resourceUri: "https://mcp.microgifter.test/mcp",
      authorizationServer: "https://microgifter.test",
      protectedResourceMetadataUrl: "https://mcp.microgifter.test/.well-known/oauth-protected-resource",
      allowInternalBearer: true,
    },
  };
}

function bridge(calls) {
  return {
    async resolveConnection() { return connection; },
    async searchCatalog() { return { items: [], limit: 10, next_cursor: null }; },
    async getCatalogItem() { return {}; },
    async createDraft(_connectionId, input) {
      calls.push({ operation: "create", input });
      return {
        id: draftId,
        type: input.type,
        status: "pending_review",
        title: input.title,
        payload: input.payload,
        approval: { required: true },
        execution: { enabled: false, status: "not_enabled", next_step: "owner_review" },
      };
    },
    async listDrafts(_connectionId, input) {
      calls.push({ operation: "list", input });
      return { items: [], next_cursor: null, limit: input.limit ?? 20 };
    },
    async getDraft(_connectionId, id) {
      calls.push({ operation: "get", id });
      return { id, type: "gift", status: "pending_review", execution: { enabled: false } };
    },
    async cancelDraft(_connectionId, id, reason) {
      calls.push({ operation: "cancel", id, reason });
      return { id, type: "gift", status: "canceled", execution: { enabled: false } };
    },
    async recordReceipt() {},
  };
}

async function withServer(configuration, callback) {
  const calls = [];
  const receipts = new InMemoryInvocationReceiptSink();
  const app = createInternalMcpApp(configuration, receipts, bridge(calls));
  const server = app.listen(0, "127.0.0.1");
  await once(server, "listening");
  const address = server.address();
  assert.equal(typeof address, "object");
  try {
    await callback({ baseUrl: `http://127.0.0.1:${address.port}`, calls, receipts });
  } finally {
    server.close();
    await once(server, "close");
  }
}

function rpc(baseUrl, body) {
  return fetch(`${baseUrl}/mcp`, {
    method: "POST",
    headers: {
      authorization: `Bearer ${token}`,
      "content-type": "application/json",
      accept: "application/json, text/event-stream",
    },
    body: JSON.stringify(body),
  });
}

const initialize = {
  jsonrpc: "2.0",
  id: 1,
  method: "initialize",
  params: {
    protocolVersion: "2025-11-25",
    capabilities: {},
    clientInfo: { name: "phase3a-test", version: "1.0.0" },
  },
};

test("draft authority exposes typed review-only tools", async () => {
  await withServer(config(), async ({ baseUrl }) => {
    assert.equal((await rpc(baseUrl, initialize)).status, 200);
    const response = await rpc(baseUrl, { jsonrpc: "2.0", id: 2, method: "tools/list", params: {} });
    assert.equal(response.status, 200);
    const payload = await response.json();
    const names = payload.result.tools.map((tool) => tool.name);
    for (const expected of [
      "microgifter.gift.create_draft",
      "microgifter.campaign.create_draft",
      "microgifter.reward.create_draft",
      "microgifter.message.create_draft",
      "microgifter.drafts.list",
      "microgifter.drafts.get",
      "microgifter.drafts.cancel",
    ]) assert.ok(names.includes(expected), expected);
    assert.ok(!names.some((name) => /publish|send|purchase|execute|schedule/.test(name)));
  });
});

test("gift draft call creates review record without execution", async () => {
  await withServer(config(), async ({ baseUrl, calls, receipts }) => {
    await rpc(baseUrl, initialize);
    const response = await rpc(baseUrl, {
      jsonrpc: "2.0",
      id: 3,
      method: "tools/call",
      params: {
        name: "microgifter.gift.create_draft",
        arguments: {
          title: "Birthday dinner gift",
          summary: "Prepare one local restaurant gift for review.",
          product_id: "b66cb57e-7a86-4772-a9cb-6a183360be56",
          recipient_name: "Alex",
          message: "Happy birthday!",
          quantity: 1,
          idempotency_key: "gift-draft-test-001",
          requested_reason: "The user asked for a draft, not a purchase.",
        },
      },
    });
    assert.equal(response.status, 200);
    const payload = await response.json();
    assert.equal(payload.result.structuredContent.data.status, "pending_review");
    assert.equal(payload.result.structuredContent.data.execution.enabled, false);
    assert.equal(calls[0].operation, "create");
    assert.equal(calls[0].input.type, "gift");
    assert.match(calls[0].input.source_request_id, /^[0-9a-f-]{36}$/);
    const draftReceipt = receipts.all().find((item) => item.toolName === "microgifter.gift.create_draft");
    assert.equal(draftReceipt.operationClass, "draft");
    assert.equal(draftReceipt.resultStatus, "success");
  });
});

test("read-only connections never see draft tools", async () => {
  const readOnly = {
    ...connection,
    scopes: ["profile:read", "catalog:read"],
    maximumOperationClass: "read",
  };
  await withServer(config(readOnly), async ({ baseUrl }) => {
    await rpc(baseUrl, initialize);
    const response = await rpc(baseUrl, { jsonrpc: "2.0", id: 4, method: "tools/list", params: {} });
    const payload = await response.json();
    assert.ok(payload.result.tools.every((tool) => !tool.name.includes("draft")));
  });
});

test("draft management tools stay bound to the canonical bridge", async () => {
  await withServer(config(), async ({ baseUrl, calls }) => {
    await rpc(baseUrl, initialize);
    for (const request of [
      { id: 5, name: "microgifter.drafts.list", arguments: { status: "pending_review", limit: 10 } },
      { id: 6, name: "microgifter.drafts.get", arguments: { draft_id: draftId } },
      { id: 7, name: "microgifter.drafts.cancel", arguments: { draft_id: draftId, reason: "User changed direction." } },
    ]) {
      const response = await rpc(baseUrl, { jsonrpc: "2.0", id: request.id, method: "tools/call", params: { name: request.name, arguments: request.arguments } });
      assert.equal(response.status, 200);
    }
    assert.deepEqual(calls.map((item) => item.operation), ["list", "get", "cancel"]);
  });
});
