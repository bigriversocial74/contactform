import { randomBytes, randomUUID } from "node:crypto";
import type { ConnectionContext } from "../contracts.js";
import {
  CanonicalBridgeError,
  signCanonicalBridgeRequest,
  type CanonicalBridgeConfig,
} from "./canonicalBridge.js";

export interface ResolvedAccessToken {
  readonly connection: ConnectionContext;
  readonly oauthClientId: string;
  readonly tokenFamilyId: string;
}

export interface OAuthTokenResolver {
  resolveAccessToken(tokenSha256: string, resourceUri: string): Promise<ResolvedAccessToken>;
}

function object(value: unknown, name: string): Readonly<Record<string, unknown>> {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    throw new CanonicalBridgeError(`Invalid ${name} response.`, "MCP_BRIDGE_RESPONSE_INVALID", 502);
  }
  return value as Readonly<Record<string, unknown>>;
}

function text(value: unknown, name: string): string {
  if (typeof value !== "string" || value.trim() === "") {
    throw new CanonicalBridgeError(`Invalid ${name} response.`, "MCP_BRIDGE_RESPONSE_INVALID", 502);
  }
  return value;
}

function stringArray(value: unknown, name: string): readonly string[] {
  if (!Array.isArray(value) || value.some((item) => typeof item !== "string" || item.trim() === "")) {
    throw new CanonicalBridgeError(`Invalid ${name} response.`, "MCP_BRIDGE_RESPONSE_INVALID", 502);
  }
  return [...new Set(value)].sort();
}

function connectionContext(data: Readonly<Record<string, unknown>>): ConnectionContext {
  const workspaceValue = data.workspace;
  let workspace: ConnectionContext["workspace"];
  if (workspaceValue !== null && workspaceValue !== undefined) {
    const workspaceObject = object(workspaceValue, "workspace");
    workspace = {
      type: text(workspaceObject.type, "workspace type"),
      id: text(workspaceObject.id, "workspace id"),
    };
  }
  const expiresAt = typeof data.expiresAt === "string" && data.expiresAt !== "" ? data.expiresAt : undefined;
  const tokenVersion = typeof data.tokenVersion === "number" && Number.isSafeInteger(data.tokenVersion)
    ? data.tokenVersion
    : 1;
  return {
    connectionId: text(data.connectionId, "connection id"),
    clientKey: text(data.clientKey, "client key"),
    userId: text(data.userId, "user id"),
    ...(workspace ? { workspace } : {}),
    scopes: stringArray(data.scopes, "scopes"),
    maximumOperationClass: "read",
    tokenVersion,
    ...(expiresAt ? { expiresAt } : {}),
  };
}

export class HttpOAuthTokenResolver implements OAuthTokenResolver {
  public constructor(private readonly config: CanonicalBridgeConfig) {
    if (!config.enabled) {
      throw new Error("The OAuth resolver cannot be created while the canonical bridge is disabled.");
    }
  }

  public async resolveAccessToken(tokenSha256: string, resourceUri: string): Promise<ResolvedAccessToken> {
    const body = JSON.stringify({
      request_id: randomUUID(),
      operation: "oauth.token.resolve",
      arguments: { token_sha256: tokenSha256, resource_uri: resourceUri },
    });
    const timestamp = String(Math.floor(Date.now() / 1000));
    const nonce = randomBytes(24).toString("base64url");
    const signature = signCanonicalBridgeRequest(this.config.secret, timestamp, nonce, body);
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), this.config.timeoutMs);

    let response: Response;
    try {
      response = await fetch(this.config.url, {
        method: "POST",
        headers: {
          accept: "application/json",
          "content-type": "application/json",
          "x-microgifter-mcp-timestamp": timestamp,
          "x-microgifter-mcp-nonce": nonce,
          "x-microgifter-mcp-signature": signature,
        },
        body,
        signal: controller.signal,
      });
    } catch (error) {
      const message = error instanceof Error && error.name === "AbortError"
        ? "The OAuth authority timed out."
        : "The OAuth authority is unavailable.";
      throw new CanonicalBridgeError(message, "MCP_BRIDGE_UNAVAILABLE", 503);
    } finally {
      clearTimeout(timeout);
    }

    const payload = await response.json().catch(() => null) as unknown;
    const envelope = object(payload, "OAuth bridge envelope");
    if (!response.ok || envelope.ok !== true) {
      const error = envelope.error && typeof envelope.error === "object" && !Array.isArray(envelope.error)
        ? envelope.error as Readonly<Record<string, unknown>>
        : {};
      throw new CanonicalBridgeError(
        typeof error.message === "string" && error.message !== "" ? error.message : "The OAuth authority rejected the request.",
        typeof error.code === "string" && error.code !== "" ? error.code : "MCP_OAUTH_RESOLUTION_FAILED",
        response.status,
      );
    }
    const data = object(envelope.data, "OAuth access token");
    return {
      connection: connectionContext(object(data.connection, "OAuth connection")),
      oauthClientId: text(data.oauthClientId, "OAuth client id"),
      tokenFamilyId: text(data.tokenFamilyId, "OAuth token family id"),
    };
  }
}
