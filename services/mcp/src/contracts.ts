export const OPERATION_CLASSES = [
  "read",
  "monitor",
  "recommend",
  "task",
  "draft",
  "approval_gated",
  "bounded_auto",
  "prohibited",
] as const;

export type OperationClass = (typeof OPERATION_CLASSES)[number];

export const AUTOMATION_STATUSES = [
  "draft",
  "pending_approval",
  "active",
  "paused",
  "completed",
  "failed",
  "expired",
  "revoked",
] as const;

export type AutomationStatus = (typeof AUTOMATION_STATUSES)[number];

export const RUN_STATUSES = [
  "queued",
  "evaluating",
  "waiting_for_approval",
  "approved",
  "executing",
  "succeeded",
  "partially_succeeded",
  "failed",
  "cancelled",
  "dead_lettered",
] as const;

export type RunStatus = (typeof RUN_STATUSES)[number];

export const ACTION_STATUSES = [
  "proposed",
  "waiting_for_approval",
  "approved",
  "rejected",
  "executing",
  "succeeded",
  "failed",
  "cancelled",
  "expired",
] as const;

export type ActionStatus = (typeof ACTION_STATUSES)[number];

export const TRIGGER_TYPES = [
  "manual",
  "fixed_schedule",
  "recurring_schedule",
  "canonical_event",
  "condition",
  "monitor_threshold",
] as const;

export type TriggerType = (typeof TRIGGER_TYPES)[number];

export const RISK_LEVELS = ["low", "medium", "high", "critical"] as const;
export type RiskLevel = (typeof RISK_LEVELS)[number];

export interface WorkspaceRef {
  readonly type: string;
  readonly id: string;
}

export interface ConnectionContext {
  readonly connectionId: string;
  readonly clientKey: string;
  readonly userId: string;
  readonly workspace?: WorkspaceRef;
  readonly scopes: readonly string[];
  readonly maximumOperationClass: OperationClass;
  readonly tokenVersion: number;
  readonly expiresAt?: string;
}

export interface AutomationBudget {
  readonly currency?: string;
  readonly perRunAmountCents?: number;
  readonly dailyAmountCents?: number;
  readonly lifetimeAmountCents?: number;
  readonly perRunQuantity?: number;
  readonly dailyQuantity?: number;
  readonly lifetimeQuantity?: number;
}

export interface AutomationGrant {
  readonly id: string;
  readonly version: number;
  readonly connectionId: string;
  readonly authorizingUserId: string;
  readonly workspace?: WorkspaceRef;
  readonly status: "draft" | "active" | "paused" | "expired" | "revoked";
  readonly maximumOperationClass: OperationClass;
  readonly allowedTools: readonly string[];
  readonly allowedPlaybooks: readonly string[];
  readonly allowedTriggers: readonly TriggerType[];
  readonly approvalPolicy: "always" | "risk_based" | "grant_based" | "never_for_allowlisted";
  readonly riskCeiling: RiskLevel;
  readonly budget: AutomationBudget;
  readonly maximumFrequencySeconds?: number;
  readonly maximumConcurrentRuns: number;
  readonly targetPolicy: Readonly<Record<string, unknown>>;
  readonly startsAt?: string;
  readonly expiresAt?: string;
  readonly revocationVersion: number;
}

export interface AutomationDefinition {
  readonly id: string;
  readonly grantId: string;
  readonly ownerUserId: string;
  readonly workspace?: WorkspaceRef;
  readonly name: string;
  readonly playbookKey: string;
  readonly status: AutomationStatus;
  readonly version: number;
  readonly configuration: Readonly<Record<string, unknown>>;
  readonly timezone: string;
}

export interface AutomationTrigger {
  readonly id: string;
  readonly automationId: string;
  readonly type: TriggerType;
  readonly status: "active" | "paused" | "expired" | "revoked";
  readonly configuration: Readonly<Record<string, unknown>>;
  readonly nextDueAt?: string;
}

export interface AutomationRun {
  readonly id: string;
  readonly automationId: string;
  readonly grantId: string;
  readonly triggerId?: string;
  readonly status: RunStatus;
  readonly idempotencyKey: string;
  readonly attempt: number;
  readonly maximumAttempts: number;
  readonly scheduledAt?: string;
  readonly leaseOwner?: string;
  readonly leaseExpiresAt?: string;
}

export interface AutomationAction {
  readonly id: string;
  readonly runId: string;
  readonly sequence: number;
  readonly toolName: string;
  readonly operationClass: OperationClass;
  readonly riskLevel: RiskLevel;
  readonly status: ActionStatus;
  readonly approvalRequired: boolean;
  readonly idempotencyKey: string;
  readonly inputFingerprint: string;
  readonly freshStateToken?: string;
}

export interface AutomationRepository {
  getGrant(id: string): Promise<AutomationGrant | null>;
  getAutomation(id: string): Promise<AutomationDefinition | null>;
  getTrigger(id: string): Promise<AutomationTrigger | null>;
  getRun(id: string): Promise<AutomationRun | null>;
  getAction(id: string): Promise<AutomationAction | null>;
  saveRun(run: AutomationRun): Promise<void>;
  saveAction(action: AutomationAction): Promise<void>;
}

export interface BridgeRequest<TInput extends Readonly<Record<string, unknown>>> {
  readonly requestId: string;
  readonly contract: string;
  readonly version: "1.0";
  readonly connection: ConnectionContext;
  readonly workspace?: WorkspaceRef;
  readonly requiredScope: string;
  readonly operationClass: OperationClass;
  readonly input: TInput;
}

export interface BridgeSuccess<TOutput> {
  readonly ok: true;
  readonly requestId: string;
  readonly contract: string;
  readonly version: "1.0";
  readonly output: TOutput;
}

export interface BridgeFailure {
  readonly ok: false;
  readonly requestId: string;
  readonly contract: string;
  readonly version: "1.0";
  readonly error: {
    readonly code: string;
    readonly message: string;
    readonly retryable: boolean;
  };
}

export type BridgeResponse<TOutput> = BridgeSuccess<TOutput> | BridgeFailure;

export interface CanonicalBridge {
  query<TInput extends Readonly<Record<string, unknown>>, TOutput>(
    request: BridgeRequest<TInput>,
  ): Promise<BridgeResponse<TOutput>>;

  command<TInput extends Readonly<Record<string, unknown>>, TOutput>(
    request: BridgeRequest<TInput>,
  ): Promise<BridgeResponse<TOutput>>;
}

export interface QueueMessage {
  readonly id: string;
  readonly runId: string;
  readonly automationId: string;
  readonly grantId: string;
  readonly attempt: number;
  readonly availableAt: string;
}

export interface AcquiredLease {
  readonly key: string;
  readonly owner: string;
  readonly expiresAt: string;
}

export interface AutomationQueue {
  enqueue(message: QueueMessage): Promise<void>;
  acquire(workerId: string, leaseSeconds: number): Promise<QueueMessage | null>;
  acknowledge(messageId: string): Promise<void>;
  retry(messageId: string, availableAt: string, reasonCode: string): Promise<void>;
  deadLetter(messageId: string, reasonCode: string): Promise<void>;
}

export interface WorkerLeaseRepository {
  acquire(key: string, owner: string, leaseSeconds: number): Promise<AcquiredLease | null>;
  heartbeat(lease: AcquiredLease, leaseSeconds: number): Promise<AcquiredLease>;
  release(lease: AcquiredLease): Promise<void>;
}
