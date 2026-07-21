import { createHash, randomUUID } from "node:crypto";
import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import * as z from "zod/v4";
import type { CanonicalBridge, DraftCreateArguments } from "../bridge/canonicalBridge.js";
import { CanonicalBridgeError } from "../bridge/canonicalBridge.js";
import type { ConnectionContext } from "../contracts.js";
import type { InvocationReceipt, InvocationReceiptSink } from "../receipts.js";

export interface DraftToolDependencies {
  readonly connection: ConnectionContext;
  readonly receipts: InvocationReceiptSink;
  readonly bridge?: CanonicalBridge;
}

const draftTypes = ["gift", "campaign", "reward", "message"] as const;
const draftStatuses = ["pending_review", "approved", "rejected", "canceled", "expired"] as const;
const riskLevels = ["low", "medium", "high", "critical"] as const;

function fingerprint(value: unknown): string {
  return createHash("sha256").update(JSON.stringify(value ?? {})).digest("hex");
}

function hasScope(connection: ConnectionContext, scope: string): boolean {
  return connection.maximumOperationClass === "draft" && connection.scopes.includes(scope);
}

function hasAnyDraftScope(connection: ConnectionContext): boolean {
  return ["gift:draft", "campaign:draft", "reward:draft", "message:draft"].some((scope) => hasScope(connection, scope));
}

function result(payload: Readonly<Record<string, unknown>>, isError = false) {
  return {
    ...(isError ? { isError: true as const } : {}),
    content: [{ type: "text" as const, text: JSON.stringify(payload) }],
    structuredContent: payload,
  };
}

async function receipt(
  sink: InvocationReceiptSink,
  values: Omit<InvocationReceipt, "startedAt" | "completedAt"> & { readonly startedAt: string },
): Promise<void> {
  await sink.record({ ...values, completedAt: new Date().toISOString() });
}

