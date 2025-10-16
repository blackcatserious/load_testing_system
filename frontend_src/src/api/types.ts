export interface ApiErrorInfo {
  message: string;
  code?: string;
  details?: unknown;
}

export interface ApiSuccessResponse<T> {
  status: 'ok';
  data: T;
}

export interface ApiErrorResponse {
  status: 'error';
  error: ApiErrorInfo;
}

export type ApiResponse<T> = ApiSuccessResponse<T> | ApiErrorResponse;

export interface ProxyStats {
  total_proxies: number;
  active_proxies: number;
  dead_proxies?: number;
  rotation_enabled?: boolean;
  current_proxy?: string;
  proxy_ping?: number;
  success_rate?: number;
  rotation_count?: number;
}

export interface FingerprintStats {
  current_ja3?: string;
  ja3_profile_name?: string;
  tls_version?: string;
  cipher_suites?: string;
  current_user_agent?: string;
  stealth_level?: string;
  ja3_rotation_enabled?: boolean;
  tls_rotation_enabled?: boolean;
  ua_rotation_enabled?: boolean;
  detection_risk?: string;
  last_rotation?: string;
}

export interface StealthStats {
  proxy_rotations: number;
  active_proxies: number;
  ja3_rotations: number;
  ja3_pool_size: number;
  ua_rotations: number;
  ua_variants: number;
  tls_rotations: number;
  tls_configs: number;
  cookie_rotations: number;
  active_sessions: number;
}

export interface EscalationStatus {
  status: 'stable' | 'monitoring' | 'escalating';
  thread_count: number;
  last_escalation: string;
  escalation_count: number;
  reason?: string;
  escalation_factor?: number;
}

export interface TargetMetricsSummary {
  success_rate: number;
  rps: number;
  avg_latency: number;
  status_codes?: Record<string, number>;
  success_detection?: {
    target_disabled: boolean;
    protection_rate: number;
    escalation_decision: string;
  };
}

export interface DashboardMetrics {
  status: string;
  uptime_sec: number;
  threads: number;
  rps: number;
  total_requests: number;
  success_rate: number;
  avg_response_time: number;
  active_connections: number;
  errors: number;
  status_codes: Record<string, number>;
  proxy_stats: ProxyStats;
  fingerprint_stats?: FingerprintStats;
  stealth_stats?: StealthStats;
  escalation?: EscalationStatus;
  resistance?: {
    level: string;
    score: number;
    trend?: string;
  };
  target_metrics?: Record<string, TargetMetricsSummary>;
  timestamp: string;
}

export interface TestRun {
  id: string;
  group_id?: string;
  target_url?: string;
  status: string;
  started_at?: string;
  finished_at?: string | null;
  target_status?: string;
  success_detection_triggered?: boolean;
  permanent_failure_achieved?: boolean;
}

export interface MetricsSnapshot {
  timestamp: string;
  rps: number;
  total_requests: number;
  success_rate: number;
  avg_response_time: number;
  active_connections: number;
  proxy_success_rate?: number;
}

export interface TestRunDetails extends TestRun {
  metrics: MetricsSnapshot[];
  waf_detections?: any[];
  method_performance?: any[];
  run_config?: Record<string, unknown>;
}

export interface TestPlan {
  id: string;
  profile_id: string;
  status: string;
  threads: number;
  duration: number;
  engine: string;
  behavior_profile_id?: string;
  started_at?: string;
  finished_at?: string | null;
  stealth_profile?: string;
  proxy_profile?: string;
  attack_method?: string;
  targets: string[];
}

export interface ReportRunInfo {
  run_id?: string;
  target_url?: string;
  method?: string;
  started_at?: string;
  finished_at?: string;
}

export interface ReportSummary {
  filename: string;
  file_path: string;
  size: number;
  created_at: string;
  type: string;
  run_id?: string;
  timestamp?: string;
  run_info?: ReportRunInfo;
  summary?: {
    total_requests?: number;
    success_rate?: number;
    avg_response_time?: number;
    duration?: number;
  };
}

export interface StartTestRequest {
  group_id?: string;
  profile_id: string;
  threads: number;
  duration: number;
  engine: string;
  behavior_profile_id?: string;
  targets?: string[];
  target_url?: string;
  stealth_profile?: string;
  proxy_profile?: string;
  attack_method?: string;
  user_agent_rotation?: boolean;
  ja3_rotation?: boolean;
  tls_rotation?: boolean;
  proxy_rotation?: boolean;
  spoof_headers?: boolean;
}

export interface StartTestResponse {
  status: string;
  group_id?: string;
  run_ids?: string[];
}

export interface StopTestRequest {
  group_id: string;
}
