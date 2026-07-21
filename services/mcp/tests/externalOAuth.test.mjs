import test from "node:test";
import assert from "node:assert/strict";
import { once } from "node:events";

import {
  CanonicalBridgeError,
  InMemoryInvocationReceiptSink,
  createInternalMcpApp,
  hashBearerToken,
  validateInternalProtocolConfig,
} from "../dist/index.js";

const externalToken = "phase2a-external-oauth-access-token";
const internalToken = "phase2a-internal-fallback-token";
const connection = {
  connectionId: "65d6f920-1e11-4f45-91fd-65a89522dff0",
  clientKey: "oauth-test-client",
  userId: "42",
  scopes: ["profile:read", "catalog:read"],
  maximumOperationClass: "read",
  tokenVersion: 3,
};

function config(overrides = {}) {
  return {
    platformEnabled: true,
    internalHttpEnabled: true,
    host: "127.0.0.1",
    port: 0,
    allowedOrigins: ["https://chatgpt.example"],
    allowedHosts: [],
    tokenSha256: hashBearerToken(internalToken),
    rateLimitRequests: 20,
    rateLimitWindowMs: 60_000,
    connection,
    bridge: {
      enabled: true,
      url: "https://microgifter.example/api/internal/mcp-bridge.php",
      secret: "phase2a-test-bridge-secret-0123456789abcdef",
      timeoutMs: 8_000,
    },
    externalOAuth: {
      enabled: true,
      resourceUri: "https://mcp.microgifter.example/mcp",
      authorizationServer: "https://microgifter.example",
      protectedResourceMetadataUrl: "https://mcp.microgifter.example/.well-known/oauth-protected-resource",
      allowInternalBearer: false,
    },
    ...overrides,
  };
}

function resolver() {
  return {
    async resolveAccessToken(tokenSha256, resourceUri) {
      assert.equal(tokenSha256, hashBearerToken(externalToken));
      assert.equal(resourceUri, "https://mcp.microgifter.example/mcp");
      return {
        connection,
        oauthClientId: "oauth-client-public-id",
        tokenFamilyId: "5f8ca8b7-4d25-45ad-a65b-ab33ee3c228d",
      };
    },
  };
}

const bridge = {
  async resolveConnection() { return connection; },
  async searchCatalog() { return { items: [], limit: 10, next_cursor: null }; },
  async getCatalogItem() { return {}; },
  async recordReceipt() {},
};

async function withServer(configuration, callback, tokenResolver = resolver()) {
  const receipts = new InMemoryInvocationReceiptSink();
  const app = createInternalMcpApp(configuration, receipts, bridge, undefined, undefined, tokenResolver);
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

function rpc(baseUrl, token, body) {
  return fetch(`${baseUrl}/mcp`, {
    method: "POST",
    headers: {
      "content-type": "application/json",
      accept: "application/json, text/event-stream",
      authorization: token ? `Bearer ${token}` : "",
      origin: "https://chatgpt.example",
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
    clientInfo: { name: "phase2a-oauth-test", version: "1.0.0" },
  },
};

test("protected resource metadata is discoverable when OAuth is enabled", async () => {
  await withServer(config(), async ({ baseUrl }) => {
    const response = await fetch(`${baseUrl}/.well-known/oauth-protected-resource`);
    assert.equal(response.status, 200);
    assert.equal(response.headers.get("access-control-allow-origin"), "*");
    const metadata = await response.json();
    assert.equal(metadata.resource, "https://mcp.microgifter.example/mcp");
    assert.deepEqual(metadata.authorization_servers, ["https://microgifter.example"]);
    assert.deepEqual(metadata.scopes_supported, [
      "campaign:draft",
      "catalog:read",
      "gift:draft",
      "message:draft",
      "profile:read",
      "reward:draft",
    ]);
  });
});

test("missing OAuth token returns resource metadata challenge", async () => {
  await withServer(config(), async ({ baseUrl }) => {
    const response = await rpc(baseUrl, "", initialize);
    assert.equal(response.status, 401);
    const challenge = response.headers.get("www-authenticate") ?? "";
    assert.match(challenge, /Bearer realm="microgifter-mcp"/);
    assert.match(challenge, /resource_metadata="https:\/\/mcp\.microgifter\.example\/\.well-known\/oauth-protected-resource"/);
  });
});

test("valid OAuth token resolves the caller connection dynamically", async () => {
  await withServer(config(), async ({ baseUrl }) => {
    const initialized = await rpc(baseUrl, externalToken, initialize);
    assert.equal(initialized.status, 200);
    const tools = await rpc(baseUrl, externalToken, {
      jsonrpc: "2.0",
      id: 2,
      method: "tools/list",
      params: {},
    });
    assert.equal(tools.status, 200);
    const payload = await tools.json();
    assert.deepEqual(payload.result.tools.map((tool) => tool.name), [
      "microgifter.account.get_connection_context",
      "microgifter.catalog.search",
      "microgifter.catalog.get_item",
    ]);
  });
});

test("invalid external token remains a 401 OAuth challenge", async () => {
  const deniedResolver = {
    async resolveAccessToken() {
      throw new CanonicalBridgeError("Invalid access token.", "MCP_OAUTH_TOKEN_INVALID", 401);
    },
  };
  await withServer(config(), async ({ baseUrl }) => {
    const response = await rpc(baseUrl, "invalid-external-token-value", initialize);
    assert.equal(response.status, 401);
    assert.match(response.headers.get("www-authenticate") ?? "", /resource_metadata=/);
  }, deniedResolver);
});

test("internal bearer fallback is explicit and optional", async () => {
  const fallback = config({
    externalOAuth: {
      ...config().externalOAuth,
      allowInternalBearer: true,
    },
  });
  await withServer(fallback, async ({ baseUrl }) => {
    const response = await rpc(baseUrl, internalToken, initialize);
    assert.equal(response.status, 200);
  }, {
    async resolveAccessToken() {
      throw new CanonicalBridgeError("Invalid access token.", "MCP_OAUTH_TOKEN_INVALID", 401);
    },
  });
});

test("production external OAuth configuration remains fail closed", () => {
  const production = config({
    tokenSha256: "",
    externalOAuth: {
      ...config().externalOAuth,
      allowInternalBearer: false,
    },
    runtime: {
      environment: "production",
      release: "phase2a-test",
      publicBaseUrl: "https://mcp.microgifter.example",
      shutdownGraceMs: 30_000,
      logLevel: "silent",
      allowNonLoopbackBind: false,
    },
    allowedHosts: ["mcp.microgifter.example"],
  });
  assert.doesNotThrow(() => validateInternalProtocolConfig(production));
  assert.throws(
    () => validateInternalProtocolConfig({
      ...production,
      externalOAuth: {
        ...production.externalOAuth,
        resourceUri: "https://other.example/mcp",
      },
    }),
    /resource URI must match/,
  );
});