async function runDraftOperation(
  dependencies: DraftToolDependencies,
  toolName: string,
  requiredScope: string,
  input: unknown,
  operation: () => Promise<Readonly<Record<string, unknown>>>,
) {
  const requestId = randomUUID();
  const startedAt = new Date().toISOString();
  const started = Date.now();
  try {
    const data = await operation();
    await receipt(dependencies.receipts, {
      requestId,
      connectionId: dependencies.connection.connectionId,
      toolName,
      operationClass: "draft",
      requiredScope,
      inputFingerprint: fingerprint(input),
      resultStatus: "success",
      httpStatus: 200,
      durationMs: Date.now() - started,
      recordCount: Array.isArray(data.items) ? data.items.length : 1,
      startedAt,
    });
    return result({ ok: true, request_id: requestId, data });
  } catch (error) {
    const failure = error instanceof CanonicalBridgeError
      ? error
      : new CanonicalBridgeError("The canonical draft authority could not complete the request.", "MCP_DRAFT_SERVICE_FAILED", 500);
    await receipt(dependencies.receipts, {
      requestId,
      connectionId: dependencies.connection.connectionId,
      toolName,
      operationClass: "draft",
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

function ensureBridge(dependencies: DraftToolDependencies): CanonicalBridge | null {
  return dependencies.bridge ?? null;
}

function createDraftHandler(
  dependencies: DraftToolDependencies,
  type: DraftCreateArguments["type"],
  scope: string,
  toolName: string,
  payload: (input: Record<string, unknown>) => Readonly<Record<string, unknown>>,
) {
  return async (input: Record<string, unknown>) => {
    const bridge = ensureBridge(dependencies);
    if (!bridge) return result({ ok: false, error: { code: "MICROGIFTER_TOOL_DISABLED", message: "The canonical draft bridge is not enabled." } }, true);
    const arguments_: DraftCreateArguments = {
      type,
      title: String(input.title ?? ""),
      summary: String(input.summary ?? ""),
      payload: payload(input),
      idempotency_key: String(input.idempotency_key ?? ""),
      source_request_id: randomUUID(),
      risk_level: (input.risk_level ?? "medium") as DraftCreateArguments["risk_level"],
      requested_reason: String(input.requested_reason ?? "External agent prepared a reviewable draft."),
    };
    return runDraftOperation(
      dependencies,
      toolName,
      scope,
      arguments_,
      () => bridge.createDraft(dependencies.connection.connectionId, arguments_),
    );
  };
}

export function registerDraftTools(server: McpServer, dependencies: DraftToolDependencies): void {
  if (hasScope(dependencies.connection, "gift:draft")) {
    server.registerTool(
      "microgifter.gift.create_draft",
      {
        description: "Create a reviewable gift draft. This does not purchase, issue, deliver, or charge for a gift.",
        inputSchema: {
          title: z.string().trim().min(1).max(190),
          summary: z.string().trim().min(1).max(500),
          product_id: z.string().uuid(),
          recipient_name: z.string().trim().max(190).optional(),
          recipient_reference: z.string().trim().max(190).optional(),
          message: z.string().max(1000).optional(),
          quantity: z.number().int().min(1).max(25).default(1),
          deliver_after: z.string().datetime({ offset: true }).optional(),
          notes: z.string().max(1000).optional(),
          idempotency_key: z.string().trim().min(8).max(190).regex(/^[A-Za-z0-9._:-]+$/),
          risk_level: z.enum(riskLevels).default("medium"),
          requested_reason: z.string().trim().min(1).max(1000).optional(),
        },
        annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false },
      },
      createDraftHandler(dependencies, "gift", "gift:draft", "microgifter.gift.create_draft", (input) => ({
        product_id: input.product_id,
        recipient_name: input.recipient_name,
        recipient_reference: input.recipient_reference,
        message: input.message,
        quantity: input.quantity,
        deliver_after: input.deliver_after,
        notes: input.notes,
      })),
    );
  }

  if (hasScope(dependencies.connection, "campaign:draft")) {
    server.registerTool(
      "microgifter.campaign.create_draft",
      {
        description: "Create a merchant campaign draft for human review. This does not publish, schedule, message, or spend budget.",
        inputSchema: {
          title: z.string().trim().min(1).max(190),
          summary: z.string().trim().min(1).max(500),
          name: z.string().trim().min(1).max(190),
          objective: z.string().trim().min(1).max(500),
          audience_summary: z.string().trim().min(1).max(500),
          offer_summary: z.string().trim().min(1).max(500),
          starts_at: z.string().datetime({ offset: true }).optional(),
          ends_at: z.string().datetime({ offset: true }).optional(),
          budget_cents: z.number().int().min(0).max(100000000).optional(),
          notes: z.string().max(1000).optional(),
          idempotency_key: z.string().trim().min(8).max(190).regex(/^[A-Za-z0-9._:-]+$/),
          risk_level: z.enum(riskLevels).default("medium"),
          requested_reason: z.string().trim().min(1).max(1000).optional(),
        },
        annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false },
      },
      createDraftHandler(dependencies, "campaign", "campaign:draft", "microgifter.campaign.create_draft", (input) => ({
        name: input.name,
        objective: input.objective,
        audience_summary: input.audience_summary,
        offer_summary: input.offer_summary,
        starts_at: input.starts_at,
        ends_at: input.ends_at,
        budget_cents: input.budget_cents,
        notes: input.notes,
      })),
    );
  }

  if (hasScope(dependencies.connection, "reward:draft")) {
    server.registerTool(
      "microgifter.reward.create_draft",
      {
        description: "Create a merchant reward draft for human review. This does not activate, issue, or fulfill a reward.",
        inputSchema: {
          title: z.string().trim().min(1).max(190),
          summary: z.string().trim().min(1).max(500),
          name: z.string().trim().min(1).max(190),
          qualification_summary: z.string().trim().min(1).max(500),
          reward_summary: z.string().trim().min(1).max(500),
          quantity_limit: z.number().int().min(1).max(1000000).optional(),
          starts_at: z.string().datetime({ offset: true }).optional(),
          ends_at: z.string().datetime({ offset: true }).optional(),
          notes: z.string().max(1000).optional(),
          idempotency_key: z.string().trim().min(8).max(190).regex(/^[A-Za-z0-9._:-]+$/),
          risk_level: z.enum(riskLevels).default("medium"),
          requested_reason: z.string().trim().min(1).max(1000).optional(),
        },
        annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false },
      },
      createDraftHandler(dependencies, "reward", "reward:draft", "microgifter.reward.create_draft", (input) => ({
        name: input.name,
        qualification_summary: input.qualification_summary,
        reward_summary: input.reward_summary,
        quantity_limit: input.quantity_limit,
        starts_at: input.starts_at,
        ends_at: input.ends_at,
        notes: input.notes,
      })),
    );
  }

  if (hasScope(dependencies.connection, "message:draft")) {
    server.registerTool(
      "microgifter.message.create_draft",
      {
        description: "Create a merchant message draft for human review. This does not send or schedule a message.",
        inputSchema: {
          title: z.string().trim().min(1).max(190),
          summary: z.string().trim().min(1).max(500),
          audience_summary: z.string().trim().min(1).max(500),
          subject: z.string().trim().max(190).optional(),
          body: z.string().min(1).max(5000),
          channel: z.enum(["in_app", "email", "sms"]).default("in_app"),
          schedule_after: z.string().datetime({ offset: true }).optional(),
          notes: z.string().max(1000).optional(),
          idempotency_key: z.string().trim().min(8).max(190).regex(/^[A-Za-z0-9._:-]+$/),
          risk_level: z.enum(riskLevels).default("medium"),
          requested_reason: z.string().trim().min(1).max(1000).optional(),
        },
        annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false },
      },
      createDraftHandler(dependencies, "message", "message:draft", "microgifter.message.create_draft", (input) => ({
        audience_summary: input.audience_summary,
        subject: input.subject,
        body: input.body,
        channel: input.channel,
        schedule_after: input.schedule_after,
        notes: input.notes,
      })),
    );
  }

  if (hasAnyDraftScope(dependencies.connection)) {
    server.registerTool(
      "microgifter.drafts.list",
      {
        description: "List reviewable drafts created through this authorized MCP connection.",
        inputSchema: {
          type: z.enum(draftTypes).optional(),
          status: z.enum(draftStatuses).optional(),
          limit: z.number().int().min(1).max(50).default(20),
          cursor: z.string().regex(/^[0-9]+$/).optional(),
        },
        annotations: { readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false },
      },
      async (input) => {
        const bridge = ensureBridge(dependencies);
        if (!bridge) return result({ ok: false, error: { code: "MICROGIFTER_TOOL_DISABLED", message: "The canonical draft bridge is not enabled." } }, true);
        return runDraftOperation(dependencies, "microgifter.drafts.list", "draft:any", input, () => bridge.listDrafts(dependencies.connection.connectionId, input));
      },
    );

    server.registerTool(
      "microgifter.drafts.get",
      {
        description: "Get one reviewable draft created through this authorized MCP connection.",
        inputSchema: { draft_id: z.string().uuid() },
        annotations: { readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false },
      },
      async (input) => {
        const bridge = ensureBridge(dependencies);
        if (!bridge) return result({ ok: false, error: { code: "MICROGIFTER_TOOL_DISABLED", message: "The canonical draft bridge is not enabled." } }, true);
        return runDraftOperation(dependencies, "microgifter.drafts.get", "draft:any", input, () => bridge.getDraft(dependencies.connection.connectionId, input.draft_id));
      },
    );

    server.registerTool(
      "microgifter.drafts.cancel",
      {
        description: "Cancel a pending reviewable draft created through this connection. This cannot reverse an owner decision.",
        inputSchema: {
          draft_id: z.string().uuid(),
          reason: z.string().trim().min(1).max(1000).optional(),
        },
        annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
      },
      async (input) => {
        const bridge = ensureBridge(dependencies);
        if (!bridge) return result({ ok: false, error: { code: "MICROGIFTER_TOOL_DISABLED", message: "The canonical draft bridge is not enabled." } }, true);
        return runDraftOperation(dependencies, "microgifter.drafts.cancel", "draft:any", input, () => bridge.cancelDraft(dependencies.connection.connectionId, input.draft_id, input.reason));
      },
    );
  }
}
