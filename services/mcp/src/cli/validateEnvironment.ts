import { loadInternalProtocolConfig } from "../protocolConfig.js";
import { resolveRuntimeConfig } from "../runtime.js";

try {
  const config = loadInternalProtocolConfig();
  const runtime = resolveRuntimeConfig(config.runtime);
  process.stdout.write(`${JSON.stringify({
    ok: true,
    service: "microgifter-mcp",
    environment: runtime.environment,
    release: runtime.release,
    host: config.host,
    port: config.port,
    public_base_url: runtime.publicBaseUrl,
    bridge_enabled: config.bridge.enabled,
    allowed_hosts: config.allowedHosts,
    allowed_origins_count: config.allowedOrigins.length,
    connection_id_configured: /^[0-9a-f-]{36}$/i.test(config.connection.connectionId),
    bearer_hash_configured: /^[a-f0-9]{64}$/.test(config.tokenSha256),
    secret_values_emitted: false,
  }, null, 2)}\n`);
} catch (error) {
  const message = error instanceof Error ? error.message : "Unknown configuration error.";
  process.stderr.write(`${JSON.stringify({ ok: false, service: "microgifter-mcp", error: message })}\n`);
  process.exit(1);
}
