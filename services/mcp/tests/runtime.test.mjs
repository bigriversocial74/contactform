import test from "node:test";
import assert from "node:assert/strict";
import { once } from "node:events";

import {
  InMemoryInvocationReceiptSink,
  ServiceRuntimeState,
  createInternalMcpApp,
  hashBearerToken,
  loadInternalProtocolConfig,
} from "../dist/index.js";

const token = "runtime-health-test-token";

function config(overrides = {}) {
  return {
    platformEnabled: true,
    internalHttpEnabled: true,
    host: "127.0.0.1",
    port: 0,
    allowedOrigins: [],
    allowedHosts: ["mcp.microgifter.test"],
    tokenSha256: hashBearerToken(token),
    rateLimitRequests: 20,
    rateLimitWindowMs: 60_000,
    connection: {
      connectionId: "connection-runtime-test",
      clientKey: "runtime-test-client",
      userId: "runtime-test-user",
      scopes: ["profile:read", "catalog:read"],
      maximumOperationClass: "read",
      tokenVersion: 1,
    },
    bridge: { enabled: false, url: "", secret: "", timeoutMs: 8_000 },
    runtime: {
      environment: "test",
      release: "runtime-test",
      publicBaseUrl: "",
      shutdownGraceMs: 5_000,
      logLevel: "silent",
      allowNonLoopbackBind: false,
    },
    ...overrides,
  };
}

async function withServer(configuration, callback, bridge, runtime = new ServiceRuntimeState()) {
  const app = createInternalMcpApp(
    configuration,
    new InMemoryInvocationReceiptSink(),
    bridge,
    runtime,
  );
  const server = app.listen(0, "127.0.0.1");
  await once(server, "listening");
  const address = server.address();
  assert.equal(typeof address, "object");
  const baseUrl = `http://127.0.0.1:${address.port}`;
  try {
    await callback({ baseUrl, runtime });
  } finally {
    server.close();
    await once(server, "close");
  }
}

test("health and readiness endpoints expose only safe service metadata", async () => {
  await withServer(config(), async ({ baseUrl }) => {
    const health = await fetch(`${baseUrl}/health`);
    assert.equal(health.status, 200);
    const healthBody = await health.json();
    assert.equal(healthBody.service, "microgifter-mcp");
    assert.equal(healthBody.release, "runtime-test");
    assert.equal(healthBody.liveness, "ok");
    assert.equal(healthBody.token, undefined);
    assert.equal(healthBody.secret, undefined);

    const ready = await fetch(`${baseUrl}/ready`);
    assert.equal(ready.status, 200);
    const readyBody = await ready.json();
    assert.equal(readyBody.readiness, "ready");
  });
});

test("draining makes readiness and MCP traffic fail closed", async () => {
  const runtime = new ServiceRuntimeState();
  await withServer(config(), async ({ baseUrl }) => {
    runtime.beginDrain();
    const ready = await fetch(`${baseUrl}/ready`);
    assert.equal(ready.status, 503);

    const response = await fetch(`${baseUrl}/mcp`, {
      method: "POST",
      headers: {
        "content-type": "application/json",
        accept: "application/json, text/event-stream",
        authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({ jsonrpc: "2.0", id: 1, method: "tools/list", params: {} }),
    });
    assert.equal(response.status, 503);
  }, undefined, runtime);
});

test("bridge readiness failures return 503 without leaking authority errors", async () => {
  const bridge = {
    resolveConnection: async () => { throw new Error("sensitive upstream failure"); },
  };
  await withServer(
    config({
      bridge: {
        enabled: true,
        url: "https://microgifter.test/api/internal/mcp-bridge.php",
        secret: "a".repeat(48),
        timeoutMs: 8_000,
      },
      connection: {
        connectionId: "9eb6e4b1-5c50-4e7a-9b5e-d68828b62822",
        clientKey: "runtime-test-client",
        userId: "runtime-test-user",
        scopes: [],
        maximumOperationClass: "read",
        tokenVersion: 1,
      },
    }),
    async ({ baseUrl }) => {
      const ready = await fetch(`${baseUrl}/ready`);
      assert.equal(ready.status, 503);
      const body = await ready.json();
      assert.equal(body.readiness, "not_ready");
      assert.doesNotMatch(JSON.stringify(body), /sensitive upstream failure/);
    },
    bridge,
  );
});

test("production environment validation enforces HTTPS, allowed hosts, and loopback binding", () => {
  const base = {
    MICROGIFTER_MCP_ENV: "production",
    MICROGIFTER_MCP_ENABLED: "true",
    MICROGIFTER_MCP_INTERNAL_HTTP_ENABLED: "true",
    MICROGIFTER_MCP_INTERNAL_HOST: "127.0.0.1",
    MICROGIFTER_MCP_INTERNAL_TOKEN_SHA256: hashBearerToken(token),
    MICROGIFTER_MCP_INTERNAL_CONNECTION_ID: "9eb6e4b1-5c50-4e7a-9b5e-d68828b62822",
    MICROGIFTER_MCP_INTERNAL_CLIENT_KEY: "runtime-test-client",
    MICROGIFTER_MCP_INTERNAL_USER_ID: "42",
    MICROGIFTER_MCP_BRIDGE_ENABLED: "true",
    MICROGIFTER_MCP_BRIDGE_URL: "https://microgifter.test/api/internal/mcp-bridge.php",
    MICROGIFTER_MCP_BRIDGE_SECRET: "b".repeat(48),
    MICROGIFTER_MCP_PUBLIC_BASE_URL: "https://mcp.microgifter.test",
    MICROGIFTER_MCP_ALLOWED_HOSTS: "mcp.microgifter.test",
    MICROGIFTER_MCP_RELEASE: "mcp-vps-test",
  };

  const valid = loadInternalProtocolConfig(base);
  assert.equal(valid.runtime.environment, "production");

  assert.throws(
    () => loadInternalProtocolConfig({ ...base, MICROGIFTER_MCP_ALLOWED_HOSTS: "" }),
    /allowed Host/,
  );
  assert.throws(
    () => loadInternalProtocolConfig({ ...base, MICROGIFTER_MCP_PUBLIC_BASE_URL: "http://mcp.microgifter.test" }),
    /HTTPS origin/,
  );
  assert.throws(
    () => loadInternalProtocolConfig({ ...base, MICROGIFTER_MCP_INTERNAL_HOST: "0.0.0.0" }),
    /bind outside loopback/,
  );

  const container = loadInternalProtocolConfig({
    ...base,
    MICROGIFTER_MCP_INTERNAL_HOST: "0.0.0.0",
    MICROGIFTER_MCP_ALLOW_NON_LOOPBACK_BIND: "true",
  });
  assert.equal(container.host, "0.0.0.0");
});
