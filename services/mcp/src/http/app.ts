import type { Server } from "node:http";
import { createMcpExpressApp } from "@modelcontextprotocol/sdk/server/express.js";
import { StreamableHTTPServerTransport } from "@modelcontextprotocol/sdk/server/streamableHttp.js";
import type { CanonicalBridge } from "../bridge/canonicalBridge.js";
import { CanonicalBridgeError } from "../bridge/canonicalBridge.js";
import { resolveExternalOAuthConfig, type InternalProtocolConfig } from "../protocolConfig.js";
import { authenticateInternalBearer } from "../auth/internalToken.js";
import {
  authenticateExternalOAuth,
  oauthChallenge,
  protectedResourceMetadata,
  type ExternalOAuthConfig,
} from "../auth/externalOAuth.js";
import { isOriginAllowed } from "./origin.js";
import { FixedWindowRateLimiter } from "../rateLimit.js";
import type { InvocationReceiptSink } from "../receipts.js";
import { createInternalMcpServer } from "../tools/registry.js";
import {
  ServiceRuntimeState,
  createRuntimeLogger,
  resolveRuntimeConfig,
  safeRequestId,
  type RuntimeLogger,
} from "../runtime.js";
import type { ConnectionContext } from "../contracts.js";
import type { OAuthTokenResolver } from "../bridge/oauthBridge.js";

function jsonError(code: number, message: string) {
  return { jsonrpc: "2.0", id: null, error: { code, message } };
}

function servicePayload(config: InternalProtocolConfig, runtime: ServiceRuntimeState) {
  const runtimeConfig = resolveRuntimeConfig(config.runtime);
  const snapshot = runtime.snapshot();
  return {
    service: "microgifter-mcp",
    release: runtimeConfig.release,
    environment: runtimeConfig.environment,
    status: snapshot.status,
    uptime_seconds: snapshot.uptimeSeconds,
  };
}

function oauthUnauthorized(config: ExternalOAuthConfig, response: import("express").Response): void {
  response.setHeader("WWW-Authenticate", oauthChallenge(config));
  response.status(401).json(jsonError(-32001, "OAuth authorization required."));
}

