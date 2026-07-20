import type { Server } from "node:http";
import { createMcpExpressApp } from "@modelcontextprotocol/sdk/server/express.js";
import { StreamableHTTPServerTransport } from "@modelcontextprotocol/sdk/server/streamableHttp.js";
import type { CanonicalBridge } from "../bridge/canonicalBridge.js";
import { CanonicalBridgeError } from "../bridge/canonicalBridge.js";
import type { InternalProtocolConfig } from "../protocolConfig.js";
import { authenticateInternalBearer } from "../auth/internalToken.js";
import { isOriginAllowed } from "./origin.js";
import { FixedWindowRateLimiter } from "../rateLimit.js";
import type { InvocationReceiptSink } from "../receipts.js";
import { createInternalMcpServer } from "../tools/registry.js";

function jsonError(code: number, message: string) {
  return { jsonrpc: "2.0", id: null, error: { code, message } };
}

export function createInternalMcpApp(
  config: InternalProtocolConfig,
  receipts: InvocationReceiptSink,
  bridge?: CanonicalBridge,
) {
  const app = createMcpExpressApp(
    config.allowedHosts.length > 0
      ? { host: config.host, allowedHosts: [...config.allowedHosts] }
      : { host: config.host },
  );
  const limiter = new FixedWindowRateLimiter(config.rateLimitRequests, config.rateLimitWindowMs);

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

    const principal = authenticateInternalBearer(request.headers, config.tokenSha256, config.connection);
    if (!principal) {
      response.setHeader("WWW-Authenticate", 'Bearer realm="microgifter-mcp-internal"');
      response.status(401).json(jsonError(-32001, "Authentication required."));
      return;
    }

    let connection = principal.connection;
    if (config.bridge.enabled) {
      if (!bridge) {
        response.status(503).json(jsonError(-32050, "Canonical authority is unavailable."));
        return;
      }
      try {
        connection = await bridge.resolveConnection(config.connection.connectionId);
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
    const transport = new StreamableHTTPServerTransport({
      sessionIdGenerator: undefined,
      enableJsonResponse: true,
    });

    try {
      await server.connect(transport);
      await transport.handleRequest(request, response, request.body);
    } catch {
      if (!response.headersSent) {
        response.status(500).json(jsonError(-32603, "Internal server error."));
      }
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
): Promise<Server> {
  const app = createInternalMcpApp(config, receipts, bridge);
  return new Promise((resolve, reject) => {
    const server = app.listen(config.port, config.host, () => resolve(server));
    server.once("error", reject);
  });
}
