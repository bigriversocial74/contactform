import { createHash, randomUUID } from "node:crypto";
import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import * as z from "zod/v4";
import type { CanonicalBridge, DraftCreateArguments } from "../bridge/canonicalBridge.js";
import { CanonicalBridgeError } from "../bridge/canonicalBridge.js";
import type { ConnectionContext } from "../contracts.js";
import type { InvocationReceipt, InvocationReceiptSink } from "../receipts.js";

export interface CreatorCampaignDraftToolDependencies {
  readonly connection: ConnectionContext;
  readonly receipts: InvocationReceiptSink;
  readonly bridge?: CanonicalBridge;
}

type ProposalKind =
  | "draft.create"
  | "draft.update"
  | "products.propose"
  | "eligibility.propose"
  | "deliverables.propose"
  | "compensation.propose"
  | "attribution.propose"
  | "budget.propose"
  | "rights.propose"
  | "terms.propose"
  | "invitation.draft"
  | "message.draft"
  | "submission_feedback.draft";

const proposalScope: Readonly<Record<ProposalKind, string>> = Object.freeze({
  "draft.create": "creator_campaigns:draft",
  "draft.update": "creator_campaigns:draft",
  "products.propose": "creator_campaign_products:draft",
  "eligibility.propose": "creator_campaign_eligibility:draft",
  "deliverables.propose": "creator_campaign_deliverables:draft",
  "compensation.propose": "creator_campaign_compensation:draft",
  "attribution.propose": "creator_campaign_attribution:draft",
  "budget.propose": "creator_campaign_budget:draft",
  "rights.propose": "creator_campaign_rights:draft",
  "terms.propose": "creator_campaign_terms:draft",
  "invitation.draft": "creator_campaign_invitations:draft",
  "message.draft": "creator_campaign_messages:draft",
  "submission_feedback.draft": "creator_campaign_submission_feedback:draft",
});

const riskLevels = ["low", "medium", "high", "critical"] as const;
const campaignId = z.string().trim().min(8).max(40).regex(/^[A-Za-z0-9][A-Za-z0-9_-]+$/);
const publicId = z.string().trim().min(8).max(40).regex(/^[A-Za-z0-9][A-Za-z0-9_-]+$/);
const uuid = z.string().uuid();
const idempotencyKey = z.string().trim().min(8).max(190).regex(/^[A-Za-z0-9._:-]+$/);
const currency = z.string().trim().length(3).regex(/^[A-Za-z]{3}$/).transform((value) => value.toUpperCase());

const commonSchema = {
  title: z.string().trim().min(1).max(190),
  summary: z.string().trim().min(1).max(500),
  idempotency_key: idempotencyKey,
  risk_level: z.enum(riskLevels).default("medium"),
  requested_reason: z.string().trim().min(1).max(1000).optional(),
};

const draftAnnotations = {
  readOnlyHint: false,
  destructiveHint: false,
  idempotentHint: true,
  openWorldHint: false,
} as const;

