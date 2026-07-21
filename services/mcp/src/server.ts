import { HttpCanonicalBridge } from "./bridge/canonicalBridge.js";
import { HttpOAuthTokenResolver } from "./bridge/oauthBridge.js";
import { loadInternalProtocolConfig, resolveExternalOAuthConfig } from "./protocolConfig.js";
import { InMemoryInvocationReceiptSink, type InvocationReceiptSink } from "./receipts.js";
import { listenInternalMcp } from "./http/app.js";
import { ServiceRuntimeState, createRuntimeLogger, resolveRuntimeConfig } from "./runtime.js";

const config = loadInternalProtocolConfig();
const runtimeConfig = resolveRuntimeConfig(config.runtime);
const externalOAuth = resolveExternalOAuthConfig(config.externalOAuth);
const logger = createRuntimeLogger(runtimeConfig.logLevel);
const runtime = new ServiceRuntimeState();

async function main(): Promise<void> {
  if (!config.platformEnabled || !config.internalHttpEnabled) {
    throw new Error("Internal MCP HTTP is disabled.");
  }

  const bridge = config.bridge.enabled
    ? new HttpCanonicalBridge(config.bridge, config.connection.connectionId)
    : undefined;
  const oauthResolver = externalOAuth.enabled
    ? new HttpOAuthTokenResolver(config.bridge)
    : undefined;
  const receipts: InvocationReceiptSink = bridge
    ? { record: async (receipt) => bridge.recordReceipt(receipt) }
    : new InMemoryInvocationReceiptSink();

  logger.info("service.starting", {
    environment: runtimeConfig.environment,
    release: runtimeConfig.release,
    host: config.host,
    port: config.port,
    bridgeEnabled: config.bridge.enabled,
    externalOAuthEnabled: externalOAuth.enabled,
    internalBearerFallback: externalOAuth.allowInternalBearer,
    publicBaseUrl: runtimeConfig.publicBaseUrl,
  });

  const server = await listenInternalMcp(config, receipts, bridge, runtime, logger, oauthResolver);
  server.keepAliveTimeout = 65_000;
  server.headersTimeout = 66_000;
  server.requestTimeout = 300_000;

  logger.info("service.started", {
    environment: runtimeConfig.environment,
    release: runtimeConfig.release,
    host: config.host,
    port: config.port,
    externalOAuthEnabled: externalOAuth.enabled,
  });

  let shutdownPromise: Promise<void> | undefined;
  const shutdown = (reason: string, exitCode: number): Promise<void> => {
    shutdownPromise ??= (async () => {
      runtime.beginDrain();
      logger.info("service.draining", {
        reason,
        activeRequests: runtime.activeRequests,
        graceMs: runtimeConfig.shutdownGraceMs,
      });

      const serverClosed = new Promise<void>((resolve) => {
        server.close(() => resolve());
      });
      const idle = await runtime.waitForIdle(runtimeConfig.shutdownGraceMs);
      if (!idle) {
        logger.warn("service.drain_timeout", {
          activeRequests: runtime.activeRequests,
          graceMs: runtimeConfig.shutdownGraceMs,
        });
        server.closeIdleConnections?.();
        server.closeAllConnections?.();
      }

      await Promise.race([
        serverClosed,
        new Promise<void>((resolve) => {
          const timer = setTimeout(resolve, 2_000);
          timer.unref();
        }),
      ]);
      logger.info("service.stopped", { reason, exitCode });
      process.exit(exitCode);
    })();
    return shutdownPromise;
  };

  process.once("SIGINT", () => void shutdown("SIGINT", 0));
  process.once("SIGTERM", () => void shutdown("SIGTERM", 0));
  process.once("uncaughtException", (error) => {
    logger.error("process.uncaught_exception", { error });
    void shutdown("uncaughtException", 1);
  });
  process.once("unhandledRejection", (error) => {
    logger.error("process.unhandled_rejection", { error });
    void shutdown("unhandledRejection", 1);
  });
}

main().catch((error) => {
  logger.error("service.start_failed", { error });
  process.exit(1);
});
