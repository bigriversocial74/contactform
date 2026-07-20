import { randomUUID } from "node:crypto";
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import * as z from "zod/v4";
import type { ConnectionContext } from "../contracts.js";
import type { InvocationReceiptSink } from "../receipts.js";

export interface ToolRegistryDependencies {
  readonly connection: ConnectionContext;
  readonly receipts: InvocationReceiptSink;
}

function hasScope(connection: ConnectionContext, scope: string): boolean {
  return connection.scopes.includes(scope);
}

function errorResult(code: string, message: string) {
  return {
    isError: true as const,
    content: [{ type: "text" as const, text: JSON.stringify({ ok: false, error: { code, message } }) }],
  };
}

export function createInternalMcpServer(dependencies: ToolRegistryDependencies): McpServer {
  const server = new McpServer(
    { name: "microgifter-mcp", version: "0.1.0", description: "Microgifter internal read-only MCP development server" },
    { capabilities: { tools: { listChanged: false } } },
  );

  if (hasScope(dependencies.connection, "profile:read")) {
    server.registerTool(
      "microgifter.account.get_connection_context",
      {
        description: "Return the authenticated Microgifter connection and workspace context without private account fields.",
        inputSchema: {},
        annotations: { readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false },
      },
      async () => {
        const requestId = randomUUID();
        const startedAt = new Date().toISOString();
        const output = {
          connection_id: dependencies.connection.connectionId,
          client_key: dependencies.connection.clientKey,
          user_id: dependencies.connection.userId,
          workspace: dependencies.connection.workspace ?? null,
          scopes: [...dependencies.connection.scopes].sort(),
          maximum_operation_class: dependencies.connection.maximumOperationClass,
          token_version: dependencies.connection.tokenVersion,
        };
        await dependencies.receipts.record({
          requestId,
          connectionId: dependencies.connection.connectionId,
          toolName: "microgifter.account.get_connection_context",
          operationClass: "read",
          requiredScope: "profile:read",
          resultStatus: "success",
          startedAt,
          completedAt: new Date().toISOString(),
        });
        return {
          content: [{ type: "text", text: JSON.stringify({ ok: true, request_id: requestId, data: output }) }],
          structuredContent: { ok: true, request_id: requestId, data: output },
        };
      },
    );
  }

  if (hasScope(dependencies.connection, "catalog:read")) {
    server.registerTool(
      "microgifter.catalog.search",
      {
        description: "Search currently published Microgifter catalog products through canonical visibility rules.",
        inputSchema: {
          query: z.string().trim().max(200).optional(),
          limit: z.number().int().min(1).max(25).default(10),
          cursor: z.string().max(500).optional(),
        },
        annotations: { readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false },
      },
      async () => errorResult("MICROGIFTER_TOOL_DISABLED", "The canonical catalog bridge is not enabled in this release."),
    );

    server.registerTool(
      "microgifter.catalog.get_item",
      {
        description: "Return one currently published Microgifter catalog item through the canonical public projection.",
        inputSchema: {
          product_id: z.string().uuid(),
          slug: z.string().trim().max(190).optional(),
        },
        annotations: { readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false },
      },
      async () => errorResult("MICROGIFTER_TOOL_DISABLED", "The canonical catalog bridge is not enabled in this release."),
    );
  }

  return server;
}
