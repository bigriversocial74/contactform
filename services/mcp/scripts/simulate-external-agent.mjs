import assert from "node:assert/strict";
import { createHash, randomBytes, randomUUID } from "node:crypto";
import { once } from "node:events";

import {
  CanonicalBridgeError,
  InMemoryInvocationReceiptSink,
  createInternalMcpApp,
  hashBearerToken,
} from "../dist/index.js";

const RESOURCE_URI = "https://mcp.microgifter.com/mcp";
const AUTHORIZATION_SERVER = "https://microgifter.com";
const ORIGIN = "http://127.0.0.1";
const SCOPES = ["profile:read", "catalog:read"];
const PROFILES = [
  { key: "chatgpt", name: "ChatGPT", redirectUri: "http://127.0.0.1/callback/chatgpt" },
  { key: "claude", name: "Claude", redirectUri: "http://127.0.0.1/callback/claude" },
  { key: "generic", name: "Generic remote MCP client", redirectUri: "http://127.0.0.1/callback/generic" },
];

function sha256(value) {
  return createHash("sha256").update(value).digest("hex");
}

function pkceChallenge(verifier) {
  return createHash("sha256").update(verifier).digest("base64url");
}

function opaqueToken(prefix) {
  return `${prefix}_${randomBytes(32).toString("base64url")}`;
}

function refuseProduction() {
  const environments = [process.env.NODE_ENV, process.env.MICROGIFTER_MCP_ENV]
    .filter(Boolean)
    .map((value) => String(value).trim().toLowerCase());
  if (environments.includes("production")) {
    throw new Error("The external-agent simulator refuses to run in production mode.");
  }
}

class SimulatedOAuthAuthority {
  constructor(connection) {
    this.connection = connection;
    this.codes = new Map();
    this.accessTokens = new Map();
    this.refreshTokens = new Map();
    this.families = new Map();
  }

  beginAuthorization(profile) {
    const verifier = randomBytes(48).toString("base64url");
    const code = opaqueToken("code");
    this.codes.set(sha256(code), {
      profile,
      challenge: pkceChallenge(verifier),
      consumed: false,
      resourceUri: RESOURCE_URI,
      scopes: [...SCOPES],
    });
    return { code, verifier };
  }

  exchangeAuthorizationCode(profile, code, verifier) {
    const record = this.codes.get(sha256(code));
    assert(record, "Authorization code was not issued.");
    assert.equal(record.consumed, false, "Authorization code must be single use.");
    assert.equal(record.profile.redirectUri, profile.redirectUri, "Redirect URI must match exactly.");
    assert.equal(record.resourceUri, RESOURCE_URI, "Resource indicator must match.");
    assert.equal(pkceChallenge(verifier), record.challenge, "PKCE S256 verification failed.");
    record.consumed = true;
    return this.issueTokenPair(profile, randomUUID());
  }

  issueTokenPair(profile, familyId) {
    const family = this.families.get(familyId) ?? { revoked: false };
    this.families.set(familyId, family);
    const accessToken = opaqueToken("access");
    const refreshToken = opaqueToken("refresh");
    this.accessTokens.set(sha256(accessToken), {
      familyId,
      profile,
      resourceUri: RESOURCE_URI,
      revoked: false,
    });
    this.refreshTokens.set(sha256(refreshToken), {
      familyId,
      profile,
      resourceUri: RESOURCE_URI,
      used: false,
      revoked: false,
    });
    return { accessToken, refreshToken, familyId };
  }

  rotateRefreshToken(refreshToken) {
    const record = this.refreshTokens.get(sha256(refreshToken));
    if (!record) throw new Error("Unknown refresh token.");
    const family = this.families.get(record.familyId);
    if (!family || family.revoked || record.revoked) throw new Error("Refresh token family is revoked.");
    if (record.used) {
      family.revoked = true;
      throw new Error("Refresh token replay detected; the token family was revoked.");
    }
    record.used = true;
    return this.issueTokenPair(record.profile, record.familyId);
  }

  revokeByAccessToken(accessToken) {
    const record = this.accessTokens.get(sha256(accessToken));
    if (!record) return;
    const family = this.families.get(record.familyId);
    if (family) family.revoked = true;
  }

