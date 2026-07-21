import type { IncomingHttpHeaders } from "node:http";
import type { OAuthTokenResolver, ResolvedAccessToken } from "../bridge/oauthBridge.js";
import { extractBearerToken, hashBearerToken } from "./internalToken.js";

export interface ExternalOAuthConfig {
  readonly enabled: boolean;
  readonly resourceUri: string;
  readonly authorizationServer: string;
  readonly protectedResourceMetadataUrl: string;
  readonly allowInternalBearer: boolean;
}

export interface ExternalOAuthPrincipal extends ResolvedAccessToken {
  readonly authenticationType: "oauth_access_token";
}

export function protectedResourceMetadata(config: ExternalOAuthConfig, scopes: readonly string[]) {
  return {
    resource: config.resourceUri,
    authorization_servers: [config.authorizationServer],
    bearer_methods_supported: ["header"],
    scopes_supported: [...new Set(scopes)].sort(),
    resource_name: "Microgifter MCP",
  };
}

export function oauthChallenge(config: ExternalOAuthConfig, scopes: readonly string[] = []): string {
  const parts = [
    'Bearer realm="microgifter-mcp"',
    `resource_metadata="${config.protectedResourceMetadataUrl}"`,
  ];
  if (scopes.length > 0) {
    parts.push(`scope="${[...new Set(scopes)].sort().join(" ")}"`);
  }
  return parts.join(", ");
}

export async function authenticateExternalOAuth(
  headers: IncomingHttpHeaders,
  resolver: OAuthTokenResolver,
  config: ExternalOAuthConfig,
): Promise<ExternalOAuthPrincipal | null> {
  const token = extractBearerToken(headers);
  if (!token || token.length < 16 || token.length > 2048) return null;
  const resolved = await resolver.resolveAccessToken(hashBearerToken(token), config.resourceUri);
  return { ...resolved, authenticationType: "oauth_access_token" };
}
