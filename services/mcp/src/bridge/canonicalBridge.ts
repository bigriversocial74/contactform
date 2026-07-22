import { createHash, createHmac, randomBytes, randomUUID } from "node:crypto";
import type { ConnectionContext } from "../contracts.js";
import type { InvocationReceipt } from "../receipts.js";

export interface CanonicalBridgeConfig {
  readonly enabled: boolean;
  readonly url: string;
  readonly secret: string;
  readonly timeoutMs: number;
}

export interface CatalogSearchArguments {
  readonly query?: string | undefined;
  readonly location?: string | undefined;
  readonly category?: string | undefined;
  readonly limit?: number | undefined;
  readonly cursor?: string | undefined;
}

export interface CatalogSearchResult {
  readonly items: readonly Readonly<Record<string, unknown>>[];
  readonly limit: number;
  readonly next_cursor: string | null;
}

export interface DraftCreateArguments {
  readonly type: "gift" | "campaign" | "reward" | "message";
  readonly title: string;
  readonly summary: string;
  readonly payload: Readonly<Record<string, unknown>>;
  readonly idempotency_key: string;
  readonly source_request_id: string;
  readonly risk_level?: "low" | "medium" | "high" | "critical" | undefined;
  readonly requested_reason?: string | undefined;
}

export interface DraftListArguments {
  readonly type?: "gift" | "campaign" | "reward" | "message" | undefined;
  readonly status?: "pending_review" | "approved" | "rejected" | "canceled" | "expired" | undefined;
  readonly limit?: number | undefined;
  readonly cursor?: string | undefined;
}

export type CreatorCampaignReadOperation =
  | "creator_campaigns.list"
  | "creator_campaigns.get"
  | "creator_campaigns.validate"
  | "creator_campaigns.analytics.get"
  | "creator_campaigns.applications.list"
  | "creator_campaigns.participants.list"
  | "creator_campaigns.deliverables.list"
  | "creator_campaigns.submissions.list"
  | "creator_campaigns.tracking.get"
  | "creator_campaigns.attributions.list"
  | "creator_campaigns.earnings.list"
  | "creator_campaigns.payouts.list"
  | "creator_campaigns.disputes.list";

export type CreatorCampaignReadArguments = Readonly<Record<string, unknown>>;

export interface CanonicalBridge {
  resolveConnection(connectionId: string): Promise<ConnectionContext>;
  searchCatalog(connectionId: string, arguments_: CatalogSearchArguments): Promise<CatalogSearchResult>;
  getCatalogItem(
    connectionId: string,
    arguments_: Readonly<{ product_id: string; slug?: string | undefined }>,
  ): Promise<Readonly<Record<string, unknown>>>;
  creatorCampaignRead?(
    connectionId: string,
    operation: CreatorCampaignReadOperation,
    arguments_: CreatorCampaignReadArguments,
  ): Promise<Readonly<Record<string, unknown>>>;
  requestCreatorCampaignAction?(
    connectionId: string,
    toolName: string,
    input: Readonly<Record<string, unknown>>,
  ): Promise<Readonly<Record<string, unknown>>>;
  createDraft(connectionId: string, arguments_: DraftCreateArguments): Promise<Readonly<Record<string, unknown>>>;
  listDrafts(connectionId: string, arguments_: DraftListArguments): Promise<Readonly<Record<string, unknown>>>;
  getDraft(connectionId: string, draftId: string): Promise<Readonly<Record<string, unknown>>>;
  cancelDraft(connectionId: string, draftId: string, reason?: string): Promise<Readonly<Record<string, unknown>>>;
  recordReceipt(receipt: InvocationReceipt): Promise<void>;
}

export class CanonicalBridgeError extends Error {
  public constructor(
    message: string,
    public readonly code: string,
    public readonly status: number,
  ) {
    super(message);
    this.name = "CanonicalBridgeError";
  }
}

export function canonicalBridgeSignaturePayload(timestamp: string, nonce: string, body: string): string {
  return `${timestamp}\n${nonce}\n${createHash("sha256").update(body).digest("hex")}`;
}

