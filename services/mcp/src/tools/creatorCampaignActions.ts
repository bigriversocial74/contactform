import { createHash, randomUUID } from "node:crypto";
import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import * as z from "zod/v4";
import type { CanonicalBridge } from "../bridge/canonicalBridge.js";
import { CanonicalBridgeError } from "../bridge/canonicalBridge.js";
import type { ConnectionContext } from "../contracts.js";
import type { InvocationReceipt, InvocationReceiptSink } from "../receipts.js";

export interface CreatorCampaignActionToolDependencies {
  readonly connection: ConnectionContext;
  readonly receipts: InvocationReceiptSink;
  readonly bridge?: CanonicalBridge;
}

const actionScopes = Object.freeze({
  "microgifter.creator_campaigns.publish": "creator_campaigns:publish",
  "microgifter.creator_campaigns.schedule": "creator_campaigns:publish",
  "microgifter.creator_campaigns.pause": "creator_campaigns:publish",
  "microgifter.creator_campaigns.resume": "creator_campaigns:publish",
  "microgifter.creator_campaigns.complete": "creator_campaigns:publish",
  "microgifter.creator_campaigns.cancel": "creator_campaigns:publish",
  "microgifter.creator_campaigns.application.approve": "creator_campaign_participants:manage",
  "microgifter.creator_campaigns.application.decline": "creator_campaign_participants:manage",
  "microgifter.creator_campaigns.invitation.send": "creator_campaign_participants:manage",
  "microgifter.creator_campaigns.agreement.offer": "creator_campaign_agreements:manage",
  "microgifter.creator_campaigns.participant.suspend": "creator_campaign_participants:manage",
  "microgifter.creator_campaigns.participant.remove": "creator_campaign_participants:manage",
  "microgifter.creator_campaigns.submission.approve": "creator_campaign_submissions:review",
  "microgifter.creator_campaigns.submission.request_revision": "creator_campaign_submissions:review",
  "microgifter.creator_campaigns.submission.reject": "creator_campaign_submissions:review",
  "microgifter.creator_campaigns.attribution.override": "creator_campaign_attribution:manage",
  "microgifter.creator_campaigns.earning.approve": "creator_campaign_earnings:manage",
  "microgifter.creator_campaigns.earning.hold": "creator_campaign_earnings:manage",
  "microgifter.creator_campaigns.earning.reject": "creator_campaign_earnings:manage",
  "microgifter.creator_campaigns.earning.reverse": "creator_campaign_earnings:manage",
  "microgifter.creator_campaigns.payout.record": "creator_campaign_payouts:manage",
  "microgifter.creator_campaigns.dispute.resolve": "creator_campaign_disputes:manage",
} as const);

type ActionToolName = keyof typeof actionScopes;
const id = z.string().trim().min(8).max(80).regex(/^[A-Za-z0-9][A-Za-z0-9_-]+$/);
const common = {
  grant_id: z.string().uuid(),
  idempotency_key: z.string().trim().min(8).max(190).regex(/^[A-Za-z0-9._:-]+$/),
  requested_reason: z.string().trim().min(1).max(1000),
};
const lock = { expected_lock_version: z.number().int().min(1) };
const reason = { reason: z.string().trim().min(1).max(2000) };
const annotations = { readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false } as const;

function hasAuthority(connection: ConnectionContext, scope: string): boolean {
  return connection.maximumOperationClass === "approval_gated" && connection.scopes.includes(scope);
}
function fingerprint(value: unknown): string { return createHash("sha256").update(JSON.stringify(value ?? {})).digest("hex"); }
function output(payload: Readonly<Record<string, unknown>>, isError = false) {
  return { ...(isError ? { isError: true as const } : {}), content: [{ type: "text" as const, text: JSON.stringify(payload) }], structuredContent: payload };
}
async function record(sink: InvocationReceiptSink, values: Omit<InvocationReceipt,"completedAt">): Promise<void> {
  await sink.record({ ...values, completedAt: new Date().toISOString() });
}

function register(
  server: McpServer,
  dependencies: CreatorCampaignActionToolDependencies,
  name: ActionToolName,
  description: string,
  inputSchema: Record<string,z.ZodType>,
): void {
  const scope=actionScopes[name];
  if(!hasAuthority(dependencies.connection,scope)) return;
  server.registerTool(name,{description,inputSchema,annotations},async(input)=>{
    const requestId=randomUUID();const startedAt=new Date().toISOString();const started=Date.now();
    if(!dependencies.bridge?.requestCreatorCampaignAction){
      await record(dependencies.receipts,{requestId,connectionId:dependencies.connection.connectionId,toolName:name,operationClass:"approval_gated",requiredScope:scope,inputFingerprint:fingerprint(input),resultStatus:"failed",httpStatus:503,durationMs:Date.now()-started,recordCount:0,errorCode:"MICROGIFTER_TOOL_DISABLED",startedAt});
      return output({ok:false,error:{code:"MICROGIFTER_TOOL_DISABLED",message:"The Creator Campaign action-request bridge is disabled."}},true);
    }
    try{
      const data=await dependencies.bridge.requestCreatorCampaignAction(dependencies.connection.connectionId,name,input);
      await record(dependencies.receipts,{requestId,connectionId:dependencies.connection.connectionId,toolName:name,operationClass:"approval_gated",requiredScope:scope,inputFingerprint:fingerprint(input),resultStatus:"success",httpStatus:200,durationMs:Date.now()-started,recordCount:1,startedAt});
      return output({ok:true,request_id:requestId,data,execution:{performed:false,status:"waiting_for_owner_approval",owner_execution_required:true}});
    }catch(error){
      const failure=error instanceof CanonicalBridgeError?error:new CanonicalBridgeError("The action request could not be created.","MCP_CREATOR_CAMPAIGN_ACTION_REQUEST_FAILED",500);
      await record(dependencies.receipts,{requestId,connectionId:dependencies.connection.connectionId,toolName:name,operationClass:"approval_gated",requiredScope:scope,inputFingerprint:fingerprint(input),resultStatus:failure.status===403?"denied":failure.status===422?"validation_error":"failed",httpStatus:failure.status,durationMs:Date.now()-started,recordCount:0,errorCode:failure.code,...(failure.status===403?{denialReason:failure.message}:{}),startedAt});
      return output({ok:false,error:{code:failure.code,message:failure.message}},true);
    }
  });
}

