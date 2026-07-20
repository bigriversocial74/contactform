import test from "node:test";
import assert from "node:assert/strict";
import { once } from "node:events";
import { createHash, createHmac } from "node:crypto";

import {
  InMemoryInvocationReceiptSink,
  canonicalBridgeSignaturePayload,
  createInternalMcpApp,
  hashBearerToken,
  signCanonicalBridgeRequest,
} from "../dist/index.js";

const token = "canonical-bridge-test-token";
const connectionId = "11111111-1111-4111-8111-111111111111";

function configuration() {
  return {
    platformEnabled: true,
    internalHttpEnabled: true,
    host: "127.0.0.1",
    port: 0,
    allowedOrigins: ["https://internal.microgifter.test"],
    allowedHosts: [],
    tokenSha256: hashBearerToken(token),
    rateLimitRequests: 20,
    rateLimitWindowMs: 60_000,
    connection: {
      connectionId,
      clientKey: "configured-placeholder",
      userId: "configured-placeholder",
      scopes: [],
      maximumOperationClass: "read",
      tokenVersion: 1,
    },
    bridge: {
      enabled: true,
      url: "https://microgifter.test/api/internal/mcp-bridge.php",
      secret: "bridge-test-secret-with-more-than-thirty-two-characters",
      timeoutMs: 8_000,
    },
  };
}

function fakeBridge(overrides = {}) {
  return {
    async resolveConnection() {
      return {
        connectionId,
        clientKey: "db-resolved-client",
        userId: "42",
        workspace: { type: "merchant", id: "22222222-2222-4222-8222-222222222222" },
        scopes: ["catalog:read", "profile:read"],
        maximumOperationClass: "read",
        tokenVersion: 3,
      };
    },
    async searchCatalog(_connectionId, arguments_) {
      return {
        items: [{ id: "33333333-3333-4333-8333-333333333333", title: "Coffee for Two" }],
        limit: arguments_.limit ?? 10,
        next_cursor: "next-page",
      };
    },
    async getCatalogItem() {
      return { id: "33333333-3333-4333-8333-333333333333", title: "Coffee for Two" };
    },
    async recordReceipt() {},
    ...overrides,
  };
}

async function withServer(bridge, callback) {
  const receipts = new InMemoryInvocationReceiptSink();
  const app = createInternalMcpApp(configuration(), receipts, bridge);
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

test("canonical bridge signatures match the PHP HMAC contract", () => {
  const secret = "bridge-test-secret-with-more-than-thirty-two-characters";
  const timestamp = "1784577600";
  const nonce = "abcdefghijklmnop";
  const body = JSON.stringify({ operation: "connection.resolve" });
  const expectedPayload = `${timestamp}\n${nonce}\n${createHash("sha256").update(body).digest("hex")}`;
  assert.equal(canonicalBridgeSignaturePayload(timestamp, nonce, body), expectedPayload);
  assert.equal(
    signCanonicalBridgeRequest(secret, timestamp, nonce, body),
    createHmac("sha256", secret).update(expectedPayload).digest("hex"),
  );
});

test("bridge-resolved authority replaces configured placeholder scopes", async () => {
  await withServer(fakeBridge(), async ({ baseUrl }) => {
    const response = await rpc(baseUrl, { jsonrpc: "2.0", id: 1, method: "tools/list", params: {} });
    assert.equal(response.status, 200);
    const payload = await response.json();
    assert.deepEqual(payload.result.tools.map((tool) => tool.name), [
      "microgifter.account.get_connection_context",
      "microgifter.catalog.search",
      "microgifter.catalog.get_item",
    ]);
  });
});

test("live catalog search returns canonical results and a receipt", async () => {
  await withServer(fakeBridge(), async ({ baseUrl, receipts }) => {
    const response = await rpc(baseUrl, {
      jsonrpc: "2.0",
      id: 2,
      method: "tools/call",
      params: { name: "microgifter.catalog.search", arguments: { query: "coffee", limit: 5 } },
    });
    assert.equal(response.status, 200);
    const payload = await response.json();
    assert.equal(payload.result.structuredContent.ok, true);
    assert.equal(payload.result.structuredContent.data.items[0].title, "Coffee for Two");
    assert.equal(payload.result.structuredContent.data.next_cursor, "next-page");
    assert.equal(receipts.all().length, 1);
    assert.equal(receipts.all()[0].toolName, "microgifter.catalog.search");
    assert.equal(receipts.all()[0].recordCount, 1);
  });
});

test("enabled bridge without an authority client fails closed", async () => {
  await withServer(undefined, async ({ baseUrl }) => {
    const response = await rpc(baseUrl, {
      jsonrpc: "2.0",
      id: 3,
      method: "initialize",
      params: {
        protocolVersion: "2025-11-25",
        capabilities: {},
        clientInfo: { name: "bridge-test", version: "1.0.0" },
      },
    });
    assert.equal(response.status, 503);
  });
});