function hasScope(connection: ConnectionContext, scope: string): boolean {
  return connection.maximumOperationClass === "draft" && connection.scopes.includes(scope);
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

async function createProposal(
  dependencies: CreatorCampaignDraftToolDependencies,
  toolName: string,
  kind: ProposalKind,
  input: Readonly<Record<string, unknown>>,
  proposedValues: Readonly<Record<string, unknown>>,
  campaign?: string,
) {
  const bridge = dependencies.bridge;
  if (!bridge) {
    return result({
      ok: false,
      error: { code: "MICROGIFTER_TOOL_DISABLED", message: "The canonical Creator Campaign proposal bridge is not enabled." },
    }, true);
  }

  const scope = proposalScope[kind];
  const requestId = randomUUID();
  const startedAt = new Date().toISOString();
  const started = Date.now();
  const arguments_: DraftCreateArguments = {
    type: "campaign",
    title: String(input.title ?? ""),
    summary: String(input.summary ?? ""),
    payload: {
      creator_campaign_proposal: true,
      proposal_version: 1,
      proposal_kind: kind,
      ...(campaign ? { campaign_id: campaign } : {}),
      proposed_values: proposedValues,
    },
    idempotency_key: String(input.idempotency_key ?? ""),
    source_request_id: requestId,
    risk_level: (input.risk_level ?? "medium") as DraftCreateArguments["risk_level"],
    requested_reason: String(input.requested_reason ?? "External agent prepared a Creator Campaign proposal for merchant review."),
  };

  try {
    const data = await bridge.createDraft(dependencies.connection.connectionId, arguments_);
    await recordReceipt(dependencies.receipts, {
      requestId,
      connectionId: dependencies.connection.connectionId,
      toolName,
      operationClass: "draft",
      requiredScope: scope,
      inputFingerprint: fingerprint(input),
      resultStatus: "success",
      httpStatus: 200,
      durationMs: Date.now() - started,
      recordCount: 1,
      startedAt,
    });
    return result({ ok: true, request_id: requestId, data });
  } catch (error) {
    const failure = error instanceof CanonicalBridgeError
      ? error
      : new CanonicalBridgeError(
        "The canonical Creator Campaign proposal service could not complete the request.",
        "MCP_CREATOR_CAMPAIGN_PROPOSAL_SERVICE_FAILED",
        500,
      );
    await recordReceipt(dependencies.receipts, {
      requestId,
      connectionId: dependencies.connection.connectionId,
      toolName,
      operationClass: "draft",
      requiredScope: scope,
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

function register(
  server: McpServer,
  dependencies: CreatorCampaignDraftToolDependencies,
  kind: ProposalKind,
  toolName: string,
  description: string,
  inputSchema: Record<string, z.ZodType>,
  values: (input: Record<string, unknown>) => Readonly<Record<string, unknown>>,
  campaign: (input: Record<string, unknown>) => string | undefined = (input) => typeof input.campaign_id === "string" ? input.campaign_id : undefined,
): void {
  const scope = proposalScope[kind];
  if (!hasScope(dependencies.connection, scope)) return;
  server.registerTool(
    toolName,
    { description, inputSchema, annotations: draftAnnotations },
    async (input) => createProposal(dependencies, toolName, kind, input, values(input), campaign(input)),
  );
}

export function registerCreatorCampaignDraftTools(
  server: McpServer,
  dependencies: CreatorCampaignDraftToolDependencies,
): void {
  register(
    server, dependencies, "draft.create", "microgifter.creator_campaigns.draft.create",
    "Create a review-only proposal for a new Creator Campaign. This does not create or publish a native campaign.",
    {
      ...commonSchema,
      internal_reference: z.string().trim().min(1).max(100),
      name: z.string().trim().min(1).max(180),
      description: z.string().max(10000).optional(),
      objective: z.string().trim().min(1).max(180),
      category: z.string().trim().min(1).max(100),
      campaign_focus: z.enum(["merchant_profile", "single_product", "multiple_products", "product_collection", "microgift_offer", "reward", "event", "service", "experience", "general_brand_campaign"]),
      access_mode: z.enum(["open", "invite_only", "approved_creators", "selected_creators", "hybrid"]),
      timezone: z.string().trim().min(1).max(80).default("UTC"),
      starts_at: z.string().datetime({ offset: true }).optional(),
      ends_at: z.string().datetime({ offset: true }).optional(),
      application_deadline_at: z.string().datetime({ offset: true }).optional(),
    },
    (input) => ({
      internal_reference: input.internal_reference, title: input.name, description: input.description,
      objective: input.objective, category: input.category, campaign_focus: input.campaign_focus,
      access_mode: input.access_mode, timezone: input.timezone, starts_at: input.starts_at,
      ends_at: input.ends_at, application_deadline_at: input.application_deadline_at,
    }),
    () => undefined,
  );

  register(
    server, dependencies, "draft.update", "microgifter.creator_campaigns.draft.update",
    "Propose edits to an existing Creator Campaign for merchant review without changing the native campaign.",
    { ...commonSchema, campaign_id: campaignId, changes: z.record(z.string(), z.unknown()).refine((value) => Object.keys(value).length > 0, "At least one change is required.") },
    (input) => input.changes as Readonly<Record<string, unknown>>,
  );

  register(
    server, dependencies, "products.propose", "microgifter.creator_campaigns.products.propose",
    "Propose Creator Campaign product relationships without changing the native campaign catalog links.",
    {
      ...commonSchema, campaign_id: campaignId,
      products: z.array(z.object({
        product_id: uuid, version_id: uuid.optional(),
        relationship_type: z.enum(["primary", "featured", "commissionable", "excluded", "creator_compensation"]).default("featured"),
        sort_order: z.number().int().min(0).max(10000).default(0),
        value_minor: z.number().int().min(0).max(1_000_000_000).optional(), currency: currency.optional(),
      })).min(1).max(50),
    },
    (input) => ({ products: input.products }),
  );

  register(
    server, dependencies, "eligibility.propose", "microgifter.creator_campaigns.eligibility.propose",
    "Propose Creator eligibility rules without changing campaign eligibility or accepting Creators.",
    {
      ...commonSchema, campaign_id: campaignId,
      rules: z.array(z.object({
        rule_type: z.enum(["specialty", "category", "platform", "verification", "location", "audience", "existing_relationship"]),
        operator: z.enum(["equals", "not_equals", "contains", "in", "gte", "lte", "between", "exists"]).default("equals"),
        value: z.unknown(), required: z.boolean().default(true), sort_order: z.number().int().min(0).max(10000).default(0),
      })).min(1).max(50),
    },
    (input) => ({ rules: input.rules }),
  );

  register(
    server, dependencies, "deliverables.propose", "microgifter.creator_campaigns.deliverables.propose",
    "Propose Creator Campaign deliverables without assigning work or changing submissions.",
    {
      ...commonSchema, campaign_id: campaignId,
      deliverables: z.array(z.object({
        title: z.string().trim().min(1).max(180), description: z.string().max(10000).optional(),
        type: z.enum(["photo", "short_video", "long_video", "story", "reel", "post", "article", "audio", "livestream", "event_appearance", "product_review", "other"]),
        platform: z.string().trim().max(80).optional(), format: z.string().trim().max(120).optional(),
        quantity: z.number().int().min(1).max(1000).default(1), instructions: z.string().max(20000).optional(),
        publication_required: z.boolean().default(false), proof_required: z.boolean().default(false),
        revision_limit: z.number().int().min(0).max(100).default(2), due_offset_days: z.number().int().min(0).max(3650).optional(),
      })).min(1).max(50),
    },
    (input) => ({ deliverables: input.deliverables }),
  );

  register(
    server, dependencies, "compensation.propose", "microgifter.creator_campaigns.compensation.propose",
    "Propose compensation rules for merchant review. This cannot activate rules, create earnings, reserve budget, or pay Creators.",
    {
      ...commonSchema, campaign_id: campaignId, risk_level: z.enum(riskLevels).default("high"),
      rules: z.array(z.object({
        trigger_type: z.enum(["verified_deliverable", "attributed_conversion", "milestone", "manual_adjustment"]),
        currency: currency.default("USD"), amount_minor: z.number().int().min(0).max(1_000_000_000).optional(),
        rate_bps: z.number().int().min(0).max(10000).optional(), cap_minor: z.number().int().min(0).max(1_000_000_000).optional(),
        conditions: z.record(z.string(), z.unknown()).default({}),
      }).refine((value) => value.amount_minor !== undefined || value.rate_bps !== undefined, "Amount or rate is required.")).min(1).max(50),
    },
    (input) => ({ rules: input.rules }),
  );

  register(
    server, dependencies, "attribution.propose", "microgifter.creator_campaigns.attribution.propose",
    "Propose campaign attribution settings without changing sources, events, or attribution decisions.",
    {
      ...commonSchema, campaign_id: campaignId,
      model: z.enum(["first_touch", "last_touch", "direct"]).default("last_touch"),
      click_window_days: z.number().int().min(1).max(365).default(30), conversion_window_days: z.number().int().min(1).max(365).default(30),
      channels: z.array(z.string().trim().min(1).max(80)).max(30).default([]),
    },
    (input) => ({ model: input.model, click_window_days: input.click_window_days, conversion_window_days: input.conversion_window_days, channels: input.channels }),
  );

  register(
    server, dependencies, "budget.propose", "microgifter.creator_campaigns.budget.propose",
    "Propose a campaign budget for review without funding, reserving, committing, or spending money.",
    {
      ...commonSchema, campaign_id: campaignId, risk_level: z.enum(riskLevels).default("high"),
      currency: currency.default("USD"), limit_minor: z.number().int().min(0).max(100_000_000_000),
      allow_overage: z.boolean().default(false), overage_limit_minor: z.number().int().min(0).max(100_000_000_000).default(0),
    },
    (input) => ({ currency: input.currency, limit_minor: input.limit_minor, allow_overage: input.allow_overage, overage_limit_minor: input.overage_limit_minor }),
  );

  register(
    server, dependencies, "rights.propose", "microgifter.creator_campaigns.rights.propose",
    "Propose content-rights language without creating, offering, or modifying a Creator agreement.",
    {
      ...commonSchema, campaign_id: campaignId, risk_level: z.enum(riskLevels).default("high"),
      license_scope: z.string().trim().min(1).max(500), channels: z.array(z.string().trim().min(1).max(80)).max(50).default([]),
      territory: z.string().trim().min(1).max(250), duration_days: z.number().int().min(1).max(36500),
      exclusive: z.boolean().default(false), usage_notes: z.string().max(10000).optional(),
    },
    (input) => ({ license_scope: input.license_scope, channels: input.channels, territory: input.territory, duration_days: input.duration_days, exclusive: input.exclusive, usage_notes: input.usage_notes }),
  );

  register(
    server, dependencies, "terms.propose", "microgifter.creator_campaigns.terms.propose",
    "Propose campaign terms without creating an agreement version or changing accepted terms.",
    {
      ...commonSchema, campaign_id: campaignId, risk_level: z.enum(riskLevels).default("high"),
      terms_summary: z.string().trim().min(1).max(1000), terms_text: z.string().min(1).max(30000),
      change_summary: z.string().max(2000).optional(), requires_reacceptance: z.boolean().default(true),
    },
    (input) => ({ summary: input.terms_summary, terms_text: input.terms_text, change_summary: input.change_summary, requires_reacceptance: input.requires_reacceptance }),
  );

  register(
    server, dependencies, "invitation.draft", "microgifter.creator_campaigns.invitation.draft",
    "Draft a Creator invitation for merchant review without sending it or creating a participant.",
    {
      ...commonSchema, campaign_id: campaignId, creator_profile_id: publicId,
      invitation_message: z.string().min(1).max(5000), response_deadline_at: z.string().datetime({ offset: true }).optional(),
      internal_note: z.string().max(2000).optional(),
    },
    (input) => ({ creator_profile_id: input.creator_profile_id, invitation_message: input.invitation_message, response_deadline_at: input.response_deadline_at, internal_note: input.internal_note }),
  );

  register(
    server, dependencies, "message.draft", "microgifter.creator_campaigns.message.draft",
    "Draft a campaign message for merchant review without sending or scheduling it.",
    {
      ...commonSchema, campaign_id: campaignId, participant_id: publicId.optional(),
      audience_summary: z.string().trim().min(1).max(500), subject: z.string().trim().max(190).optional(),
      body: z.string().min(1).max(10000), channel: z.enum(["in_app", "email", "sms"]).default("in_app"),
      send_after: z.string().datetime({ offset: true }).optional(),
    },
    (input) => ({ participant_id: input.participant_id, audience_summary: input.audience_summary, subject: input.subject, body: input.body, channel: input.channel, send_after: input.send_after }),
  );

  register(
    server, dependencies, "submission_feedback.draft", "microgifter.creator_campaigns.submission_feedback.draft",
    "Draft feedback for a Creator submission without approving, rejecting, or requesting a revision.",
    {
      ...commonSchema, submission_id: publicId,
      recommendation: z.enum(["approve", "request_revision", "reject", "request_information"]),
      feedback: z.string().min(1).max(10000), required_changes: z.array(z.string().trim().min(1).max(1000)).max(50).default([]),
    },
    (input) => ({ submission_id: input.submission_id, recommendation: input.recommendation, feedback: input.feedback, required_changes: input.required_changes }),
    () => undefined,
  );
}
