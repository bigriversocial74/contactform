import { createHash, randomUUID } from "node:crypto";
import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import * as z from "zod/v4";
import type {
  CanonicalBridge,
  CreatorCampaignReadArguments,
  CreatorCampaignReadOperation,
} from "../bridge/canonicalBridge.js";
import { CanonicalBridgeError } from "../bridge/canonicalBridge.js";
import type { ConnectionContext } from "../contracts.js";
import type { InvocationReceipt, InvocationReceiptSink } from "../receipts.js";

export interface CreatorCampaignToolDependencies {
  readonly connection: ConnectionContext;
  readonly receipts: InvocationReceiptSink;
  readonly bridge?: CanonicalBridge;
}

const readAnnotations = {
  readOnlyHint: true,
  destructiveHint: false,
  idempotentHint: true,
  openWorldHint: false,
} as const;

const pageSchema = {
  limit: z.number().int().min(1).max(100).default(25),
  cursor: z.string().regex(/^[0-9]{1,8}$/).optional(),
};

const campaignFilterSchema = {
  campaign_id: z.string().trim().min(8).max(40).regex(/^[A-Za-z0-9][A-Za-z0-9_-]+$/).optional(),
  status: z.string().trim().min(1).max(40).optional(),
  ...pageSchema,
};

function hasScope(connection: ConnectionContext, scope: string): boolean {
  return connection.scopes.includes(scope);
}

function fingerprint(value: unknown): string {
  return createHash("sha256").update(JSON.stringify(value ?? {})).digest("hex");
}

function result(payload: Readonly<Record<string, unknown>>, isError = false) {
  return {
    ...(isError ? { isError: true as const } : {}),
    content: [{ type: "text" as const, text: JSON.stringify(payload) }],
    structuredContent: payload,
  };
}

async function recordReceipt(
  sink: InvocationReceiptSink,
  values: Omit<InvocationReceipt, "startedAt" | "completedAt"> & { readonly startedAt: string },
): Promise<void> {
  await sink.record({ ...values, completedAt: new Date().toISOString() });
}

async function runCreatorCampaignRead(
  dependencies: CreatorCampaignToolDependencies,
  toolName: string,
  requiredScope: string,
  operation: CreatorCampaignReadOperation,
  input: CreatorCampaignReadArguments,
) {
  const bridge = dependencies.bridge;
  if (!bridge?.creatorCampaignRead) {
    return result({
      ok: false,
      error: {
        code: "MICROGIFTER_TOOL_DISABLED",
        message: "The canonical Creator Campaign bridge is not enabled.",
      },
    }, true);
  }

  const requestId = randomUUID();
  const startedAt = new Date().toISOString();
  const started = Date.now();
  try {
    const data = await bridge.creatorCampaignRead(
      dependencies.connection.connectionId,
      operation,
      input,
    );
    const items = data.items;
    await recordReceipt(dependencies.receipts, {
      requestId,
      connectionId: dependencies.connection.connectionId,
      toolName,
      operationClass: "read",
      requiredScope,
      inputFingerprint: fingerprint(input),
      resultStatus: "success",
      httpStatus: 200,
      durationMs: Date.now() - started,
      recordCount: Array.isArray(items) ? items.length : 1,
      startedAt,
    });
    return result({ ok: true, request_id: requestId, data });
  } catch (error) {
    const failure = error instanceof CanonicalBridgeError
      ? error
      : new CanonicalBridgeError(
        "The canonical Creator Campaign service could not complete the request.",
        "MCP_CREATOR_CAMPAIGN_SERVICE_FAILED",
        500,
      );
    await recordReceipt(dependencies.receipts, {
      requestId,
      connectionId: dependencies.connection.connectionId,
      toolName,
      operationClass: "read",
      requiredScope,
      inputFingerprint: fingerprint(input),
      resultStatus: failure.status === 403 ? "denied" : failure.status === 422 ? "validation_error" : "failed",
      httpStatus: failure.status,
      durationMs: Date.now() - started,
      recordCount: 0,
      errorCode: failure.code,
      ...(failure.status === 403 ? { denialReason: failure.message } : {}),
      startedAt,
    });
    return result({ ok: false, error: { code: failure.code, message: failure.message } }, true);
  }
}

