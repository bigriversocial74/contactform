import test from "node:test";
import assert from "node:assert/strict";
import { once } from "node:events";

import {
  InMemoryInvocationReceiptSink,
  createInternalMcpApp,
  hashBearerToken,
} from "../dist/index.js";

const token = "phase1-internal-test-token";

function config(overrides = {}) {
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
      connectionId: "connection-test",
      clientKey: "internal-test-client",
      userId: "user-test",
      scopes: ["profile:read", "catalog:read"],
      maximumOperationClass: "read",
      tokenVersion: 1,
    },
    bridge: {
      enabled: false,
      url: "",
      secret: "",
      timeoutMs: 8_000,
    },
    ...overrides,
  };
}

async function withServer(configuration, callback, bridge) {
  const receipts = new InMemoryInvocationReceiptSink();
  const app = createInternalMcpApp(configuration, receipts, bridge);
  const server = app.listen(0, "127.0.0.1");
  await once(server, "listening");
  const address = server.address();
  assert.equal(typeof address, "object");
  const baseUrl = `http://127.0.0.1:${address.port}`;
  try {
    await callback({ baseUrl, receipts });
  } finally {
    server.close();
    await once(server, "close");
  }
}

function headers(extra = {}) {
  return {
    "content-type": "application/json",
    accept: "application/json, text/event-stream",
    authorization: `Bearer ${token}`,
    origin: "https://internal.microgifter.test",
    ...extra,
  };
}

async function rpc(baseUrl, body, extraHeaders = {}) {
  return fetch(`${baseUrl}/mcp`, {
    method: "POST",
    headers: headers(extraHeaders),
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
    clientInfo: { name: "mcp-phase1-contract-test", version: "1.0.0" },
  },
};

test("disabled internal HTTP fails closed with 404", async () => {
  await withServer(config({ platformEnabled: false, internalHttpEnabled: false }), async ({ baseUrl }) => {
    const response = await rpc(baseUrl, initialize);
    assert.equal(response.status, 404);
  });
});

test("invalid origin is rejected with 403", async () => {
  await withServer(config(), async ({ baseUrl }) => {
    const response = await rpc(baseUrl, initialize, { origin: "https://evil.example" });
    assert.equal(response.status, 403);
  });
});

test("missing bearer token is rejected with 401", async () => {
  await withServer(config(), async ({ baseUrl }) => {
    const response = await rpc(baseUrl, initialize, { authorization: "" });
    assert.equal(response.status, 401);
    assert.match(response.headers.get("www-authenticate") ?? "", /Bearer/);
  });
});

test("initialize negotiates the stable MCP protocol", async () => {
  await withServer(config(), async ({ baseUrl }) => {
    const response = await rpc(baseUrl, initialize);
    assert.equal(response.status, 200);
    const result = await response.json();
    assert.equal(result.jsonrpc, "2.0");
    assert.equal(result.id, 1);
    assert.equal(result.result.protocolVersion, "2025-11-25");
    assert.equal(result.result.serverInfo.name, "microgifter-mcp");
    assert.equal(result.result.capabilities.tools.listChanged, true);
  });
});

test("tools list is deterministic and scope filtered", async () => {
  await withServer(config(), async ({ baseUrl }) => {
    const response = await rpc(baseUrl, { jsonrpc: "2.0", id: 2, method: "tools/list", params: {} });
    assert.equal(response.status, 200);
    const result = await response.json();
    assert.deepEqual(
      result.result.tools.map((tool) => tool.name),
      [
        "microgifter.account.get_connection_context",
        "microgifter.catalog.search",
        "microgifter.catalog.get_item",
      ],
    );
  });

  await withServer(
    config({
      connection: {
        connectionId: "profile-only",
        clientKey: "internal-test-client",
        userId: "user-test",
        scopes: ["profile:read"],
        maximumOperationClass: "read",
        tokenVersion: 1,
      },
    }),
    async ({ baseUrl }) => {
      const response = await rpc(baseUrl, { jsonrpc: "2.0", id: 3, method: "tools/list", params: {} });
      const result = await response.json();
      assert.deepEqual(result.result.tools.map((tool) => tool.name), ["microgifter.account.get_connection_context"]);
    },
  );
});

test("account context tool returns minimized data and records a receipt", async () => {
  await withServer(config(), async ({ baseUrl, receipts }) => {
    const response = await rpc(baseUrl, {
      jsonrpc: "2.0",
      id: 4,
      method: "tools/call",
      params: { name: "microgifter.account.get_connection_context", arguments: {} },
    });
    assert.equal(response.status, 200);
    const result = await response.json();
    assert.equal(result.result.structuredContent.ok, true);
    assert.equal(result.result.structuredContent.data.connection_id, "connection-test");
    assert.equal(result.result.structuredContent.data.maximum_operation_class, "read");
    assert.equal(result.result.structuredContent.data.password, undefined);
    assert.equal(receipts.all().length, 1);
    assert.equal(receipts.all()[0].resultStatus, "success");
    assert.match(receipts.all()[0].inputFingerprint, /^[a-f0-9]{64}$/);
  });
});

test("catalog tools remain listed but fail closed without a canonical bridge", async () => {
  await withServer(config(), async ({ baseUrl }) => {
    const response = await rpc(baseUrl, {
      jsonrpc: "2.0",
      id: 5,
      method: "tools/call",
      params: { name: "microgifter.catalog.search", arguments: { query: "coffee", limit: 5 } },
    });
    assert.equal(response.status, 200);
    const result = await response.json();
    assert.equal(result.result.isError, true);
    assert.match(result.result.content[0].text, /MICROGIFTER_TOOL_DISABLED/);
  });
});

test("per-connection rate limits return 429 and retry metadata", async () => {
  await withServer(config({ rateLimitRequests: 1 }), async ({ baseUrl }) => {
    const first = await rpc(baseUrl, initialize);
    assert.equal(first.status, 200);
    const second = await rpc(baseUrl, initialize);
    assert.equal(second.status, 429);
    assert.ok(Number(second.headers.get("retry-after")) >= 1);
  });
});

test("GET and DELETE are not enabled for the stateless internal endpoint", async () => {
  await withServer(config(), async ({ baseUrl }) => {
    const get = await fetch(`${baseUrl}/mcp`);
    assert.equal(get.status, 405);
    const remove = await fetch(`${baseUrl}/mcp`, { method: "DELETE" });
    assert.equal(remove.status, 405);
  });
});
