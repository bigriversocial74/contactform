import { existsSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, resolve } from "node:path";

const here = dirname(fileURLToPath(import.meta.url));
const serviceRoot = resolve(here, "..");
const repositoryRoot = resolve(serviceRoot, "../..");
const strict = process.argv.includes("--strict");

function nodeMajor() {
  return Number.parseInt(process.versions.node.split(".", 1)[0] ?? "0", 10);
}

function status(name, passed, detail) {
  return { name, status: passed ? "passed" : "pending", detail };
}

function envPresent(name) {
  return typeof process.env[name] === "string" && process.env[name].trim() !== "";
}

function exactEnv(name, expected) {
  if (!envPresent(name)) return status(name, false, "not configured");
  return status(name, process.env[name] === expected, process.env[name] === expected ? "configured" : "unexpected value");
}

const requiredFiles = [
  "docs/MICROGIFTER_MCP_INSTALLATION_AND_ACTIVATION.md",
  "docs/MICROGIFTER_MCP_PRODUCTION_VPS_DEPLOYMENT_V1.md",
  "docs/MICROGIFTER_MCP_EXTERNAL_AGENT_AUTHORIZATION_PHASE2A.md",
  "docs/MICROGIFTER_MCP_EXTERNAL_AGENT_SIMULATOR_PHASE2B.md",
  "deploy/vps/mcp.env.example",
  "deploy/vps/php-bridge.env.example",
  "deploy/vps/php-oauth.env.example",
  "deploy/vps/nginx/mcp.microgifter.com.conf.template",
  "services/mcp/scripts/simulate-external-agent.mjs",
];

const codeChecks = [
  status("node_20_or_newer", nodeMajor() >= 20, `detected Node ${process.versions.node}`),
  ...requiredFiles.map((path) => status(path, existsSync(resolve(repositoryRoot, path)), existsSync(resolve(repositoryRoot, path)) ? "present" : "missing")),
];

const environmentChecks = [
  exactEnv("MICROGIFTER_MCP_ENABLED", "true"),
  exactEnv("MICROGIFTER_MCP_INTERNAL_HTTP_ENABLED", "true"),
  exactEnv("MICROGIFTER_MCP_EXTERNAL_OAUTH_ENABLED", "true"),
  exactEnv("MICROGIFTER_MCP_BRIDGE_ENABLED", "true"),
  exactEnv("MICROGIFTER_MCP_RESOURCE_URI", "https://mcp.microgifter.com/mcp"),
  exactEnv("MICROGIFTER_MCP_AUTHORIZATION_SERVER", "https://microgifter.com"),
  exactEnv("MICROGIFTER_MCP_PROTECTED_RESOURCE_METADATA_URL", "https://mcp.microgifter.com/.well-known/oauth-protected-resource"),
  exactEnv("MICROGIFTER_MCP_PUBLIC_BASE_URL", "https://mcp.microgifter.com"),
  status("MICROGIFTER_MCP_ALLOWED_HOSTS", envPresent("MICROGIFTER_MCP_ALLOWED_HOSTS"), envPresent("MICROGIFTER_MCP_ALLOWED_HOSTS") ? "configured" : "not configured"),
  status("MICROGIFTER_MCP_BRIDGE_URL", envPresent("MICROGIFTER_MCP_BRIDGE_URL"), envPresent("MICROGIFTER_MCP_BRIDGE_URL") ? "configured" : "not configured"),
  status("MICROGIFTER_MCP_BRIDGE_SECRET", envPresent("MICROGIFTER_MCP_BRIDGE_SECRET") && process.env.MICROGIFTER_MCP_BRIDGE_SECRET.length >= 32, envPresent("MICROGIFTER_MCP_BRIDGE_SECRET") ? "present; value not displayed" : "not configured"),
  status("MICROGIFTER_MCP_INTERNAL_CONNECTION_ID", envPresent("MICROGIFTER_MCP_INTERNAL_CONNECTION_ID"), envPresent("MICROGIFTER_MCP_INTERNAL_CONNECTION_ID") ? "configured" : "not configured"),
];

const codeReady = codeChecks.every((check) => check.status === "passed");
const environmentReady = environmentChecks.every((check) => check.status === "passed");
const report = {
  report: "microgifter-mcp-external-agent-readiness-phase2b",
  code_ready: codeReady,
  environment_ready: environmentReady,
  production_ready: false,
  code_checks: codeChecks,
  environment_checks: environmentChecks,
  live_checks_required: [
    "DNS for mcp.microgifter.com resolves to the intended VPS",
    "TLS certificate is valid",
    "Nginx proxies only to the loopback Node listener",
    "health and readiness endpoints return success",
    "authorization and protected-resource metadata are public",
    "an approved client completes an exact redirect and PKCE exchange",
    "MCP initialization, tool discovery, and revocation work from the live client",
  ],
  note: "No secret values are included in this report. Production readiness remains false until live checks are completed.",
};

process.stdout.write(`${JSON.stringify(report, null, 2)}\n`);
if (strict && (!codeReady || !environmentReady)) process.exitCode = 2;
