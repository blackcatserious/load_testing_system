import {
  TestRunDetailDTO,
  TestRunSummaryDTO,
  RuntimeMetadataDTO,
  ProxyPoolStatsDTO,
  UserAgentMetaDTO,
  TLSMetaDTO,
} from '../types/dto.js';

interface RawRunRecord {
  run_id: string;
  group_id?: string;
  target?: string;
  target_url?: string;
  status: string;
  started_at?: string;
  finished_at?: string | null;
  target_status?: string;
  profile_id?: string;
  threads?: number;
  duration?: number;
  engine?: string;
  behavior_profile_id?: string;
  [key: string]: any;
}

interface RawRuntimePayload {
  proxy_stats?: Record<string, any>;
  current_proxy?: Record<string, any> | null;
  user_agent?: string | Record<string, any>;
  user_agent_rotation?: boolean;
  tls_profile?: Record<string, any>;
  ja3?: Record<string, any>;
  tls?: Record<string, any>;
}

export const mapProxyStats = (raw?: Record<string, any>): ProxyPoolStatsDTO | undefined => {
  if (!raw) return undefined;
  return {
    total_proxies: Number(raw.total_proxies ?? raw.total ?? 0),
    active_proxies: Number(raw.active_proxies ?? raw.active ?? 0),
    dead_proxies: raw.dead_proxies != null ? Number(raw.dead_proxies) : undefined,
    rotation_count: raw.rotation_count != null ? Number(raw.rotation_count) : undefined,
    success_rate: raw.success_rate != null ? Number(raw.success_rate) : undefined,
  };
};

const mapUserAgent = (raw: RawRuntimePayload): UserAgentMetaDTO | undefined => {
  if (typeof raw.user_agent === 'string') {
    return { current: raw.user_agent, rotation_enabled: raw.user_agent_rotation ?? true };
  }

  if (raw.user_agent && typeof raw.user_agent === 'object' && 'current' in raw.user_agent) {
    return {
      current: String((raw.user_agent as Record<string, any>).current ?? ''),
      rotation_enabled:
        (raw.user_agent as Record<string, any>).rotation_enabled ?? raw.user_agent_rotation ?? true,
    };
  }

  return undefined;
};

const mapTLS = (raw: RawRuntimePayload): TLSMetaDTO | undefined => {
  const source = raw.tls_profile ?? raw.ja3 ?? raw.tls ?? undefined;
  if (!source || typeof source !== 'object') {
    return undefined;
  }
  const cipherSuites = (source as Record<string, any>).cipher_suites;
  return {
    ja3_fingerprint:
      (source as Record<string, any>).ja3_fingerprint ??
      (source as Record<string, any>).ja3 ??
      (source as Record<string, any>).fingerprint,
    name: (source as Record<string, any>).name,
    tls_version: (source as Record<string, any>).tls_version,
    cipher_suites: Array.isArray(cipherSuites)
      ? cipherSuites
      : typeof cipherSuites === 'string'
        ? cipherSuites
            .split(',')
            .map((entry: string) => entry.trim())
            .filter(Boolean)
        : undefined,
  };
};

export const mapRuntimeMetadata = (raw?: RawRuntimePayload): RuntimeMetadataDTO | undefined => {
  if (!raw) return undefined;
  const proxyPool = mapProxyStats(raw.proxy_stats);
  if (!proxyPool) {
    return undefined;
  }

  return {
    proxy_pool: proxyPool,
    current_proxy: raw.current_proxy
      ? {
          ip: String(raw.current_proxy.ip ?? raw.current_proxy.address ?? ''),
          port: raw.current_proxy.port ?? raw.current_proxy.port ?? '',
          type: raw.current_proxy.type,
          country: raw.current_proxy.country,
        }
      : null,
    user_agent: mapUserAgent(raw),
    tls: mapTLS(raw),
  };
};

export const mapRunSummary = (run: RawRunRecord, runtime?: RuntimeMetadataDTO): TestRunSummaryDTO => ({
  id: run.run_id,
  group_id: run.group_id,
  target_url: run.target_url ?? run.target,
  status: run.status ?? 'UNKNOWN',
  started_at: run.started_at,
  finished_at: run.finished_at,
  target_status: run.target_status,
  proxy_pool: runtime?.proxy_pool,
  user_agent: runtime?.user_agent,
  tls: runtime?.tls,
});

export const mapRunDetail = (
  run: RawRunRecord,
  runtime?: RuntimeMetadataDTO,
  extras?: Partial<TestRunDetailDTO>
): TestRunDetailDTO => ({
  ...mapRunSummary(run, runtime),
  metrics: extras?.metrics,
  waf_detections: extras?.waf_detections,
  method_performance: extras?.method_performance,
  run_config: extras?.run_config,
});