export function signCanonicalBridgeRequest(secret: string, timestamp: string, nonce: string, body: string): string {
  return createHmac("sha256", secret).update(canonicalBridgeSignaturePayload(timestamp, nonce, body)).digest("hex");
}

function object(value: unknown, name: string): Readonly<Record<string, unknown>> {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    throw new CanonicalBridgeError(`Invalid ${name} response.`, "MCP_BRIDGE_RESPONSE_INVALID", 502);
  }
  return value as Readonly<Record<string, unknown>>;
}

function text(value: unknown, name: string): string {
  if (typeof value !== "string" || value.trim() === "") {
    throw new CanonicalBridgeError(`Invalid ${name} response.`, "MCP_BRIDGE_RESPONSE_INVALID", 502);
  }
  return value;
}

function stringArray(value: unknown, name: string): readonly string[] {
  if (!Array.isArray(value) || value.some((item) => typeof item !== "string" || item.trim() === "")) {
    throw new CanonicalBridgeError(`Invalid ${name} response.`, "MCP_BRIDGE_RESPONSE_INVALID", 502);
  }
  return [...new Set(value)].sort();
}

export class HttpCanonicalBridge implements CanonicalBridge {
  public constructor(
    private readonly config: CanonicalBridgeConfig,
    private readonly connectionId: string,
  ) {
    if (!config.enabled) {
      throw new Error("The canonical bridge client cannot be created while disabled.");
    }
  }

  public async resolveConnection(connectionId: string): Promise<ConnectionContext> {
    const data = object(await this.request("connection.resolve", connectionId, {}), "connection");
    const workspaceValue = data.workspace;
    let workspace: ConnectionContext["workspace"];
    if (workspaceValue !== null && workspaceValue !== undefined) {
      const workspaceObject = object(workspaceValue, "workspace");
      workspace = {
        type: text(workspaceObject.type, "workspace type"),
        id: text(workspaceObject.id, "workspace id"),
      };
    }
    const expiresAt = typeof data.expiresAt === "string" && data.expiresAt !== "" ? data.expiresAt : undefined;
    const tokenVersion = typeof data.tokenVersion === "number" && Number.isSafeInteger(data.tokenVersion)
      ? data.tokenVersion
      : 1;
    const maximumOperationClass = typeof data.maximumOperationClass === "string"
      ? data.maximumOperationClass as ConnectionContext["maximumOperationClass"]
      : "read";
    return {
      connectionId: text(data.connectionId, "connection id"),
      clientKey: text(data.clientKey, "client key"),
      userId: text(data.userId, "user id"),
      ...(workspace ? { workspace } : {}),
      scopes: stringArray(data.scopes, "scopes"),
      maximumOperationClass,
      tokenVersion,
      ...(expiresAt ? { expiresAt } : {}),
    };
  }

  public async searchCatalog(connectionId: string, arguments_: CatalogSearchArguments): Promise<CatalogSearchResult> {
    const data = object(await this.request("catalog.search", connectionId, arguments_), "catalog search");
    if (!Array.isArray(data.items)) {
      throw new CanonicalBridgeError("Invalid catalog search response.", "MCP_BRIDGE_RESPONSE_INVALID", 502);
    }
    const limit = typeof data.limit === "number" && Number.isSafeInteger(data.limit)
      ? data.limit
      : arguments_.limit ?? 10;
    return {
      items: data.items.map((item) => object(item, "catalog item")),
      limit,
      next_cursor: typeof data.next_cursor === "string" && data.next_cursor !== "" ? data.next_cursor : null,
    };
  }

  public async getCatalogItem(
    connectionId: string,
    arguments_: Readonly<{ product_id: string; slug?: string | undefined }>,
  ): Promise<Readonly<Record<string, unknown>>> {
    return object(await this.request("catalog.get_item", connectionId, arguments_), "catalog item");
  }

