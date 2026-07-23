import { createHash, randomUUID } from "node:crypto";
import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import * as z from "zod/v4";
import type { CanonicalBridge } from "../bridge/canonicalBridge.js";
import { CanonicalBridgeError } from "../bridge/canonicalBridge.js";
import type { ConnectionContext } from "../contracts.js";
import type { InvocationReceipt, InvocationReceiptSink } from "../receipts.js";

export interface CreatorCampaignPlaybookToolDependencies {
  readonly connection: ConnectionContext;
  readonly receipts: InvocationReceiptSink;
  readonly bridge?: CanonicalBridge;
}

const playbookScopes = Object.freeze({
  "microgifter.creator_campaigns.playbooks.campaign_preparation.run": "creator_campaign_playbooks:campaign_preparation",
  "microgifter.creator_campaigns.playbooks.application_review.run": "creator_campaign_playbooks:application_review",
  "microgifter.creator_campaigns.playbooks.content_review.run": "creator_campaign_playbooks:content_review",
  "microgifter.creator_campaigns.playbooks.campaign_health.run": "creator_campaign_playbooks:campaign_health",
  "microgifter.creator_campaigns.playbooks.earnings_review.run": "creator_campaign_playbooks:earnings_review",
  "microgifter.creator_campaigns.playbooks.creator_outreach.run": "creator_campaign_playbooks:creator_outreach",
} as const);

type PlaybookToolName = keyof typeof playbookScopes;
const publicId = z.string().trim().min(8).max(80).regex(/^[A-Za-z0-9][A-Za-z0-9_-]+$/);
const common = {
  automation_id: z.string().uuid(),
  idempotency_key: z.string().trim().min(8).max(190).regex(/^[A-Za-z0-9._:-]+$/),
  requested_reason: z.string().trim().min(1).max(1000),
  artifact_title: z.string().trim().min(1).max(190).optional(),
  artifact_summary: z.string().trim().min(1).max(500).optional(),
};
const annotations = {
  readOnlyHint: false,
  destructiveHint: false,
  idempotentHint: true,
  openWorldHint: false,
} as const;

function hasAuthority(connection: ConnectionContext, scope: string): boolean {
  return connection.maximumOperationClass === "draft" && connection.scopes.includes(scope);
}

function fingerprint(value: unknown): string {
  return createHash("sha256").update(JSON.stringify(value ?? {})).digest("hex");
}

function output(payload: Readonly<Record<string, unknown>>, isError = false) {
  return {
    ...(isError ? { isError: true as const } : {}),
    content: [{ type: "text" as const, text: JSON.stringify(payload) }],
    structuredContent: payload,
  };
}

async function record(
  sink: InvocationReceiptSink,
  values: Omit<InvocationReceipt, "completedAt">,
): Promise<void> {
  await sink.record({ ...values, completedAt: new Date().toISOString() });
}

