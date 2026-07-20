import type { ConnectionContext } from "./contracts.js";
import type { CanonicalBridgeConfig } from "./bridge/canonicalBridge.js";

export interface InternalProtocolConfig {
  readonly platformEnabled: boolean;
  readonly internalHttpEnabled: boolean;
  readonly host: string;
  readonly port: number;
  readonly allowedOrigins: readonly string[];
  readonly allowedHosts: readonly string[];
  readonly tokenSha256: string;
  readonly rateLimitRequests: number;
  readonly rateLimitWindowMs: number;
  readonly connection: ConnectionContext;
  readonly bridge: CanonicalBridgeConfig;
}

function csv(value: string | undefined): readonly string[] {
  return (value ?? "")
    .split(",")
    .map((item) => item.trim())
    .filter((item) => item !== "");
}

function enabled(value: string | undefined): boolean {
  return value === "1" || value?.toLowerCase() === "true";
}

function integer(value: string | undefined, fallback: number): number {
  const parsed = Number.parseInt(value ?? "", 10);
  return Number.isSafeInteger(parsed) && parsed > 0 ? parsed : fallback;
}

export function loadInternalProtocolConfig(
  environment: Readonly<Record<string, string | undefined>> = process.env,
): InternalProtocolConfig {
  const scopes = csv(environment.MICROGIFTER_MCP_INTERNAL_SCOPES);
  const workspaceType = environment.MICROGIFTER_MCP_INTERNAL_WORKSPACE_TYPE?.trim();
  const workspaceId = environment.MICROGIFTER_MCP_INTERNAL_WORKSPACE_ID?.trim();

  const config: InternalProtocolConfig = {
    platformEnabled: enabled(environment.MICROGIFTER_MCP_ENABLED),
    internalHttpEnabled: enabled(environment.MICROGIFTER_MCP_INTERNAL_HTTP_ENABLED),
    host: environment.MICROGIFTER_MCP_INTERNAL_HOST?.trim() || "127.0.0.1",
    port: integer(environment.MICROGIFTER_MCP_INTERNAL_PORT, 8787),
    allowedOrigins: csv(environment.MICROGIFTER_MCP_ALLOWED_ORIGINS),
    allowedHosts: csv(environment.MICROGIFTER_MCP_ALLOWED_HOSTS),
    tokenSha256: environment.MICROGIFTER_MCP_INTERNAL_TOKEN_SHA256?.trim().toLowerCase() ?? "",
    rateLimitRequests: integer(environment.MICROGIFTER_MCP_RATE_LIMIT_REQUESTS, 120),
    rateLimitWindowMs: integer(environment.MICROGIFTER_MCP_RATE_LIMIT_WINDOW_MS, 300_000),
    connection: {
      connectionId: environment.MICROGIFTER_MCP_INTERNAL_CONNECTION_ID?.trim() || "internal-development",
      clientKey: environment.MICROGIFTER_MCP_INTERNAL_CLIENT_KEY?.trim() || "internal-development",
      userId: environment.MICROGIFTER_MCP_INTERNAL_USER_ID?.trim() || "development-user",
      ...(workspaceType && workspaceId ? { workspace: { type: workspaceType, id: workspaceId } } : {}),
      scopes,
      maximumOperationClass: "read",
      tokenVersion: 1,
    },
    bridge: {
      enabled: enabled(environment.MICROGIFTER_MCP_BRIDGE_ENABLED),
      url: environment.MICROGIFTER_MCP_BRIDGE_URL?.trim() ?? "",
      secret: environment.MICROGIFTER_MCP_BRIDGE_SECRET?.trim() ?? "",
      timeoutMs: integer(environment.MICROGIFTER_MCP_BRIDGE_TIMEOUT_MS, 8_000),
    },
  };

  validateInternalProtocolConfig(config);
  return config;
}

function validBridgeUrl(value: string): boolean {
  try {
    const url = new URL(value);
    if (url.protocol === "https:") return true;
    return url.protocol === "http:" && ["127.0.0.1", "localhost", "::1"].includes(url.hostname);
  } catch {
    return false;
  }
}

export function validateInternalProtocolConfig(config: InternalProtocolConfig): void {
  if (!config.platformEnabled && config.internalHttpEnabled) {
    throw new Error("Internal MCP HTTP cannot be enabled while the platform is disabled.");
  }
  if (config.internalHttpEnabled && !/^[a-f0-9]{64}$/.test(config.tokenSha256)) {
    throw new Error("Internal MCP HTTP requires a SHA-256 bearer token hash.");
  }
  if (config.internalHttpEnabled && !config.bridge.enabled && config.connection.scopes.length === 0) {
    throw new Error("Internal MCP HTTP requires at least one explicit scope when the canonical bridge is disabled.");
  }
  if (config.connection.maximumOperationClass !== "read") {
    throw new Error("The Phase 1 internal protocol is read-only.");
  }
  if (config.bridge.enabled) {
    if (!config.internalHttpEnabled) {
      throw new Error("The canonical bridge cannot be enabled while internal MCP HTTP is disabled.");
    }
    if (!validBridgeUrl(config.bridge.url)) {
      throw new Error("The canonical bridge requires HTTPS, except for localhost development.");
    }
    if (config.bridge.secret.length < 32) {
      throw new Error("The canonical bridge requires a secret of at least 32 characters.");
    }
    if (!/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(config.connection.connectionId)) {
      throw new Error("The canonical bridge requires a persisted MCP connection UUID.");
    }
  }
}