  async resolveAccessToken(tokenSha256, resourceUri) {
    const record = this.accessTokens.get(tokenSha256);
    if (!record) {
      throw new CanonicalBridgeError("Invalid simulated access token.", "MCP_OAUTH_TOKEN_INVALID", 401);
    }
    const family = this.families.get(record.familyId);
    if (record.revoked || !family || family.revoked) {
      throw new CanonicalBridgeError("Simulated access token is revoked.", "MCP_OAUTH_TOKEN_REVOKED", 401);
    }
    if (record.resourceUri !== resourceUri) {
      throw new CanonicalBridgeError("Simulated token resource mismatch.", "MCP_OAUTH_RESOURCE_MISMATCH", 403);
    }
    return {
      connection: this.connection,
      oauthClientId: `simulator-${record.profile.key}`,
      tokenFamilyId: record.familyId,
    };
  }
}

function configuration(connection) {
  return {
    platformEnabled: true,
    internalHttpEnabled: true,
    host: "127.0.0.1",
    port: 0,
    allowedOrigins: [ORIGIN],
    allowedHosts: [],
    tokenSha256: hashBearerToken("phase2b-unused-internal-token"),
    rateLimitRequests: 100,
    rateLimitWindowMs: 60_000,
    connection,
    bridge: {
      enabled: true,
      url: "https://microgifter.invalid/api/internal/mcp-bridge.php",
      secret: "phase2b-loopback-simulator-secret-not-for-production",
      timeoutMs: 8_000,
    },
    externalOAuth: {
      enabled: true,
      resourceUri: RESOURCE_URI,
      authorizationServer: AUTHORIZATION_SERVER,
      protectedResourceMetadataUrl: `${RESOURCE_URI.replace(/\/mcp$/, "")}/.well-known/oauth-protected-resource`,
      allowInternalBearer: false,
    },
    runtime: {
      environment: "test",
      release: "phase2b-simulator",
      publicBaseUrl: RESOURCE_URI.replace(/\/mcp$/, ""),
      shutdownGraceMs: 5_000,
      logLevel: "silent",
      allowNonLoopbackBind: false,
    },
  };
}

function rpc(baseUrl, token, body) {
  return fetch(`${baseUrl}/mcp`, {
    method: "POST",
    headers: {
      "content-type": "application/json",
      accept: "application/json, text/event-stream",
      authorization: token ? `Bearer ${token}` : "",
      origin: ORIGIN,
    },
    body: JSON.stringify(body),
  });
}

async function closeServer(server) {
  const closed = once(server, "close");
  server.close();
  await closed;
}