function register(
  server: McpServer,
  dependencies: CreatorCampaignPlaybookToolDependencies,
  name: PlaybookToolName,
  description: string,
  inputSchema: Record<string, z.ZodType>,
): void {
  const scope = playbookScopes[name];
  if (!hasAuthority(dependencies.connection, scope)) return;
  server.registerTool(name, { description, inputSchema, annotations }, async (input) => {
    const requestId = randomUUID();
    const startedAt = new Date().toISOString();
    const started = Date.now();
    if (!dependencies.bridge?.runCreatorCampaignPlaybook) {
      await record(dependencies.receipts, {
        requestId,
        connectionId: dependencies.connection.connectionId,
        toolName: name,
        operationClass: "draft",
        requiredScope: scope,
        inputFingerprint: fingerprint(input),
        resultStatus: "failed",
        httpStatus: 503,
        durationMs: Date.now() - started,
        recordCount: 0,
        errorCode: "MICROGIFTER_TOOL_DISABLED",
        startedAt,
      });
      return output({
        ok: false,
        error: {
          code: "MICROGIFTER_TOOL_DISABLED",
          message: "The Creator Campaign bounded-playbook bridge is disabled.",
        },
      }, true);
    }
    try {
      const data = await dependencies.bridge.runCreatorCampaignPlaybook(
        dependencies.connection.connectionId,
        name,
        { ...input, source_request_id: requestId },
      );
      await record(dependencies.receipts, {
        requestId,
        connectionId: dependencies.connection.connectionId,
        toolName: name,
        operationClass: "draft",
        requiredScope: scope,
        inputFingerprint: fingerprint(input),
        resultStatus: "success",
        httpStatus: 200,
        durationMs: Date.now() - started,
        recordCount: 1,
        startedAt,
      });
      return output({
        ok: true,
        request_id: requestId,
        data,
        execution: {
          performed: false,
          status: "owner_review_artifact_created",
          canonical_action_request_created: false,
          canonical_mutation: false,
          external_effect: false,
        },
      });
    } catch (error) {
      const failure = error instanceof CanonicalBridgeError
        ? error
        : new CanonicalBridgeError(
          "The bounded Creator Campaign playbook could not be completed.",
          "MCP_CREATOR_CAMPAIGN_PLAYBOOK_FAILED",
          500,
        );
      await record(dependencies.receipts, {
        requestId,
        connectionId: dependencies.connection.connectionId,
        toolName: name,
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
      return output({ ok: false, error: { code: failure.code, message: failure.message } }, true);
    }
  });
}

export function registerCreatorCampaignPlaybookTools(
  server: McpServer,
  dependencies: CreatorCampaignPlaybookToolDependencies,
): void {
  register(
    server,
    dependencies,
    "microgifter.creator_campaigns.playbooks.campaign_preparation.run",
    "Run an owner-configured campaign preparation playbook and create one non-convertible review artifact. This never creates, publishes, or changes a native campaign.",
    {
      ...common,
      campaign_id: publicId.optional(),
      proposal: z.object({
        campaign: z.record(z.string(), z.unknown()),
        products: z.array(z.record(z.string(), z.unknown())).max(50).default([]),
        eligibility_rules: z.array(z.record(z.string(), z.unknown())).max(50).default([]),
        deliverables: z.array(z.record(z.string(), z.unknown())).max(50).default([]),
        compensation_rules: z.array(z.record(z.string(), z.unknown())).max(50).default([]),
      }),
    },
  );

  register(
    server,
    dependencies,
    "microgifter.creator_campaigns.playbooks.application_review.run",
    "Run an owner-configured Creator application review playbook and draft a recommendation without approving or declining the application.",
    {
      ...common,
      campaign_id: publicId,
      application_id: publicId,
      recommendation: z.enum(["approve", "decline", "request_information", "needs_review"]),
      fit_score: z.number().int().min(0).max(100),
      eligibility_matches: z.array(z.string().trim().min(1).max(2000)).max(50).default([]),
      eligibility_gaps: z.array(z.string().trim().min(1).max(2000)).max(50).default([]),
      missing_information: z.array(z.string().trim().min(1).max(2000)).max(50).default([]),
      rationale: z.string().trim().min(1).max(8000),
      draft_message: z.string().max(8000).optional(),
    },
  );

  register(
    server,
    dependencies,
    "microgifter.creator_campaigns.playbooks.content_review.run",
    "Run an owner-configured content review playbook and draft submission feedback without approving, rejecting, or requesting a revision.",
    {
      ...common,
      campaign_id: publicId,
      submission_id: publicId,
      recommendation: z.enum(["approve", "request_revision", "reject", "request_information"]),
      talking_points_met: z.boolean(),
      disclosure_present: z.boolean(),
      links_valid: z.boolean(),
      prohibited_claims_found: z.boolean(),
      findings: z.array(z.string().trim().min(1).max(2000)).max(50).default([]),
      required_changes: z.array(z.string().trim().min(1).max(2000)).max(50).default([]),
      feedback: z.string().trim().min(1).max(10000),
    },
  );

  register(
    server,
    dependencies,
    "microgifter.creator_campaigns.playbooks.campaign_health.run",
    "Run an owner-configured campaign health playbook and create a reviewable evidence and risk report. No canonical action request is created.",
    {
      ...common,
      campaign_id: publicId,
      days: z.number().int().min(1).max(366).default(30),
      assessment_notes: z.string().max(10000).optional(),
      recommended_actions: z.array(z.record(z.string(), z.unknown())).max(50).default([]),
    },
  );

  register(
    server,
    dependencies,
    "microgifter.creator_campaigns.playbooks.earnings_review.run",
    "Run an owner-configured earnings review playbook and draft a financial recommendation. Earnings, payouts, disputes, and money remain unchanged.",
    {
      ...common,
      campaign_id: publicId,
      earning_id: publicId,
      recommendation: z.enum(["approve", "hold", "reject", "reverse"]),
      agreement_verified: z.boolean(),
      attribution_verified: z.boolean(),
      compensation_rule_verified: z.boolean(),
      budget_verified: z.boolean(),
      refund_clear: z.boolean(),
      fraud_clear: z.boolean(),
      duplicate_clear: z.boolean(),
      rationale: z.string().trim().min(1).max(10000),
    },
  );

  register(
    server,
    dependencies,
    "microgifter.creator_campaigns.playbooks.creator_outreach.run",
    "Run an owner-configured Creator outreach playbook and draft a ranked invitation list. Only active approved Microgifter Creators are accepted and no invitation is sent.",
    {
      ...common,
      campaign_id: publicId,
      candidates: z.array(z.object({
        creator_profile_id: publicId,
        fit_score: z.number().int().min(0).max(100),
        rationale: z.string().trim().min(1).max(2000),
        invitation_message: z.string().trim().min(1).max(8000),
      })).min(1).max(25),
    },
  );
}
