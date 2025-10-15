import axios from 'axios';
import type {
  ApiResponse,
  HealthStatus,
  LiveMetrics,
  Run,
  RunDetails,
  Report,
  ClientProfile,
  TLSProfile,
  StartTestRequest,
  StartTestResponse,
  StopTestRequest
} from './types';

const envBaseURL = import.meta.env.VITE_API_BASE_URL?.trim();
const normalizeBaseURL = (url: string): string => {
  const trimmed = url.replace(/\/+$/, '');
  if (/^https?:\/\//i.test(trimmed) || trimmed.startsWith('//')) {
    return trimmed;
  }
  return trimmed.startsWith('/') ? trimmed : `/${trimmed}`;
};

const normalizedBaseURL = envBaseURL && envBaseURL.length > 0
  ? normalizeBaseURL(envBaseURL)
  : '/api';

const api = axios.create({
  baseURL: normalizedBaseURL,
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
  },
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    console.error('API Error:', error);
    return Promise.reject(error);
  }
);

export const healthApi = {
  getStatus: async (): Promise<HealthStatus> => {
    const response = await api.get<ApiResponse<HealthStatus>>('/health_endpoint.php');
    if (response.data.status === 'error') {
      throw new Error(response.data.message);
    }
    return response.data.data!;
  },
};

export const metricsApi = {
  getLiveMetrics: async (includeAntiDetect = true): Promise<LiveMetrics> => {
    const response = await api.get(
      `/metrics_endpoint.php?include_anti_detect=${includeAntiDetect}`
    );
    
    const data = response.data;
    if (data.success === false) {
      throw new Error(data.message || 'Failed to fetch metrics');
    }
    
    const metrics = data.metrics || {};
    const statusCodes = data.status_codes || {};
    
    const mappedMetrics: LiveMetrics = {
      current_rps: metrics.requests_per_second || 0,
      rps: metrics.requests_per_second || 0,
      requests_per_second: metrics.requests_per_second || 0,
      total_requests: metrics.total_requests || 0,
      success_rate: metrics.success_rate || 0,
      avg_response_time: metrics.avg_latency_ms || 0,
      avg_latency: metrics.avg_latency_ms || 0,
      average_response_time: metrics.avg_latency_ms || 0,
      active_connections: metrics.active_threads || 0,
      threads: metrics.active_threads || 0,
      active_threads: metrics.active_threads || 0,
      errors: metrics.error_rate_percent || 0,
      error_count: Math.round((metrics.total_requests || 0) * (metrics.error_rate_percent || 0) / 100),
      status_codes: {
        '2xx': statusCodes['200'] || 0,
        '4xx': (statusCodes['403'] || 0) + (statusCodes['429'] || 0),
        '5xx': (statusCodes['503'] || 0) + (statusCodes['524'] || 0),
        '403': statusCodes['403'] || 0,
        '429': statusCodes['429'] || 0,
        '524': statusCodes['524'] || 0,
      },
      success_count: Math.round((metrics.total_requests || 0) * (metrics.success_rate || 0) / 100),
      client_error_count: (statusCodes['403'] || 0) + (statusCodes['429'] || 0),
      server_error_count: (statusCodes['503'] || 0) + (statusCodes['524'] || 0),
      proxy_stats: {
        total_proxies: metrics.active_proxies || 0,
        active_proxies: metrics.active_proxies || 0,
        rotation_count: data.anti_detect?.proxy_rotation_rate || 0,
        success_rate: metrics.success_rate || 0,
      },
    };
    
    return mappedMetrics;
  },
};

