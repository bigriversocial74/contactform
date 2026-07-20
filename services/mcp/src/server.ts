import { loadInternalProtocolConfig } from "./protocolConfig.js";
import { InMemoryInvocationReceiptSink } from "./receipts.js";
import { listenInternalMcp } from "./http/app.js";

const config = loadInternalProtocolConfig();
if (!config.platformEnabled || !config.internalHttpEnabled) {
  throw new Error("Internal MCP HTTP is disabled.");
}

const receipts = new InMemoryInvocationReceiptSink();
const server = await listenInternalMcp(config, receipts);

const shutdown = () => {
  server.close(() => process.exit(0));
};
process.once("SIGINT", shutdown);
process.once("SIGTERM", shutdown);
