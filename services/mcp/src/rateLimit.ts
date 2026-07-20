export interface RateLimitDecision {
  readonly allowed: boolean;
  readonly remaining: number;
  readonly retryAfterSeconds: number;
}

interface Bucket {
  count: number;
  startedAt: number;
}

export class FixedWindowRateLimiter {
  readonly #buckets = new Map<string, Bucket>();

  public constructor(
    private readonly maximumRequests: number,
    private readonly windowMs: number,
  ) {
    if (!Number.isSafeInteger(maximumRequests) || maximumRequests < 1) {
      throw new Error("maximumRequests must be a positive integer.");
    }
    if (!Number.isSafeInteger(windowMs) || windowMs < 1) {
      throw new Error("windowMs must be a positive integer.");
    }
  }

  public consume(key: string, now = Date.now()): RateLimitDecision {
    const current = this.#buckets.get(key);
    const bucket = current && now - current.startedAt < this.windowMs
      ? current
      : { count: 0, startedAt: now };

    bucket.count += 1;
    this.#buckets.set(key, bucket);
    const allowed = bucket.count <= this.maximumRequests;
    const retryAfterSeconds = Math.max(1, Math.ceil((bucket.startedAt + this.windowMs - now) / 1000));

    return {
      allowed,
      remaining: Math.max(0, this.maximumRequests - bucket.count),
      retryAfterSeconds,
    };
  }
}
