export interface ApiResponse<T = any> {
  status: 'success' | 'error';
  message: string;
  data?: T;
  timestamp: number;
}

export interface HealthStatus {
  status: string;
  timestamp: number;
  uptime: number;
  version: string;
  environment: string;
}

export interface AntiDetectStats {
  active_sessions: number;
  user_agents_rotated: number;
  tls_fingerprints_used: number;
  headers_randomized: number;
  timing_variations: number;
  proxy_rotations: number;
  detection_events: number;
  success_rate: number;
}

export interface WAFStats {
  total_detections: number;
  waf_types: {
    [key: string]: number;
  };
  bypass_success_rate: number;
  recent_detections: Array<{
    timestamp: number;
    waf_type: string;
    target_url: string;
    detection_method: string;
  }>;
}

export interface AdaptiveMethodStats {
  method_performance: {
    [key: string]: {
      success_rate: number;
      avg_response_time: number;
      total_requests: number;
      recent_success: boolean;
    };
  };
  current_method: string;
  switch_count: number;
  optimization_score: number;
}

export interface LiveMetrics {
  current_rps: number;
  rps?: number;
  requests_per_second?: number;
  total_requests: number;
  success_rate: number;
  avg_response_time: number;
  avg_latency?: number;
  average_response_time?: number;
  active_connections: number;
  threads?: number;
  active_threads?: number;
  errors?: number;
  error_count?: number;
  status_codes?: {
    '2xx'?: number;
    '4xx'?: number;
    '5xx'?: number;
    '403'?: number;
    '429'?: number;
    '524'?: number;
    'other'?: number;
  };
  detailed_codes?: {
    '200'?: number;
    '403'?: number;
    '404'?: number;
    '429'?: number;
    '502'?: number;
    '503'?: number;
    '524'?: number;
    'timeout'?: number;
    'dns'?: number;
  };
  escalation?: {
    status: 'stable' | 'monitoring' | 'escalating';
    thread_count: number;
    last_escalation: string;
    escalation_count: number;
  };
  resistance?: {
    level: 'Low' | 'Medium' | 'High';
    score: number;
    trend: 'increasing' | 'decreasing' | 'stable';
  };
  success_count?: number;
  client_error_count?: number;
  server_error_count?: number;
  proxy_stats: {
    total_proxies: number;
    active_proxies: number;
    rotation_count: number;
    success_rate: number;
  };
  anti_detect?: AntiDetectStats;
  waf_detection?: WAFStats;
  adaptive_methods?: AdaptiveMethodStats;
  stealth_stats?: {
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
  };
  target_metrics?: {
    [target: string]: {
      success_rate: number;
      rps: number;
      avg_latency: number;
      status_codes?: {
        [key: string]: number;
      };
      success_detection?: {
        target_disabled: boolean;
        protection_rate: number;
        escalation_decision: string;
      };
    };
  };
}

export interface Run {
  id: string;
  target_url: string;
  method: string;
  status: 'RUNNING' | 'COMPLETED' | 'STOPPED' | 'ERROR';
  started_at: string;
  finished_at?: string;
  total_requests?: number;
  success_rate?: number;
  avg_response_time?: number;
  metrics_count?: number;
  waf_detections_count?: number;
  has_config?: boolean;
  config_summary?: {
    anti_detect_enabled: boolean;
    proxy_enabled: boolean;
    method: string;
  };
}

export interface RunDetails extends Run {
  metrics: MetricsSnapshot[];
  waf_detections: WAFDetection[];
  method_performance: MethodPerformance[];
  run_config?: any;
}

export interface MetricsSnapshot {
  id: number;
  run_id: string;
  timestamp: string;
  rps: number;
  total_requests: number;
  success_rate: number;
  avg_response_time: number;
  active_connections: number;
  proxy_success_rate: number;
}

export interface WAFDetection {
  id: number;
  run_id: string;
  timestamp: string;
  waf_type: string;
  detection_method: string;
  target_url: string;
  response_code: number;
  response_headers: string;
}

export interface MethodPerformance {
  id: number;
  run_id: string;
  timestamp: string;
  method: string;
  success_rate: number;
  avg_response_time: number;
  total_requests: number;
}

export interface Report {
  filename: string;
  file_path: string;
  size: number;
  created_at: string;
  type: 'json' | 'csv';
  run_id: string;
  timestamp: string;
  run_info?: {
    target_url: string;
    method: string;
    started_at: string;
    finished_at: string;
  };
  summary?: {
    total_requests: number;
    success_rate: number;
    avg_response_time: number;
    duration: number;
  };
}

export interface ClientProfile {
  name: string;
  description: string;
  features: {
    [key: string]: any;
  };
  use_cases: string[];
  performance: 'low' | 'medium' | 'high';
  detection_risk: 'very_low' | 'low' | 'medium' | 'high';
}

export interface TLSProfile {
  name: string;
  description: string;
  tls_version: string;
  cipher_suites: string[] | string;
  extensions: string[] | string;
  ja3_fingerprint?: string;
  compatibility: 'low' | 'medium' | 'high' | 'variable';
  security_level: 'standard' | 'high' | 'variable';
  performance: 'low' | 'medium' | 'high';
  stealth_level?: 'low' | 'medium' | 'high' | 'very_high' | 'maximum';
  note?: string;
}

export interface StartTestRequest {
  target_url: string;
  method?: string;
  duration?: number;
  rps?: number;
  concurrent_users?: number;
  anti_detect_enabled?: boolean;
  proxy_enabled?: boolean;
  client_profile?: string;
  tls_profile?: string;
  custom_headers?: { [key: string]: string };
}

export interface StartTestResponse {
  run_id: string;
  status: string;
  message: string;
  pid?: number;
  config_file?: string;
}

export interface StopTestRequest {
  run_id: string;
}

export interface Toast {
  id: string;
  type: 'success' | 'error' | 'warning' | 'info';
  title: string;
  message: string;
  duration?: number;
}
