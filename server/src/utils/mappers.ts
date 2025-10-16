import type {
  DashboardMetrics,
  FingerprintStats,
  ProxyStats,
  ReportSummary,
  StealthStats,
  TestPlan,
  TestRun,
  TestRunDetails,
} from '../types/dto.js';
import type {
  RawDashboardMetrics,
  RawGroup,
  RawReport,
  RawRun,
} from '../types/orchestrator.js';

const toBoolean = (value: unknown): boolean => {
  if (typeof value === 'boolean') return value;
  if (typeof value === 'number') return value !== 0;
  if (typeof value === 'string') {
    return ['1', 'true', 'yes', 'on'].includes(value.toLowerCase());
  }
  return false;
};

const coerceNumber = (value: unknown, fallback?: number): number => {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value;
  }
  if (typeof value === 'string') {
    const parsed = Number(value);
    if (Number.isFinite(parsed)) {
      return parsed;
    }
  }
  return fallback ?? 0;
};

const ensureTimestamp = (value: unknown): string | undefined => {
  if (typeof value === 'string' && value.trim().length > 0) {
    return value;
  }
  if (typeof value === 'number' && Number.isFinite(value)) {
    return new Date(value * 1000).toISOString();
  }
  return undefined;
};

export const mapRun = (raw: RawRun): TestRun => ({
  id: raw.run_id,
  group_id: raw.group_id ?? undefined,
  target_url: raw.target_url ?? raw.target ?? undefined,
  status: raw.status,
  started_at: raw.started_at ?? undefined,
  finished_at: raw.finished_at ?? undefined,
  target_status: raw.target_status ?? undefined,
  success_detection_triggered: toBoolean(raw.success_detection_triggered),
  permanent_failure_achieved: toBoolean(raw.permanent_failure_achieved),
});

export const mapRunDetails = (raw: RawRun): TestRunDetails => ({
  ...mapRun(raw),
  metrics: [],
  waf_detections: [],
  method_performance: [],
  run_config: {},
});

const mapProxyStats = (raw: Record<string, unknown> | undefined): ProxyStats => ({
  total_proxies: coerceNumber(raw?.total ?? raw?.total_proxies, 0),
  active_proxies: coerceNumber(raw?.active ?? raw?.active_proxies, 0),
  dead_proxies: coerceNumber(raw?.dead ?? raw?.dead_proxies, 0),
  rotation_enabled: toBoolean(raw?.rotation_enabled),
  current_proxy: typeof raw?.current_proxy === 'string' ? raw.current_proxy : undefined,
  proxy_ping: coerceNumber(raw?.proxy_ping, undefined),
  success_rate: coerceNumber(raw?.success_rate, undefined),
  rotation_count: coerceNumber(raw?.rotation_count, undefined),
});

const mapFingerprintStats = (raw: Record<string, unknown> | undefined): FingerprintStats | undefined => {
  if (!raw) return undefined;
  return {
    current_ja3: typeof raw.current_ja3 === 'string' ? raw.current_ja3 : undefined,
    ja3_profile_name: typeof raw.ja3_profile_name === 'string' ? raw.ja3_profile_name : undefined,
    tls_version: typeof raw.tls_version === 'string' ? raw.tls_version : undefined,
    cipher_suites: typeof raw.cipher_suites === 'string' ? raw.cipher_suites : undefined,
    current_user_agent: typeof raw.current_user_agent === 'string' ? raw.current_user_agent : undefined,
    stealth_level: typeof raw.stealth_level === 'string' ? raw.stealth_level : undefined,
    ja3_rotation_enabled: toBoolean(raw.ja3_rotation_enabled),
    tls_rotation_enabled: toBoolean(raw.tls_rotation_enabled),
    ua_rotation_enabled: toBoolean(raw.ua_rotation_enabled),
    detection_risk: typeof raw.detection_risk === 'string' ? raw.detection_risk : undefined,
    last_rotation: ensureTimestamp(raw.last_rotation),
  };
};

