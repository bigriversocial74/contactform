import test from "node:test";
import assert from "node:assert/strict";

import {
  DISABLED_FOUNDATION_CONFIG,
  assertActionTransition,
  assertAutomationTransition,
  assertRunTransition,
  canTransitionAction,
  canTransitionAutomation,
  canTransitionRun,
  validateFoundationConfig,
} from "../dist/index.js";

test("foundation is fail-closed by default", () => {
  assert.equal(DISABLED_FOUNDATION_CONFIG.enabled, false);
  assert.equal(DISABLED_FOUNDATION_CONFIG.externalHttpEnabled, false);
  assert.equal(DISABLED_FOUNDATION_CONFIG.schedulerEnabled, false);
  assert.equal(DISABLED_FOUNDATION_CONFIG.workerEnabled, false);
  assert.equal(DISABLED_FOUNDATION_CONFIG.writeToolsEnabled, false);
  assert.equal(DISABLED_FOUNDATION_CONFIG.boundedAutomationEnabled, false);
  assert.doesNotThrow(() => validateFoundationConfig(DISABLED_FOUNDATION_CONFIG));
});

test("unsafe child flags cannot be enabled under a disabled platform", () => {
  assert.throws(
    () => validateFoundationConfig({ ...DISABLED_FOUNDATION_CONFIG, schedulerEnabled: true }),
    /cannot be enabled/,
  );
});

test("bounded autonomy requires workers and write tools", () => {
  assert.throws(
    () =>
      validateFoundationConfig({
        enabled: true,
        externalHttpEnabled: false,
        schedulerEnabled: true,
        workerEnabled: false,
        writeToolsEnabled: false,
        boundedAutomationEnabled: true,
      }),
    /requires workers and write tools/,
  );
});

test("automation lifecycle permits pause and revocation but not resurrection", () => {
  assert.equal(canTransitionAutomation("active", "paused"), true);
  assert.equal(canTransitionAutomation("paused", "active"), true);
  assert.equal(canTransitionAutomation("revoked", "active"), false);
  assert.doesNotThrow(() => assertAutomationTransition("draft", "active"));
  assert.throws(() => assertAutomationTransition("completed", "active"), /Invalid automation transition/);
});

test("run lifecycle requires evaluation before execution", () => {
  assert.equal(canTransitionRun("queued", "evaluating"), true);
  assert.equal(canTransitionRun("queued", "executing"), false);
  assert.doesNotThrow(() => assertRunTransition("approved", "executing"));
  assert.throws(() => assertRunTransition("succeeded", "executing"), /Invalid run transition/);
});

test("approval-gated action cannot execute from waiting state", () => {
  assert.equal(canTransitionAction("waiting_for_approval", "approved"), true);
  assert.equal(canTransitionAction("waiting_for_approval", "executing"), false);
  assert.doesNotThrow(() => assertActionTransition("approved", "executing"));
  assert.throws(() => assertActionTransition("rejected", "executing"), /Invalid action transition/);
});
