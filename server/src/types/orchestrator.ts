export interface PhpApiResponse<T = unknown> {
  status?: 'success' | 'error';
  success?: boolean;
  message?: string;
  error?: string;
  data?: T;
  [key: string]: unknown;
}

export interface RawRun {
  run_id: string;
  group_id?: string | null;
  target?: string | null;
  target_url?: string | null;
  status: string;
  started_at?: string | null;
  finished_at?: string | null;
  target_status?: string | null;
  stealth_session_id?: string | null;
  success_detection_triggered?: number | boolean | null;
  permanent_failure_achieved?: number | boolean | null;
}

export interface RawMetricsSnapshot {
  id: number;
  run_id?: string;
  timestamp: string;
  rps: number;
  total_requests: number;
  success_rate: number;
  avg_response_time: number;
  active_connections: number;
  proxy_success_rate?: number;
}

export interface RawReport {
  filename: string;
  file_path: string;
  size: number;
  created_at: string;
  type: string;
  run_id?: string;
  timestamp?: string;
}

export interface RawGroup {
  group_id: string;
  targets?: string;
  profile_id: string;
  threads: number;
  duration: number;
  engine: string;
  behavior_profile_id?: string;
  started_at?: string | null;
  finished_at?: string | null;
  status: string;
  stealth_profile?: string | null;
  attack_method?: string | null;
  proxy_profile?: string | null;
}

export interface RawDashboardMetrics extends Record<string, unknown> {
  success: boolean;
  status: string;
  uptime_sec: number;
  threads: number;
  rps: number;
  total_requests: number;
  success_rate: number;
  avg_response_time: number;
  active_connections: number;
  errors: number;
  status_codes?: Record<string, number>;
  proxy_stats?: Record<string, unknown>;
  fingerprint_stats?: Record<string, unknown>;
  stealth_stats?: Record<string, unknown>;
  escalation?: Record<string, unknown>;
  resistance?: Record<string, unknown>;
  target_metrics?: Record<string, unknown>;
  timestamp?: string;
}
