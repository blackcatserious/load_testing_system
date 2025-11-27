export class OrchestratorError extends Error {
  public readonly statusCode: number;
  public readonly code: string;
  public readonly details?: unknown;

  constructor(message: string, options?: { statusCode?: number; code?: string; details?: unknown; cause?: unknown }) {
    super(message);
    this.name = 'OrchestratorError';
    this.statusCode = options?.statusCode ?? 502;
    this.code = options?.code ?? 'ORCHESTRATOR_ERROR';
    this.details = options?.details;
    if (options?.cause) {
      (this as Record<string, unknown>).cause = options.cause;
    }
  }
}

export function normalizeError(err: unknown): OrchestratorError {
  if (err instanceof OrchestratorError) {
    return err;
  }

  if (err instanceof Error) {
    return new OrchestratorError(err.message, { cause: err });
  }

  return new OrchestratorError('Unknown orchestrator error', { details: err });
}
