import type { OperationClass } from "./contracts.js";

export interface InvocationReceipt {
  readonly requestId: string;
  readonly connectionId: string;
  readonly toolName: string;
  readonly operationClass: OperationClass;
  readonly requiredScope: string;
  readonly resultStatus: "success" | "denied" | "failed";
  readonly errorCode?: string;
  readonly startedAt: string;
  readonly completedAt: string;
}

export interface InvocationReceiptSink {
  record(receipt: InvocationReceipt): Promise<void>;
}

export class InMemoryInvocationReceiptSink implements InvocationReceiptSink {
  readonly #receipts: InvocationReceipt[] = [];

  public async record(receipt: InvocationReceipt): Promise<void> {
    this.#receipts.push(Object.freeze({ ...receipt }));
  }

  public all(): readonly InvocationReceipt[] {
    return this.#receipts.map((receipt) => ({ ...receipt }));
  }
}