export const mapDashboardMetrics = (raw: RawDashboardMetrics): DashboardMetrics => ({
  status: raw.status,
  uptime_sec: coerceNumber(raw.uptime_sec, 0),
  threads: coerceNumber(raw.threads, 0),
  rps: coerceNumber(raw.rps, 0),
  total_requests: coerceNumber(raw.total_requests, 0),
  success_rate: coerceNumber(raw.success_rate, 0),
  avg_response_time: coerceNumber(raw.avg_response_time, 0),
  active_connections: coerceNumber(raw.active_connections, 0),
  errors: coerceNumber(raw.errors, 0),
  status_codes: (raw.status_codes as Record<string, number>) ?? {},
  proxy_stats: mapProxyStats(raw.proxy_stats as Record<string, unknown> | undefined),
  fingerprint_stats: mapFingerprintStats(raw.fingerprint_stats as Record<string, unknown> | undefined),
  stealth_stats: mapStealthStats(raw.stealth_stats as Record<string, unknown> | undefined),
  escalation: raw.escalation
    ? {
        status: (raw.escalation as Record<string, unknown>).status as any,
        thread_count: coerceNumber((raw.escalation as Record<string, unknown>).thread_count, 0),
        last_escalation:
          ensureTimestamp((raw.escalation as Record<string, unknown>).last_escalation) ??
          new Date().toISOString(),
        escalation_count: coerceNumber((raw.escalation as Record<string, unknown>).escalation_count, 0),
        reason: typeof (raw.escalation as Record<string, unknown>).escalation_reason === 'string'
          ? ((raw.escalation as Record<string, unknown>).escalation_reason as string)
          : undefined,
        escalation_factor: coerceNumber((raw.escalation as Record<string, unknown>).escalation_factor, undefined),
      }
    : undefined,
  resistance: raw.resistance
    ? {
        level: String((raw.resistance as Record<string, unknown>).level ?? 'unknown'),
        score: coerceNumber((raw.resistance as Record<string, unknown>).score, 0),
        trend: (raw.resistance as Record<string, unknown>).trend as string | undefined,
      }
    : undefined,
  target_metrics: (raw.target_metrics as Record<string, any> | undefined) ?? undefined,
  timestamp: ensureTimestamp(raw.timestamp) ?? new Date().toISOString(),
});

const parseTargets = (targets: string | undefined): string[] => {
  if (!targets) return [];
  try {
    const parsed = JSON.parse(targets);
    if (Array.isArray(parsed)) {
      return parsed.map((value) => String(value));
    }
  } catch (err) {
    return targets.split(',').map((t) => t.trim()).filter(Boolean);
  }
  return [];
};

export const mapTestPlan = (raw: RawGroup): TestPlan => ({
  id: raw.group_id,
  profile_id: raw.profile_id,
  status: raw.status,
  threads: coerceNumber(raw.threads, 0),
  duration: coerceNumber(raw.duration, 0),
  engine: raw.engine,
  behavior_profile_id: raw.behavior_profile_id ?? undefined,
  started_at: raw.started_at ?? undefined,
  finished_at: raw.finished_at ?? undefined,
  stealth_profile: raw.stealth_profile ?? undefined,
  proxy_profile: raw.proxy_profile ?? undefined,
  attack_method: raw.attack_method ?? undefined,
  targets: parseTargets(raw.targets),
});

export const mapReport = (raw: RawReport): ReportSummary => ({
  filename: raw.filename,
  file_path: raw.file_path,
  size: coerceNumber(raw.size, 0),
  created_at: raw.created_at,
  type: raw.type,
  run_id: raw.run_id ?? raw.run_info?.run_id,
  timestamp: raw.timestamp ?? raw.created_at,
  run_info: raw.run_info
    ? {
        run_id: raw.run_info.run_id ?? raw.run_id,
        target_url: raw.run_info.target_url,
        method: raw.run_info.method,
        started_at: raw.run_info.started_at,
        finished_at: raw.run_info.finished_at,
      }
    : undefined,
  summary: raw.summary
    ? {
        total_requests: coerceNumber(raw.summary.total_requests, undefined),
        success_rate: coerceNumber(raw.summary.success_rate, undefined),
        avg_response_time: coerceNumber(raw.summary.avg_response_time, undefined),
        duration: coerceNumber(raw.summary.duration, undefined),
      }
    : undefined,
});

const mapStealthStats = (raw: Record<string, unknown> | undefined): StealthStats | undefined => {
  if (!raw) return undefined;
  return {
    proxy_rotations: coerceNumber(raw.proxy_rotations, 0),
    active_proxies: coerceNumber(raw.active_proxies, 0),
    ja3_rotations: coerceNumber(raw.ja3_rotations, 0),
    ja3_pool_size: coerceNumber(raw.ja3_pool_size, 0),
    ua_rotations: coerceNumber(raw.ua_rotations, 0),
    ua_variants: coerceNumber(raw.ua_variants, 0),
    tls_rotations: coerceNumber(raw.tls_rotations, 0),
    tls_configs: coerceNumber(raw.tls_configs, 0),
    cookie_rotations: coerceNumber(raw.cookie_rotations, 0),
    active_sessions: coerceNumber(raw.active_sessions, 0),
  };
};
