export interface ProxyPoolStatsDTO {
  total_proxies: number;
  active_proxies: number;
  dead_proxies?: number;
  rotation_count?: number;
  success_rate?: number;
}

export interface UserAgentMetaDTO {
  current: string;
  rotation_enabled?: boolean;
}

export interface TLSMetaDTO {
  ja3_fingerprint?: string;
  name?: string;
  tls_version?: string;
  cipher_suites?: string[];
}

export interface RuntimeMetadataDTO {
  proxy_pool: ProxyPoolStatsDTO;
  user_agent?: UserAgentMetaDTO;
  tls?: TLSMetaDTO;
  current_proxy?: {
    ip: string;
    port: string | number;
    type?: string;
    country?: string;
  } | null;
}

export interface DashboardMetricsDTO {
  timestamp: string;
  metrics: {
    requests_per_second: number;
    active_threads: number;
    avg_latency_ms: number;
    total_requests: number;
    success_rate: number;
    error_rate_percent?: number;
  };
  status_codes: Record<string, number>;
  attack_status?: Record<string, any>;
  runtime: RuntimeMetadataDTO;
}

export interface TestRunSummaryDTO {
  id: string;
  group_id?: string;
  target_url?: string;
  status: string;
  started_at?: string;
  finished_at?: string | null;
  target_status?: string;
  proxy_pool?: ProxyPoolStatsDTO;
  user_agent?: UserAgentMetaDTO;
  tls?: TLSMetaDTO;
}

export interface TestRunDetailDTO extends TestRunSummaryDTO {
  metrics?: any;
  waf_detections?: any;
  method_performance?: any;
  run_config?: any;
}

export interface TestPlanDTO {
  id: string;
  status: string;
  started_at?: string;
  finished_at?: string | null;
  targets?: string[];
  threads?: number;
  duration?: number;
  engine?: string;
  behavior_profile_id?: string;
  runtime?: RuntimeMetadataDTO;
}

export interface ReportDTO {
  filename: string;
  file_path?: string;
  size?: number;
  created_at?: string;
  type?: string;
  run_id?: string;
  summary?: any;
}

export interface ApiErrorDTO {
  status: 'error';
  message: string;
  details?: any;
}

export interface ApiSuccessDTO<T> {
  status: 'success';
  data: T;
}

export type ApiResponseDTO<T> = ApiSuccessDTO<T> | ApiErrorDTO;
