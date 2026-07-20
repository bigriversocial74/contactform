import process from "node:process";

const baseUrl = (process.env.MCP_SMOKE_BASE_URL || process.argv[2] || "").replace(/\/$/, "");
const bearerToken = process.env.MCP_SMOKE_BEARER_TOKEN || "";
const origin = process.env.MCP_SMOKE_ORIGIN || "";
const timeoutMs = Number.parseInt(process.env.MCP_SMOKE_TIMEOUT_MS || "10000", 10);

if (!baseUrl || !/^https?:\/\//i.test(baseUrl)) {
  throw new Error("Set MCP_SMOKE_BASE_URL to the MCP service origin.");
}
if (!bearerToken) {
  throw new Error("Set MCP_SMOKE_BEARER_TOKEN to the raw one-time bearer token.");
}

const requestHeaders = {
  "content-type": "application/json",
  accept: "application/json, text/event-stream",
  authorization: `Bearer ${bearerToken}`,
  ...(origin ? { origin } : {}),
};

async function fetchWithTimeout(url, options = {}) {
  return fetch(url, { ...options, signal: AbortSignal.timeout(timeoutMs) });
}

async function requireJson(response, expectedStatus, label) {
  const text = await response.text();
  let body;
  try {
    body = text ? JSON.parse(text) : {};
  } catch {
    throw new Error(`${label} returned non-JSON content with HTTP ${response.status}.`);
  }
  if (response.status !== expectedStatus) {
    throw new Error(`${label} failed with HTTP ${response.status}: ${JSON.stringify(body)}`);
  }
  return body;
}

const health = await requireJson(await fetchWithTimeout(`${baseUrl}/health`), 200, "health");
if (health.liveness !== "ok") throw new Error("Health endpoint did not report liveness=ok.");

const ready = await requireJson(await fetchWithTimeout(`${baseUrl}/ready`), 200, "readiness");
if (ready.readiness !== "ready") throw new Error("Readiness endpoint did not report ready.");

const initialize = await requireJson(
  await fetchWithTimeout(`${baseUrl}/mcp`, {
    method: "POST",
    headers: requestHeaders,
    body: JSON.stringify({
      jsonrpc: "2.0",
      id: 1,
      method: "initialize",
      params: {
        protocolVersion: "2025-11-25",
        capabilities: {},
        clientInfo: { name: "microgifter-vps-smoke", version: "1.0.0" },
      },
    }),
  }),
  200,
  "initialize",
);
if (initialize?.result?.protocolVersion !== "2025-11-25") {
  throw new Error("MCP protocol negotiation returned an unexpected revision.");
}

const tools = await requireJson(
  await fetchWithTimeout(`${baseUrl}/mcp`, {
    method: "POST",
    headers: requestHeaders,
    body: JSON.stringify({ jsonrpc: "2.0", id: 2, method: "tools/list", params: {} }),
  }),
  200,
  "tools/list",
);
const toolNames = Array.isArray(tools?.result?.tools)
  ? tools.result.tools.map((tool) => tool?.name).filter((name) => typeof name === "string")
  : [];
for (const required of [
  "microgifter.account.get_connection_context",
  "microgifter.catalog.search",
  "microgifter.catalog.get_item",
]) {
  if (!toolNames.includes(required)) throw new Error(`Required MCP tool is missing: ${required}`);
}

process.stdout.write(`${JSON.stringify({
  ok: true,
  service: health.service,
  release: health.release,
  base_url: baseUrl,
  protocol_version: initialize.result.protocolVersion,
  tools: toolNames,
  bearer_token_emitted: false,
}, null, 2)}\n`);
