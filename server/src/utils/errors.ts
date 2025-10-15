export class OrchestrationError extends Error {
  public readonly statusCode: number;
  public readonly details?: any;

  constructor(message: string, statusCode = 502, details?: any) {
    super(message);
    this.name = 'OrchestrationError';
    this.statusCode = statusCode;
    this.details = details;
  }
}

export const isOrchestrationFailure = (payload: any): boolean => {
  if (!payload) {
    return true;
  }

  if (typeof payload !== 'object') {
    return true;
  }

  if ('status' in payload) {
    return payload.status === 'error';
  }

  if ('success' in payload) {
    return payload.success === false;
  }

  return false;
};