  public async creatorCampaignRead(
    connectionId: string,
    operation: CreatorCampaignReadOperation,
    arguments_: CreatorCampaignReadArguments,
  ): Promise<Readonly<Record<string, unknown>>> {
    return object(await this.request(operation, connectionId, arguments_), "Creator Campaign read result");
  }

  public async requestCreatorCampaignAction(
    connectionId: string,
    toolName: string,
    input: Readonly<Record<string, unknown>>,
  ): Promise<Readonly<Record<string, unknown>>> {
    return object(await this.request("creator_campaign_actions.request", connectionId, {
      tool_name: toolName,
      input,
    }), "Creator Campaign action request");
  }

  public async createDraft(connectionId: string, arguments_: DraftCreateArguments): Promise<Readonly<Record<string, unknown>>> {
    return object(await this.request("draft.create", connectionId, arguments_), "draft");
  }

  public async listDrafts(connectionId: string, arguments_: DraftListArguments): Promise<Readonly<Record<string, unknown>>> {
    return object(await this.request("draft.list", connectionId, arguments_), "draft list");
  }

  public async getDraft(connectionId: string, draftId: string): Promise<Readonly<Record<string, unknown>>> {
    return object(await this.request("draft.get", connectionId, { draft_id: draftId }), "draft");
  }

  public async cancelDraft(connectionId: string, draftId: string, reason?: string): Promise<Readonly<Record<string, unknown>>> {
    return object(await this.request("draft.cancel", connectionId, {
      draft_id: draftId,
      ...(reason ? { reason } : {}),
    }), "draft");
  }

  public async recordReceipt(receipt: InvocationReceipt): Promise<void> {
    await this.request("receipt.record", receipt.connectionId || this.connectionId, {
      request_id: receipt.requestId,
      tool_name: receipt.toolName,
      required_scope: receipt.requiredScope,
      input_fingerprint: receipt.inputFingerprint,
      result_status: receipt.resultStatus,
      http_status: receipt.httpStatus,
      duration_ms: receipt.durationMs,
      record_count: receipt.recordCount,
      ...(receipt.errorCode ? { error_code: receipt.errorCode } : {}),
      ...(receipt.denialReason ? { denial_reason: receipt.denialReason } : {}),
    });
  }

  private async request(operation: string, connectionId: string, arguments_: unknown): Promise<unknown> {
    const requestId = randomUUID();
    const body = JSON.stringify({
      request_id: requestId,
      connection_id: connectionId,
      operation,
      arguments: arguments_,
    });
    const timestamp = String(Math.floor(Date.now() / 1000));
    const nonce = randomBytes(24).toString("base64url");
    const signature = signCanonicalBridgeRequest(this.config.secret, timestamp, nonce, body);
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), this.config.timeoutMs);

    let response: Response;
    try {
      response = await fetch(this.config.url, {
        method: "POST",
        headers: {
          accept: "application/json",
          "content-type": "application/json",
          "x-microgifter-mcp-timestamp": timestamp,
          "x-microgifter-mcp-nonce": nonce,
          "x-microgifter-mcp-signature": signature,
        },
        body,
        signal: controller.signal,
      });
    } catch (error) {
      const message = error instanceof Error && error.name === "AbortError"
        ? "The canonical bridge timed out."
        : "The canonical bridge is unavailable.";
      throw new CanonicalBridgeError(message, "MCP_BRIDGE_UNAVAILABLE", 503);
    } finally {
      clearTimeout(timeout);
    }

    const payload = await response.json().catch(() => null) as unknown;
    const envelope = object(payload, "bridge envelope");
    if (!response.ok || envelope.ok !== true) {
      const error = envelope.error && typeof envelope.error === "object" && !Array.isArray(envelope.error)
        ? envelope.error as Readonly<Record<string, unknown>>
        : {};
      throw new CanonicalBridgeError(
        typeof error.message === "string" && error.message !== "" ? error.message : "The canonical bridge rejected the request.",
        typeof error.code === "string" && error.code !== "" ? error.code : "MCP_BRIDGE_REQUEST_FAILED",
        response.status,
      );
    }
    return envelope.data;
  }
}
