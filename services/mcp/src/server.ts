import { HttpCanonicalBridge } from "./bridge/canonicalBridge.js";
import { loadInternalProtocolConfig } from "./protocolConfig.js";
import { InMemoryInvocationReceiptSink, type InvocationReceiptSink } from "./receipts.js";
import { listenInternalMcp } from "./http/app.js";

const config = loadInternalProtocolConfig();
if (!config.platformEnabled || !config.internalHttpEnabled) {
  throw new Error("Internal MCP HTTP is disabled.");
}

const bridge = config.bridge.enabled
  ? new HttpCanonicalBridge(config.bridge, config.connection.connectionId)
  : undefined;
const receipts: InvocationReceiptSink = bridge
  ? { record: async (receipt) => bridge.recordReceipt(receipt) }
  : new InMemoryInvocationReceiptSink();
const server = await listenInternalMcp(config, receipts, bridge);

const shutdown = () => {
  server.close(() => process.exit(0));
};
process.once("SIGINT", shutdown);
process.once("SIGTERM", shutdown);