export function registerCreatorCampaignReadTools(
  server: McpServer,
  dependencies: CreatorCampaignToolDependencies,
): void {
  if (hasScope(dependencies.connection, "creator_campaigns:read")) {
    server.registerTool(
      "microgifter.creator_campaigns.list",
      {
        description: "List Creator Campaigns visible to the authorized merchant workspace or authenticated Creator account.",
        inputSchema: {
          search: z.string().trim().max(100).optional(),
          status: z.string().trim().max(40).optional(),
          ...pageSchema,
        },
        annotations: readAnnotations,
      },
      async (input) => runCreatorCampaignRead(
        dependencies,
        "microgifter.creator_campaigns.list",
        "creator_campaigns:read",
        "creator_campaigns.list",
        input,
      ),
    );

    server.registerTool(
      "microgifter.creator_campaigns.get",
      {
        description: "Get one Creator Campaign through canonical workspace or Creator ownership rules.",
        inputSchema: {
          campaign_id: z.string().trim().min(8).max(40).regex(/^[A-Za-z0-9][A-Za-z0-9_-]+$/),
        },
        annotations: readAnnotations,
      },
      async (input) => runCreatorCampaignRead(
        dependencies,
        "microgifter.creator_campaigns.get",
        "creator_campaigns:read",
        "creator_campaigns.get",
        input,
      ),
    );

    server.registerTool(
      "microgifter.creator_campaigns.validate",
      {
        description: "Run the native read-only campaign builder readiness checks for an authorized merchant campaign.",
        inputSchema: {
          campaign_id: z.string().trim().min(8).max(40).regex(/^[A-Za-z0-9][A-Za-z0-9_-]+$/),
        },
        annotations: readAnnotations,
      },
      async (input) => runCreatorCampaignRead(
        dependencies,
        "microgifter.creator_campaigns.validate",
        "creator_campaigns:read",
        "creator_campaigns.validate",
        input,
      ),
    );
  }

  if (hasScope(dependencies.connection, "creator_campaigns_analytics:read")) {
    server.registerTool(
      "microgifter.creator_campaigns.analytics.get",
      {
        description: "Return privacy-safe Creator Campaign performance, earnings, and payout summaries.",
        inputSchema: {
          campaign_id: z.string().trim().min(8).max(40).regex(/^[A-Za-z0-9][A-Za-z0-9_-]+$/).optional(),
          days: z.number().int().min(1).max(366).default(30),
        },
        annotations: readAnnotations,
      },
      async (input) => runCreatorCampaignRead(
        dependencies,
        "microgifter.creator_campaigns.analytics.get",
        "creator_campaigns_analytics:read",
        "creator_campaigns.analytics.get",
        input,
      ),
    );
  }

  if (hasScope(dependencies.connection, "creator_campaign_applications:read")) {
    server.registerTool(
      "microgifter.creator_campaigns.applications.list",
      {
        description: "List campaign applications within the authorized merchant workspace or the Creator's own account.",
        inputSchema: campaignFilterSchema,
        annotations: readAnnotations,
      },
      async (input) => runCreatorCampaignRead(
        dependencies,
        "microgifter.creator_campaigns.applications.list",
        "creator_campaign_applications:read",
        "creator_campaigns.applications.list",
        input,
      ),
    );
  }

  if (hasScope(dependencies.connection, "creator_campaign_participants:read")) {
    server.registerTool(
      "microgifter.creator_campaigns.participants.list",
      {
        description: "List Creator Campaign participants without exposing private account contact fields.",
        inputSchema: campaignFilterSchema,
        annotations: readAnnotations,
      },
      async (input) => runCreatorCampaignRead(
        dependencies,
        "microgifter.creator_campaigns.participants.list",
        "creator_campaign_participants:read",
        "creator_campaigns.participants.list",
        input,
      ),
    );
  }

  if (hasScope(dependencies.connection, "creator_campaign_deliverables:read")) {
    server.registerTool(
      "microgifter.creator_campaigns.deliverables.list",
      {
        description: "List merchant campaign deliverable definitions or the authenticated Creator's own assignments.",
        inputSchema: campaignFilterSchema,
        annotations: readAnnotations,
      },
      async (input) => runCreatorCampaignRead(
        dependencies,
        "microgifter.creator_campaigns.deliverables.list",
        "creator_campaign_deliverables:read",
        "creator_campaigns.deliverables.list",
        input,
      ),
    );
  }

  if (hasScope(dependencies.connection, "creator_campaign_submissions:read")) {
    server.registerTool(
      "microgifter.creator_campaigns.submissions.list",
      {
        description: "List authorized Creator Campaign submissions and review state without storage internals.",
        inputSchema: campaignFilterSchema,
        annotations: readAnnotations,
      },
      async (input) => runCreatorCampaignRead(
        dependencies,
        "microgifter.creator_campaigns.submissions.list",
        "creator_campaign_submissions:read",
        "creator_campaigns.submissions.list",
        input,
      ),
    );
  }

  if (hasScope(dependencies.connection, "creator_campaign_tracking:read")) {
    server.registerTool(
      "microgifter.creator_campaigns.tracking.get",
      {
        description: "Return authorized campaign tracking sources and aggregate accepted activity; anonymous hashes are never exposed.",
        inputSchema: {
          campaign_id: z.string().trim().min(8).max(40).regex(/^[A-Za-z0-9][A-Za-z0-9_-]+$/).optional(),
          ...pageSchema,
        },
        annotations: readAnnotations,
      },
      async (input) => runCreatorCampaignRead(
        dependencies,
        "microgifter.creator_campaigns.tracking.get",
        "creator_campaign_tracking:read",
        "creator_campaigns.tracking.get",
        input,
      ),
    );
  }

  if (hasScope(dependencies.connection, "creator_campaign_attributions:read")) {
    server.registerTool(
      "microgifter.creator_campaigns.attributions.list",
      {
        description: "List canonical Creator Campaign attribution decisions without customer identity or anonymous tracking hashes.",
        inputSchema: campaignFilterSchema,
        annotations: readAnnotations,
      },
      async (input) => runCreatorCampaignRead(
        dependencies,
        "microgifter.creator_campaigns.attributions.list",
        "creator_campaign_attributions:read",
        "creator_campaigns.attributions.list",
        input,
      ),
    );
  }

  if (hasScope(dependencies.connection, "creator_campaign_earnings:read")) {
    server.registerTool(
      "microgifter.creator_campaigns.earnings.list",
      {
        description: "List authorized append-only Creator Campaign earning events in integer minor currency units.",
        inputSchema: {
          campaign_id: z.string().trim().min(8).max(40).regex(/^[A-Za-z0-9][A-Za-z0-9_-]+$/).optional(),
          ...pageSchema,
        },
        annotations: readAnnotations,
      },
      async (input) => runCreatorCampaignRead(
        dependencies,
        "microgifter.creator_campaigns.earnings.list",
        "creator_campaign_earnings:read",
        "creator_campaigns.earnings.list",
        input,
      ),
    );
  }

  if (hasScope(dependencies.connection, "creator_campaign_payouts:read")) {
    server.registerTool(
      "microgifter.creator_campaigns.payouts.list",
      {
        description: "List authorized Creator Campaign payout records without external provider references or banking details.",
        inputSchema: campaignFilterSchema,
        annotations: readAnnotations,
      },
      async (input) => runCreatorCampaignRead(
        dependencies,
        "microgifter.creator_campaigns.payouts.list",
        "creator_campaign_payouts:read",
        "creator_campaigns.payouts.list",
        input,
      ),
    );
  }

  if (hasScope(dependencies.connection, "creator_campaign_disputes:read")) {
    server.registerTool(
      "microgifter.creator_campaigns.disputes.list",
      {
        description: "List disputes within the authorized merchant workspace or the Creator's own campaign records.",
        inputSchema: campaignFilterSchema,
        annotations: readAnnotations,
      },
      async (input) => runCreatorCampaignRead(
        dependencies,
        "microgifter.creator_campaigns.disputes.list",
        "creator_campaign_disputes:read",
        "creator_campaigns.disputes.list",
        input,
      ),
    );
  }
}
