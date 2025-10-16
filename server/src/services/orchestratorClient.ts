import axios, { type AxiosError, type AxiosInstance, type AxiosRequestConfig } from 'axios';
import { orchestratorConfig, type OrchestratorConfig } from '../config.js';
import { OrchestratorError } from '../utils/errors.js';
import type {
  PhpApiResponse,
  RawDashboardMetrics,
  RawGroup,
  RawReport,
  RawRun,
} from '../types/orchestrator.js';

interface RequestOptions extends AxiosRequestConfig {
  suppressStatusCheck?: boolean;
}

export class OrchestratorClient {
  private readonly client: AxiosInstance;

  constructor(private readonly config: OrchestratorConfig = orchestratorConfig) {
    this.client = axios.create({
      baseURL: config.baseUrl,
      timeout: config.timeoutMs,
    });
  }

  getBaseUrl(): string {
    return this.config.baseUrl;
  }

  private async request<T>(options: RequestOptions): Promise<T> {
    try {
      const response = await this.client.request<T>(options);
      return response.data;
    } catch (err) {
      throw this.normalizeAxiosError(err);
    }
  }

  private normalizeAxiosError(err: unknown): OrchestratorError {
    if (!axios.isAxiosError(err)) {
      return err instanceof OrchestratorError ? err : new OrchestratorError('Unexpected orchestrator error', { cause: err });
    }

    const axiosError = err as AxiosError<any>;
    const statusCode = axiosError.response?.status ?? 502;
    const payload = axiosError.response?.data;
    const message =
      typeof payload?.message === 'string'
        ? payload.message
        : typeof payload?.error === 'string'
        ? payload.error
        : axiosError.message;

    return new OrchestratorError(message, {
      statusCode,
      code: 'ORCHESTRATOR_HTTP_ERROR',
      details: payload,
      cause: err,
    });
  }

  private ensureSuccess<T>(payload: PhpApiResponse<T> | T, context: string): T {
    if (payload && typeof payload === 'object' && 'status' in payload) {
      const typed = payload as PhpApiResponse<T>;
      if (typed.status === 'error') {
        throw new OrchestratorError(typed.message || `Orchestrator error: ${context}`, {
          code: 'ORCHESTRATOR_RESPONSE_ERROR',
          details: typed,
        });
      }
      if (typed.status === 'success') {
        if (typed.data === undefined) {
          throw new OrchestratorError(`Missing data from orchestrator response: ${context}`, {
            code: 'ORCHESTRATOR_EMPTY_RESPONSE',
            details: typed,
          });
        }
        return typed.data as T;
      }
    }

    if (payload && typeof payload === 'object' && 'success' in payload) {
      const typed = payload as PhpApiResponse<T> & { success: boolean };
      if (!typed.success) {
        throw new OrchestratorError(typed.message || typed.error || `Orchestrator error: ${context}`, {
          code: 'ORCHESTRATOR_RESPONSE_ERROR',
          details: typed,
        });
      }
      return (typed.data ?? (typed as unknown as T)) as T;
    }

    return payload as T;
  }

  async fetchRuns(limit = 50): Promise<RawRun[]> {
    const payload = await this.request<PhpApiResponse<{ runs: RawRun[] }>>({
      url: '/runs_endpoint.php',
      method: 'GET',
      params: { limit },
    });

    const data = this.ensureSuccess(payload, 'fetchRuns');
    return Array.isArray(data.runs) ? data.runs : [];
  }

  async fetchRun(runId: string): Promise<RawRun> {
    const payload = await this.request<PhpApiResponse<RawRun>>({
      url: '/runs_endpoint.php',
      method: 'GET',
      params: { run_id: runId },
    });

    return this.ensureSuccess(payload, 'fetchRun');
  }

  async fetchDashboardMetrics(includeAntiDetect = true): Promise<RawDashboardMetrics> {
    const payload = await this.request<RawDashboardMetrics>({
      url: '/metrics_endpoint.php',
      method: 'GET',
      params: { include_anti_detect: includeAntiDetect ? 1 : 0 },
    });

    const data = this.ensureSuccess(payload as unknown as PhpApiResponse<RawDashboardMetrics>, 'fetchDashboardMetrics');
    return data;
  }

  async fetchTestPlans(limit = 50): Promise<RawGroup[]> {
    const payload = await this.request<{ success: boolean; groups?: RawGroup[]; data?: { groups?: RawGroup[] } } & PhpApiResponse>({
      url: '/group_runs_endpoint.php',
      method: 'GET',
      params: { action: 'list', limit },
    });

    const data = this.ensureSuccess(payload, 'fetchTestPlans');
    if (Array.isArray((data as any).groups)) {
      return (data as any).groups as RawGroup[];
    }
    if (Array.isArray((data as any)?.data?.groups)) {
      return (data as any).data.groups as RawGroup[];
    }
    return [];
  }

  async fetchReports(): Promise<RawReport[]> {
    const payload = await this.request<PhpApiResponse<{ reports: RawReport[] }>>({
      url: '/reports_endpoint.php',
      method: 'GET',
      params: { action: 'list' },
    });

    const data = this.ensureSuccess(payload, 'fetchReports');
    return Array.isArray(data.reports) ? data.reports : [];
  }
}

export const defaultOrchestratorClient = new OrchestratorClient();