export const runsApi = {
  getRuns: async (limit = 50): Promise<Run[]> => {
    const response = await api.get<ApiResponse<{ runs: Run[] }>>(
      `/runs_endpoint.php?limit=${limit}`
    );
    if (response.data.status === 'error') {
      throw new Error(response.data.message);
    }
    return response.data.data!.runs;
  },

  getRunDetails: async (runId: string): Promise<RunDetails> => {
    const response = await api.get<ApiResponse<RunDetails>>(
      `/runs_endpoint.php?run_id=${runId}`
    );
    if (response.data.status === 'error') {
      throw new Error(response.data.message);
    }
    return response.data.data!;
  },

  startTest: async (request: StartTestRequest): Promise<StartTestResponse> => {
    const response = await api.post<ApiResponse<StartTestResponse>>(
      '/start_endpoint.php',
      request
    );
    if (response.data.status === 'error') {
      throw new Error(response.data.message);
    }
    return response.data.data!;
  },

  stopTest: async (request: StopTestRequest): Promise<void> => {
    const response = await api.post<ApiResponse>('/stop_endpoint.php', request);
    if (response.data.status === 'error') {
      throw new Error(response.data.message);
    }
  },
};

export const reportsApi = {
  getReports: async (): Promise<Report[]> => {
    const response = await api.get<ApiResponse<{ reports: Report[] }>>(
      '/reports_endpoint.php?action=list'
    );
    if (response.data.status === 'error') {
      throw new Error(response.data.message);
    }
    return response.data.data!.reports;
  },

  downloadReport: async (filename: string): Promise<Blob> => {
    const response = await api.get(`/reports_endpoint.php?action=download&file=${filename}`, {
      responseType: 'blob',
    });
    return response.data;
  },

  viewReport: async (filename: string): Promise<any> => {
    const response = await api.get<ApiResponse<any>>(
      `/reports_endpoint.php?action=view&file=${filename}`
    );
    if (response.data.status === 'error') {
      throw new Error(response.data.message);
    }
    return response.data.data;
  },

  deleteReport: async (filename: string): Promise<void> => {
    const response = await api.post<ApiResponse>('/reports_endpoint.php', {
      action: 'delete',
      filename: filename
    });
    if (response.data.status === 'error') {
      throw new Error(response.data.message);
    }
  },

  cleanupReports: async (olderThanDays: number = 30): Promise<{ deleted_count: number }> => {
    const response = await api.post<ApiResponse<{ deleted_count: number }>>('/reports_endpoint.php', {
      action: 'cleanup',
      older_than_days: olderThanDays
    });
    if (response.data.status === 'error') {
      throw new Error(response.data.message);
    }
    return response.data.data!;
  },
};

export const profilesApi = {
  getClientProfiles: async (): Promise<{ [key: string]: ClientProfile }> => {
    const response = await api.get<ApiResponse<{ profiles: { [key: string]: ClientProfile } }>>(
      '/client_profile.php'
    );
    if (response.data.status === 'error') {
      throw new Error(response.data.message);
    }
    return response.data.data!.profiles;
  },

  getClientProfile: async (profileId: string): Promise<ClientProfile> => {
    const response = await api.get<ApiResponse<{ profile: ClientProfile }>>(
      `/client_profile.php?profile_id=${profileId}`
    );
    if (response.data.status === 'error') {
      throw new Error(response.data.message);
    }
    return response.data.data!.profile;
  },

  getTLSProfiles: async (): Promise<{ [key: string]: TLSProfile }> => {
    const response = await api.get<ApiResponse<{ profiles: { [key: string]: TLSProfile } }>>(
      '/tls_profile.php'
    );
    if (response.data.status === 'error') {
      throw new Error(response.data.message);
    }
    return response.data.data!.profiles;
  },

  getTLSProfile: async (profileId: string): Promise<TLSProfile> => {
    const response = await api.get<ApiResponse<{ profile: TLSProfile }>>(
      `/tls_profile.php?profile_id=${profileId}`
    );
    if (response.data.status === 'error') {
      throw new Error(response.data.message);
    }
    return response.data.data!.profile;
  },
};

export const wafApi = {
  detectWAF: async (targetUrl: string): Promise<any> => {
    const response = await api.post<ApiResponse>('/waf_detector.php', {
      target_url: targetUrl,
    });
    if (response.data.status === 'error') {
      throw new Error(response.data.message);
    }
    return response.data.data;
  },

  getWAFStats: async (): Promise<any> => {
    const response = await api.get<ApiResponse>('/waf_stats_endpoint.php');
    if (response.data.status === 'error') {
      throw new Error(response.data.message);
    }
    return response.data.data;
  },
};

export default api;