export function createInternalMcpApp(
  config: InternalProtocolConfig,
  receipts: InvocationReceiptSink,
  bridge?: CanonicalBridge,
  runtime = new ServiceRuntimeState(),
  logger: RuntimeLogger = createRuntimeLogger(resolveRuntimeConfig(config.runtime).logLevel),
  oauthResolver?: OAuthTokenResolver,
) {
  const externalOAuth = resolveExternalOAuthConfig(config.externalOAuth);
  const effectiveAllowedHosts = Array.from(new Set([
    ...config.allowedHosts,
    config.host,
    "127.0.0.1",
    "localhost",
  ]));
  const app = createMcpExpressApp({ host: config.host, allowedHosts: effectiveAllowedHosts });
  const limiter = new FixedWindowRateLimiter(config.rateLimitRequests, config.rateLimitWindowMs);
  app.disable("x-powered-by");

  app.use((request, response, next) => {
    const requestId = safeRequestId(request.headers["x-request-id"]);
    response.setHeader("X-Request-ID", requestId);
    response.setHeader("X-Content-Type-Options", "nosniff");
    response.setHeader("Cache-Control", "no-store");
    if (request.path !== "/mcp") {
      next();
      return;
    }
    if (runtime.draining) {
      response.status(503).json(jsonError(-32053, "Service is draining."));
      return;
    }
    const startedAt = Date.now();
    const finishRequest = runtime.beginRequest();
    let logged = false;
    const finish = () => {
      if (logged) return;
      logged = true;
      finishRequest();
      logger.info("http.request.completed", {
        requestId,
        method: request.method,
        path: request.path,
        status: response.statusCode,
        durationMs: Date.now() - startedAt,
      });
    };
    response.once("finish", finish);
    response.once("close", finish);
    next();
  });

  app.get("/health", (_request, response) => {
    response.status(200).json({ ...servicePayload(config, runtime), liveness: "ok" });
  });

  app.get("/ready", async (_request, response) => {
    if (runtime.draining || !config.platformEnabled || !config.internalHttpEnabled) {
      response.status(503).json({ ...servicePayload(config, runtime), readiness: "not_ready" });
      return;
    }
    if (config.bridge.enabled) {
      if (!bridge) {
        response.status(503).json({ ...servicePayload(config, runtime), readiness: "not_ready" });
        return;
      }
      try {
        await bridge.resolveConnection(config.connection.connectionId);
      } catch (error) {
        logger.warn("service.readiness.bridge_failed", { error, connectionId: config.connection.connectionId });
        response.status(503).json({ ...servicePayload(config, runtime), readiness: "not_ready" });
        return;
      }
    }
    response.status(200).json({
      ...servicePayload(config, runtime),
      readiness: "ready",
      external_oauth: externalOAuth.enabled,
    });
  });

  const metadataHandler = (_request: import("express").Request, response: import("express").Response) => {
    if (!externalOAuth.enabled) {
      response.status(404).json({ error: "not_found" });
      return;
    }
    response.setHeader("Access-Control-Allow-Origin", "*");
    response.setHeader("Cache-Control", "public, max-age=300");
    response.status(200).json(protectedResourceMetadata(externalOAuth, ["profile:read", "catalog:read"]));
  };
  app.get("/.well-known/oauth-protected-resource", metadataHandler);
  app.get("/.well-known/oauth-protected-resource/mcp", metadataHandler);

  app.post("/mcp", async (request, response) => {
    if (!config.platformEnabled || !config.internalHttpEnabled) {
      response.status(404).json(jsonError(-32004, "Not found."));
      return;
    }
    const origin = typeof request.headers.origin === "string" ? request.headers.origin : undefined;
    if (!isOriginAllowed(origin, config.allowedOrigins)) {
      response.status(403).json(jsonError(-32003, "Origin is not allowed."));
      return;
    }

    let connection: ConnectionContext | null = null;
    let authenticationType = "none";
    if (externalOAuth.enabled) {
      if (externalOAuth.allowInternalBearer) {
        const internal = authenticateInternalBearer(request.headers, config.tokenSha256, config.connection);
        if (internal) {
          connection = internal.connection;
          authenticationType = internal.authenticationType;
        }
      }

      if (!connection) {
        if (!oauthResolver) {
          response.status(503).json(jsonError(-32050, "OAuth authority is unavailable."));
          return;
        }
        try {
          const principal = await authenticateExternalOAuth(request.headers, oauthResolver, externalOAuth);
          if (principal) {
            connection = principal.connection;
            authenticationType = principal.authenticationType;
          }
        } catch (error) {
          if (error instanceof CanonicalBridgeError && [401, 403].includes(error.status)) {
            if (error.status === 401) oauthUnauthorized(externalOAuth, response);
            else response.status(403).json(jsonError(-32003, "The OAuth token is not authorized."));
            return;
          }
          response.status(503).json(jsonError(-32050, "Canonical authority is unavailable."));
          return;
        }
      }

      if (!connection) {
        oauthUnauthorized(externalOAuth, response);
        return;
      }
    } else {
      const principal = authenticateInternalBearer(request.headers, config.tokenSha256, config.connection);
      if (!principal) {
        response.setHeader("WWW-Authenticate", 'Bearer realm="microgifter-mcp-internal"');
        response.status(401).json(jsonError(-32001, "Authentication required."));
        return;
      }
      connection = principal.connection;
      authenticationType = principal.authenticationType;
    }

    if (authenticationType === "internal_bearer_sha256" && config.bridge.enabled) {
      if (!bridge) {
        response.status(503).json(jsonError(-32050, "Canonical authority is unavailable."));
        return;
      }
      try {
        connection = await bridge.resolveConnection(connection.connectionId);
      } catch (error) {
        const status = error instanceof CanonicalBridgeError && [401, 403].includes(error.status) ? error.status : 503;
        const message = status === 403 ? "The Microgifter connection is not authorized." : "Canonical authority is unavailable.";
        response.status(status).json(jsonError(status === 403 ? -32003 : -32050, message));
        return;
      }
    }

    const rate = limiter.consume(connection.connectionId);
    response.setHeader("X-RateLimit-Remaining", String(rate.remaining));
    if (!rate.allowed) {
      response.setHeader("Retry-After", String(rate.retryAfterSeconds));
      response.status(429).json(jsonError(-32029, "Rate limit exceeded."));
      return;
    }

    const server = createInternalMcpServer({ connection, receipts, ...(bridge ? { bridge } : {}) });
    const transport = new StreamableHTTPServerTransport({ sessionIdGenerator: undefined, enableJsonResponse: true });
    try {
      await server.connect(transport);
      await transport.handleRequest(request, response, request.body);
    } catch (error) {
      logger.error("mcp.request.failed", {
        requestId: response.getHeader("X-Request-ID"),
        authenticationType,
        connectionId: connection.connectionId,
        error,
      });
      if (!response.headersSent) response.status(500).json(jsonError(-32603, "Internal server error."));
    } finally {
      await transport.close().catch(() => undefined);
      await server.close().catch(() => undefined);
    }
  });

  app.get("/mcp", (_request, response) => {
    response.status(405).json(jsonError(-32000, "Method not allowed."));
  });
  app.delete("/mcp", (_request, response) => {
    response.status(405).json(jsonError(-32000, "Method not allowed."));
  });
  return app;
}

export function listenInternalMcp(
  config: InternalProtocolConfig,
  receipts: InvocationReceiptSink,
  bridge?: CanonicalBridge,
  runtime = new ServiceRuntimeState(),
  logger: RuntimeLogger = createRuntimeLogger(resolveRuntimeConfig(config.runtime).logLevel),
  oauthResolver?: OAuthTokenResolver,
): Promise<Server> {
  const app = createInternalMcpApp(config, receipts, bridge, runtime, logger, oauthResolver);
  return new Promise((resolve, reject) => {
    const server = app.listen(config.port, config.host, () => resolve(server));
    server.once("error", reject);
  });
}