export function registerCreatorCampaignActionTools(server: McpServer, dependencies: CreatorCampaignActionToolDependencies): void {
  const campaignSchema={...common,...lock,campaign_id:id,...reason};
  register(server,dependencies,"microgifter.creator_campaigns.publish","Request owner approval to publish a ready Creator Campaign. This tool never publishes directly.",campaignSchema);
  register(server,dependencies,"microgifter.creator_campaigns.schedule","Request owner approval to schedule a ready Creator Campaign using its existing future starts_at value.",campaignSchema);
  register(server,dependencies,"microgifter.creator_campaigns.pause","Request owner approval to pause an active Creator Campaign.",campaignSchema);
  register(server,dependencies,"microgifter.creator_campaigns.resume","Request owner approval to resume a paused Creator Campaign.",campaignSchema);
  register(server,dependencies,"microgifter.creator_campaigns.complete","Request owner approval to complete a Creator Campaign.",campaignSchema);
  register(server,dependencies,"microgifter.creator_campaigns.cancel","Request owner approval to cancel a Creator Campaign. Cancellation remains a separate critical owner action.",campaignSchema);

  const applicationSchema={...common,...lock,application_id:id,...reason,internal_note:z.string().max(16000).optional()};
  register(server,dependencies,"microgifter.creator_campaigns.application.approve","Request owner approval to approve a Creator application through the native application service.",applicationSchema);
  register(server,dependencies,"microgifter.creator_campaigns.application.decline","Request owner approval to decline a Creator application through the native application service.",applicationSchema);
  register(server,dependencies,"microgifter.creator_campaigns.invitation.send","Request owner approval to send a Creator invitation. No invitation is sent by this tool.",{...common,campaign_id:id,creator_profile_id:id,invitation_message:z.string().min(1).max(8000),internal_note:z.string().max(16000).optional(),response_deadline_at:z.string().datetime({offset:true}).optional()});
  register(server,dependencies,"microgifter.creator_campaigns.agreement.offer","Request owner approval to offer an immutable Creator agreement version.",{...common,participant_id:id,summary:z.string().max(1000).optional(),terms_text:z.string().min(1).max(32000),deliverables:z.string().max(16000).optional(),compensation:z.string().max(16000).optional(),content_rights:z.string().max(16000).optional(),disclosures:z.string().max(16000).optional(),cancellation:z.string().max(16000).optional(),reversal:z.string().max(16000).optional(),creator_specific:z.string().max(16000).optional(),change_summary:z.string().max(2000).optional(),requires_reacceptance:z.boolean().default(true)});
  const participantSchema={...common,...lock,participant_id:id,...reason};
  register(server,dependencies,"microgifter.creator_campaigns.participant.suspend","Request owner approval to suspend a Creator Campaign participant.",participantSchema);
  register(server,dependencies,"microgifter.creator_campaigns.participant.remove","Request owner approval to remove a Creator Campaign participant.",participantSchema);

  const submissionSchema={...common,...lock,submission_id:id,feedback:z.string().trim().min(1).max(32000)};
  register(server,dependencies,"microgifter.creator_campaigns.submission.approve","Request owner approval to approve a Creator submission.",submissionSchema);
  register(server,dependencies,"microgifter.creator_campaigns.submission.request_revision","Request owner approval to request a Creator submission revision.",submissionSchema);
  register(server,dependencies,"microgifter.creator_campaigns.submission.reject","Request owner approval to reject a Creator submission.",submissionSchema);
  register(server,dependencies,"microgifter.creator_campaigns.attribution.override","Request owner approval to override an attribution using the native attribution service.",{...common,...lock,attribution_id:id,source_id:id.optional(),...reason});

  const earningSchema={...common,earning_id:id,...reason};
  register(server,dependencies,"microgifter.creator_campaigns.earning.approve","Request owner approval to approve a Creator earning decision.",earningSchema);
  register(server,dependencies,"microgifter.creator_campaigns.earning.hold","Request owner approval to place a Creator earning on hold.",earningSchema);
  register(server,dependencies,"microgifter.creator_campaigns.earning.reject","Request owner approval to reject a Creator earning.",earningSchema);
  register(server,dependencies,"microgifter.creator_campaigns.earning.reverse","Request owner approval to reverse a Creator earning through the append-only native compensation service.",earningSchema);
  register(server,dependencies,"microgifter.creator_campaigns.payout.record","Request owner approval to create an internal payout record from eligible committed reservations. No payment provider is called.",{...common,participant_id:id,currency:z.string().trim().length(3).regex(/^[A-Za-z]{3}$/).transform(value=>value.toUpperCase()),...reason});
  register(server,dependencies,"microgifter.creator_campaigns.dispute.resolve","Request owner approval to resolve a Creator Campaign dispute.",{...common,dispute_id:id,resolution:z.enum(["resolved_upheld","resolved_adjusted","rejected"]),resolution_note:z.string().trim().min(1).max(4000)});
}
