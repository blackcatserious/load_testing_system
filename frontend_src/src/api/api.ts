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

const api = axios.create({
  baseURL: '/api',
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
    
    const mappedMetrics: LiveMetrics = {
      current_rps: data.rps || 0,
      rps: data.rps || 0,
      requests_per_second: data.rps || 0,
      total_requests: data.total_requests || 0,
      success_rate: data.success_rate || 0,
      avg_response_time: data.latency_ms?.p50 || 0,
      avg_latency: data.latency_ms?.p50 || 0,
      average_response_time: data.latency_ms?.p50 || 0,
      active_connections: data.active_connections || 0,
      threads: data.threads || 0,
      active_threads: data.threads || 0,
      errors: data.errors || 0,
      error_count: data.errors || 0,
      status_codes: {
        '2xx': data.codes?.['200'] || 0,
        '4xx': (data.codes?.['404'] || 0) + (data.codes?.['403'] || 0) + (data.codes?.['429'] || 0),
        '5xx': data.codes?.['500'] || 0,
        '403': data.codes?.['403'] || 0,
        '429': data.codes?.['429'] || 0,
        '524': data.codes?.['524'] || 0,
      },
      success_count: data.codes?.['200'] || 0,
      client_error_count: (data.codes?.['404'] || 0) + (data.codes?.['403'] || 0) + (data.codes?.['429'] || 0),
      server_error_count: data.codes?.['500'] || 0,
      proxy_stats: {
        total_proxies: data.proxy_stats?.total_proxies || 0,
        active_proxies: data.proxy_stats?.active_proxies || 0,
        rotation_count: data.proxy_stats?.rotation_count || 0,
        success_rate: data.proxy_stats?.success_rate || 0,
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
      '/reports_list_endpoint.php'
    );
    if (response.data.status === 'error') {
      throw new Error(response.data.message);
    }
    return response.data.data!.reports;
  },

  downloadReport: async (filename: string): Promise<Blob> => {
    const response = await api.get(`/reports_get_endpoint.php?file=${filename}`, {
      responseType: 'blob',
    });
    return response.data;
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
