import axios, { type AxiosError, type AxiosResponse } from 'axios';
import type {
  ApiResponse,
  DashboardMetrics,
  ReportSummary,
  TestPlan,
  TestRun,
  TestRunDetails,
  StartTestRequest,
  StartTestResponse,
  StopTestRequest,
} from './types';

const api = axios.create({
  baseURL: '/api',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
  },
});

const unwrap = async <T>(promise: Promise<AxiosResponse<ApiResponse<T>>>): Promise<T> => {
  try {
    const response = await promise;
    const payload = response.data;

    if (payload.status === 'error') {
      const error = payload.error ?? { message: 'Unknown API error' };
      throw new Error(error.message || 'Unknown API error');
    }

    return payload.data;
  } catch (err) {
    if (axios.isAxiosError(err)) {
      const axiosError = err as AxiosError<ApiResponse<T>>;
      const message = axiosError.response?.data && 'error' in axiosError.response.data
        ? axiosError.response.data.error?.message
        : err.message;
      throw new Error(message || 'Request failed');
    }

    throw err instanceof Error ? err : new Error('Unknown request failure');
  }
};

export const dashboardApi = {
  getMetrics: (includeAntiDetect = true): Promise<DashboardMetrics> =>
    unwrap(api.get<ApiResponse<DashboardMetrics>>('/dashboard', { params: { includeAntiDetect } })),
};

export const testRunsApi = {
  list: (limit = 50): Promise<TestRun[]> =>
    unwrap(api.get<ApiResponse<TestRun[]>>('/test-runs', { params: { limit } })),
  get: (runId: string): Promise<TestRunDetails> => unwrap(api.get<ApiResponse<TestRunDetails>>(`/test-runs/${runId}`)),
};

export const testPlansApi = {
  list: (limit = 50): Promise<TestPlan[]> =>
    unwrap(api.get<ApiResponse<TestPlan[]>>('/test-plans', { params: { limit } })),
};

export const reportsApi = {
  list: (): Promise<ReportSummary[]> => unwrap(api.get<ApiResponse<ReportSummary[]>>('/reports')),
};

export const legacyControlApi = {
  start: async (payload: StartTestRequest): Promise<StartTestResponse> => {
    const response = await api.post<ApiResponse<StartTestResponse>>('/start_endpoint.php', payload);
    const body = response.data;
    if (body.status === 'error') {
      throw new Error(body.error?.message || 'Failed to start test');
    }
    return body.data ?? { status: 'ok' };
  },
  stop: async (payload: StopTestRequest): Promise<void> => {
    const response = await api.post<ApiResponse<unknown>>('/stop_endpoint.php', payload);
    const body = response.data;
    if (body.status === 'error') {
      throw new Error(body.error?.message || 'Failed to stop test');
    }
  },
  downloadReport: (filename: string) =>
    api.get(`/reports_endpoint.php?action=download&file=${encodeURIComponent(filename)}`, { responseType: 'blob' }),
};