async function runProfile(profile) {
  const connection = {
    connectionId: randomUUID(),
    clientKey: `phase2b-${profile.key}`,
    userId: "42",
    workspace: { type: "account", id: "42" },
    scopes: [...SCOPES],
    maximumOperationClass: "read",
    tokenVersion: 1,
  };
  const authority = new SimulatedOAuthAuthority(connection);
  const receipts = new InMemoryInvocationReceiptSink();
  const bridgeCalls = [];
  const bridge = {
    async resolveConnection() {
      return connection;
    },
    async searchCatalog(connectionId, arguments_) {
      bridgeCalls.push({ operation: "catalog.search", connectionId, arguments: arguments_ });
      return {
        items: [{ product_id: "simulated-product-1", name: "Simulator Coffee", status: "published" }],
        limit: arguments_.limit ?? 10,
        next_cursor: null,
      };
    },
    async getCatalogItem() {
      return { product_id: "simulated-product-1", name: "Simulator Coffee", status: "published" };
    },
    async recordReceipt() {},
  };

  const app = createInternalMcpApp(configuration(connection), receipts, bridge, undefined, undefined, authority);
  const server = app.listen(0, "127.0.0.1");
  await once(server, "listening");
  const address = server.address();
  assert(address && typeof address === "object", "Loopback simulator did not start.");
  const baseUrl = `http://127.0.0.1:${address.port}`;

  try {
    const metadataResponse = await fetch(`${baseUrl}/.well-known/oauth-protected-resource`);
    assert.equal(metadataResponse.status, 200);
    const metadata = await metadataResponse.json();
    assert.equal(metadata.resource, RESOURCE_URI);
    assert.deepEqual(metadata.authorization_servers, [AUTHORIZATION_SERVER]);
    assert.deepEqual(metadata.scopes_supported, ["catalog:read", "profile:read"]);

    const initialize = {
      jsonrpc: "2.0",
      id: 1,
      method: "initialize",
      params: {
        protocolVersion: "2025-11-25",
        capabilities: {},
        clientInfo: { name: `phase2b-${profile.key}`, version: "1.0.0" },
      },
    };

    const challenged = await rpc(baseUrl, "", initialize);
    assert.equal(challenged.status, 401);
    assert.match(challenged.headers.get("www-authenticate") ?? "", /resource_metadata=/);

    const authorization = authority.beginAuthorization(profile);
    const firstPair = authority.exchangeAuthorizationCode(profile, authorization.code, authorization.verifier);

    const initialized = await rpc(baseUrl, firstPair.accessToken, initialize);
    assert.equal(initialized.status, 200);

    const toolsResponse = await rpc(baseUrl, firstPair.accessToken, {
      jsonrpc: "2.0",
      id: 2,
      method: "tools/list",
      params: {},
    });
    assert.equal(toolsResponse.status, 200);
    const toolsPayload = await toolsResponse.json();
    assert.deepEqual(toolsPayload.result.tools.map((tool) => tool.name), [
      "microgifter.account.get_connection_context",
      "microgifter.catalog.search",
      "microgifter.catalog.get_item",
    ]);

    const callResponse = await rpc(baseUrl, firstPair.accessToken, {
      jsonrpc: "2.0",
      id: 3,
      method: "tools/call",
      params: {
        name: "microgifter.catalog.search",
        arguments: { query: "coffee", limit: 5 },
      },
    });
    assert.equal(callResponse.status, 200);
    const callPayload = await callResponse.json();
    assert.equal(callPayload.error, undefined);
    assert.equal(bridgeCalls.length, 1);
    assert.equal(bridgeCalls[0].connectionId, connection.connectionId);
    assert.equal(receipts.all().some((receipt) => receipt.toolName === "microgifter.catalog.search"), true);

    const rotatedPair = authority.rotateRefreshToken(firstPair.refreshToken);
    const rotatedAccess = await rpc(baseUrl, rotatedPair.accessToken, initialize);
    assert.equal(rotatedAccess.status, 200);

    assert.throws(
      () => authority.rotateRefreshToken(firstPair.refreshToken),
      /replay detected/,
    );
    const replayRevoked = await rpc(baseUrl, rotatedPair.accessToken, initialize);
    assert.equal(replayRevoked.status, 401);

    const secondAuthorization = authority.beginAuthorization(profile);
    const revocablePair = authority.exchangeAuthorizationCode(
      profile,
      secondAuthorization.code,
      secondAuthorization.verifier,
    );
    authority.revokeByAccessToken(revocablePair.accessToken);
    const explicitlyRevoked = await rpc(baseUrl, revocablePair.accessToken, initialize);
    assert.equal(explicitlyRevoked.status, 401);

    return {
      profile: profile.key,
      display_name: profile.name,
      result: "passed",
      stages: [
        "protected_resource_discovery",
        "oauth_challenge",
        "authorization_code_pkce_s256",
        "mcp_initialize",
        "tools_list",
        "catalog_tool_call",
        "refresh_rotation",
        "refresh_replay_family_revocation",
        "explicit_revocation",
      ],
    };
  } finally {
    await closeServer(server);
  }
}

async function main() {
  refuseProduction();
  const requested = process.argv.find((value) => value.startsWith("--profile="))?.split("=", 2)[1] ?? "all";
  const profiles = requested === "all" ? PROFILES : PROFILES.filter((profile) => profile.key === requested);
  if (profiles.length === 0) throw new Error(`Unknown simulator profile: ${requested}`);

  const results = [];
  for (const profile of profiles) results.push(await runProfile(profile));

  const report = {
    simulator: "microgifter-mcp-external-agent-phase2b",
    mode: "loopback_sample_data_only",
    result: "passed",
    profiles: results,
    production_checks_still_required: [
      "public_dns",
      "public_tls",
      "nginx_reverse_proxy",
      "persistent_node_process",
      "exact_live_client_redirect_uri",
      "live_chatgpt_or_claude_interoperability",
    ],
  };
  process.stdout.write(`${JSON.stringify(report, null, 2)}\n`);
}

main().catch((error) => {
  process.stderr.write(`MCP external-agent simulator failed: ${error instanceof Error ? error.message : String(error)}\n`);
  process.exitCode = 1;
});
