import type { ActionStatus, AutomationStatus, RunStatus } from "./contracts.js";

const automationTransitions: Readonly<Record<AutomationStatus, readonly AutomationStatus[]>> = {
  draft: ["pending_approval", "active", "revoked"],
  pending_approval: ["active", "paused", "revoked", "expired"],
  active: ["paused", "completed", "failed", "expired", "revoked"],
  paused: ["active", "failed", "expired", "revoked"],
  completed: [],
  failed: ["paused", "revoked"],
  expired: [],
  revoked: [],
};

const runTransitions: Readonly<Record<RunStatus, readonly RunStatus[]>> = {
  queued: ["evaluating", "cancelled", "failed"],
  evaluating: ["waiting_for_approval", "approved", "executing", "failed", "cancelled"],
  waiting_for_approval: ["approved", "cancelled", "failed"],
  approved: ["executing", "cancelled", "failed"],
  executing: ["succeeded", "partially_succeeded", "failed", "cancelled", "dead_lettered"],
  succeeded: [],
  partially_succeeded: [],
  failed: ["queued", "dead_lettered", "cancelled"],
  cancelled: [],
  dead_lettered: [],
};

const actionTransitions: Readonly<Record<ActionStatus, readonly ActionStatus[]>> = {
  proposed: ["waiting_for_approval", "approved", "executing", "cancelled", "expired"],
  waiting_for_approval: ["approved", "rejected", "cancelled", "expired"],
  approved: ["executing", "cancelled", "expired"],
  rejected: [],
  executing: ["succeeded", "failed", "cancelled"],
  succeeded: [],
  failed: [],
  cancelled: [],
  expired: [],
};

export function canTransitionAutomation(from: AutomationStatus, to: AutomationStatus): boolean {
  return automationTransitions[from].includes(to);
}

export function canTransitionRun(from: RunStatus, to: RunStatus): boolean {
  return runTransitions[from].includes(to);
}

export function canTransitionAction(from: ActionStatus, to: ActionStatus): boolean {
  return actionTransitions[from].includes(to);
}

export function assertAutomationTransition(from: AutomationStatus, to: AutomationStatus): void {
  if (!canTransitionAutomation(from, to)) {
    throw new Error(`Invalid automation transition: ${from} -> ${to}`);
  }
}

export function assertRunTransition(from: RunStatus, to: RunStatus): void {
  if (!canTransitionRun(from, to)) {
    throw new Error(`Invalid run transition: ${from} -> ${to}`);
  }
}

export function assertActionTransition(from: ActionStatus, to: ActionStatus): void {
  if (!canTransitionAction(from, to)) {
    throw new Error(`Invalid action transition: ${from} -> ${to}`);
  }
}
