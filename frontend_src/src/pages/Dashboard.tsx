import React, { useMemo } from 'react';
import {
  Activity,
  AlertTriangle,
  ArrowUpRight,
  BarChart3,
  Gauge,
  Globe2,
  Loader2,
  Shield,
  Target,
  Timer,
  Wifi,
} from 'lucide-react';
import { useDashboardMetrics } from '../api/hooks';

const formatNumber = (value: number | undefined) =>
  typeof value === 'number' && Number.isFinite(value) ? value.toLocaleString() : '—';

const formatPercent = (value: number | undefined) =>
  typeof value === 'number' && Number.isFinite(value) ? `${value.toFixed(1)}%` : '—';

const formatLatency = (value: number | undefined) => {
  if (typeof value !== 'number' || !Number.isFinite(value)) {
    return '—';
  }
  if (value < 1000) {
    return `${value.toFixed(0)} ms`;
  }
  return `${(value / 1000).toFixed(2)} s`;
};

const formatDuration = (seconds: number | undefined) => {
  if (typeof seconds !== 'number' || !Number.isFinite(seconds)) {
    return '—';
  }
  const hours = Math.floor(seconds / 3600);
  const mins = Math.floor((seconds % 3600) / 60);
  const parts = [];
  if (hours > 0) parts.push(`${hours}h`);
  if (mins > 0 || parts.length === 0) parts.push(`${mins}m`);
  return parts.join(' ');
};

const emptyState = (
  <div className="flex min-h-[320px] flex-col items-center justify-center gap-4 rounded-3xl border border-white/10 bg-slate-900/70 p-10 text-white/70">
    <Shield className="h-12 w-12 text-white/40" />
    <div className="text-center">
      <h3 className="text-xl font-semibold text-white">No telemetry yet</h3>
      <p className="mt-2 text-sm text-white/60">Run an orchestration cycle to populate real-time metrics.</p>
    </div>
  </div>
);

const Dashboard: React.FC = () => {
  const { data: metrics, loading, error } = useDashboardMetrics();

  const statusCodes = useMemo(() => {
    if (!metrics) return [] as Array<{ code: string; count: number }>;
    return Object.entries(metrics.status_codes)
      .map(([code, count]) => ({ code, count }))
      .sort((a, b) => b.count - a.count)
      .slice(0, 6);
  }, [metrics]);

  const targetSummaries = useMemo(() => {
    if (!metrics?.target_metrics) return [] as Array<{ id: string; success_rate: number; rps: number; avg_latency: number }>;
    return Object.entries(metrics.target_metrics)
      .map(([id, summary]) => ({
        id,
        success_rate: summary.success_rate,
        rps: summary.rps,
        avg_latency: summary.avg_latency,
      }))
      .sort((a, b) => b.success_rate - a.success_rate)
      .slice(0, 4);
  }, [metrics]);

  if (loading) {
    return (
      <div className="flex min-h-[320px] flex-col items-center justify-center gap-4 rounded-3xl border border-white/10 bg-slate-900/70 p-10 text-white/70">
        <Loader2 className="h-10 w-10 animate-spin text-blue-400" />
        Syncing with orchestration layer…
      </div>
    );
  }

  if (error) {
    return (
      <div className="rounded-3xl border border-rose-500/30 bg-rose-500/10 p-6 text-rose-100">
        <div className="flex items-center gap-3">
          <AlertTriangle className="h-5 w-5" />
          <h3 className="text-lg font-semibold">Unable to load domain telemetry</h3>
        </div>
        <p className="mt-2 text-sm text-rose-100/80">{error}</p>
      </div>
    );
  }

  if (!metrics) {
    return emptyState;
  }

  const derivedSuccessRate = metrics.success_rate > 1 ? metrics.success_rate : metrics.success_rate * 100;

  const heroCards = [
    {
      title: 'Current RPS',
      value: formatNumber(metrics.rps),
      hint: 'Requests per second',
      icon: Gauge,
      tone: 'from-blue-500/20 to-blue-500/5 text-blue-200',
    },
    {
      title: 'Success rate',
      value: formatPercent(derivedSuccessRate),
      hint: 'Across all domains',
      icon: Shield,
      tone: 'from-emerald-500/20 to-emerald-500/5 text-emerald-200',
    },
    {
      title: 'Total requests',
      value: formatNumber(metrics.total_requests),
      hint: 'Since start of cycle',
      icon: BarChart3,
      tone: 'from-sky-500/20 to-sky-500/5 text-sky-200',
    },
    {
      title: 'Active connections',
      value: formatNumber(metrics.active_connections),
      hint: `${formatNumber(metrics.threads)} threads engaged`,
      icon: Activity,
      tone: 'from-indigo-500/20 to-indigo-500/5 text-indigo-200',
    },
  ];

  return (
    <div className="space-y-10">
      <header className="rounded-3xl border border-white/10 bg-gradient-to-br from-slate-900/80 via-slate-900/70 to-slate-900/40 p-8 shadow-xl shadow-black/40">
        <div className="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
          <div>
            <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs uppercase tracking-[0.35em] text-slate-300">
              <Wifi className="h-3.5 w-3.5 text-blue-300" />
              Orchestrator Sync
            </div>
            <h1 className="mt-4 text-3xl font-semibold text-white">Domain telemetry control center</h1>
            <p className="mt-2 max-w-2xl text-sm text-white/70">
              Live status straight from the PHP orchestration layer: proxy pools, stealth fingerprints, and mitigation posture
              for every connected domain.
            </p>
            <div className="mt-4 flex flex-wrap gap-3 text-xs uppercase tracking-widest text-white/60">
              <span className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1">
                <Timer className="h-3.5 w-3.5 text-blue-300" /> Uptime {formatDuration(metrics.uptime_sec)}
              </span>
              <span className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1">
                <Target className="h-3.5 w-3.5 text-blue-300" /> {targetSummaries.length} target metrics
              </span>
            </div>
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/5 px-5 py-4 text-right text-white/70">
            <p className="text-xs uppercase tracking-[0.35em] text-white/50">Status</p>
            <p className="mt-1 text-xl font-semibold text-white">{metrics.status || 'UNKNOWN'}</p>
            {metrics.escalation && (
              <p className="mt-2 text-xs text-white/60">
                Escalation {metrics.escalation.status.toUpperCase()} · Threads {formatNumber(metrics.escalation.thread_count)}
              </p>
            )}
          </div>
        </div>
      </header>

      <section className="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
        {heroCards.map((card) => {
          const Icon = card.icon;
          return (
            <div
              key={card.title}
              className="relative overflow-hidden rounded-2xl border border-white/10 bg-slate-900/70 p-6 shadow-lg shadow-black/30 transition hover:-translate-y-1 hover:border-white/20"
            >
              <div className={`absolute inset-0 bg-gradient-to-br ${card.tone} opacity-60`} aria-hidden />
              <div className="relative">
                <div className="flex items-center justify-between text-white/70">
                  <span className="text-xs uppercase tracking-[0.35em]">{card.title}</span>
                  <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-white">
                    <Icon className="h-5 w-5" />
                  </span>
                </div>
                <p className="mt-5 text-3xl font-semibold text-white">{card.value}</p>
                <p className="mt-2 text-xs text-white/70">{card.hint}</p>
              </div>
            </div>
          );
        })}
      </section>

      <section className="grid gap-6 xl:grid-cols-[2fr_1fr]">
        <div className="rounded-3xl border border-white/10 bg-slate-900/70 p-6 shadow-lg shadow-black/30">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p className="text-xs uppercase tracking-[0.35em] text-blue-300/80">Status codes</p>
              <h2 className="mt-2 text-xl font-semibold text-white">Response distribution</h2>
            </div>
            <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-white/70">
              <ArrowUpRight className="h-4 w-4 text-blue-300" /> {formatPercent(derivedSuccessRate)} success
            </div>
          </div>

          <div className="mt-6 space-y-4">
            {statusCodes.length === 0 && (
              <p className="text-sm text-white/60">No status code telemetry reported yet.</p>
            )}
            {statusCodes.map(({ code, count }) => (
              <div key={code} className="space-y-2">
                <div className="flex items-center justify-between text-xs uppercase tracking-widest text-white/50">
                  <span>{code}</span>
                  <span>{formatNumber(count)}</span>
                </div>
                <div className="h-2 rounded-full bg-white/5">
                  {(() => {
                    const ratio =
                      metrics.total_requests > 0 ? Math.min(100, (count / metrics.total_requests) * 100) : 0;
                    return (
                  <div
                    className="h-2 rounded-full bg-gradient-to-r from-blue-500 via-indigo-500 to-purple-500"
                        style={{ width: `${ratio}%` }}
                      />
                    );
                  })()}
                </div>
              </div>
            ))}
          </div>
        </div>

        <div className="grid gap-6">
          <div className="rounded-3xl border border-white/10 bg-slate-900/70 p-6 shadow-lg shadow-black/30">
            <div className="flex items-center gap-3">
              <Globe2 className="h-5 w-5 text-blue-300" />
              <div>
                <h3 className="text-sm font-semibold text-white">Proxy &amp; stealth posture</h3>
                <p className="text-xs text-white/60">Live proxy pool rotation and stealth fingerprinting</p>
              </div>
            </div>
            <div className="mt-5 grid gap-3 text-sm text-white/70">
              <div className="flex items-center justify-between">
                <span>Active proxies</span>
                <span className="text-white">{formatNumber(metrics.proxy_stats.active_proxies)}</span>
              </div>
              <div className="flex items-center justify-between">
                <span>Rotation count</span>
                <span className="text-white">{formatNumber(metrics.proxy_stats.rotation_count)}</span>
              </div>
              {metrics.stealth_stats && (
                <div className="flex items-center justify-between">
                  <span>Stealth sessions</span>
                  <span className="text-white">{formatNumber(metrics.stealth_stats.active_sessions)}</span>
                </div>
              )}
              {metrics.fingerprint_stats?.current_user_agent && (
                <div className="flex items-center justify-between">
                  <span>User agent</span>
                  <span className="text-white/80">{metrics.fingerprint_stats.current_user_agent}</span>
                </div>
              )}
            </div>
          </div>

          {metrics.resistance && (
            <div className="rounded-3xl border border-white/10 bg-gradient-to-br from-slate-900/80 via-slate-900/60 to-slate-900/30 p-6 shadow-lg shadow-black/30">
              <div className="flex items-center gap-3">
                <Shield className="h-5 w-5 text-emerald-300" />
                <div>
                  <h3 className="text-sm font-semibold text-white">Mitigation resistance</h3>
                  <p className="text-xs text-white/60">Adaptive score against current anti-DDoS posture</p>
                </div>
              </div>
              <div className="mt-5 space-y-3 text-sm text-white/70">
                <div className="flex items-center justify-between">
                  <span>Level</span>
                  <span className="text-white uppercase">{metrics.resistance.level}</span>
                </div>
                <div className="flex items-center justify-between">
                  <span>Score</span>
                  <span className="text-white">{formatNumber(metrics.resistance.score)}</span>
                </div>
                {metrics.resistance.trend && (
                  <div className="flex items-center justify-between">
                    <span>Trend</span>
                    <span className="text-white/80">{metrics.resistance.trend}</span>
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      </section>

      <section className="rounded-3xl border border-white/10 bg-slate-900/70 p-6 shadow-lg shadow-black/30">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-xs uppercase tracking-[0.35em] text-blue-300/80">Priority targets</p>
            <h2 className="mt-2 text-xl font-semibold text-white">Top domain performance</h2>
          </div>
          <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-white/60">
            <ArrowUpRight className="h-4 w-4 text-blue-300" /> Sorted by success rate
          </div>
        </div>
        <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          {targetSummaries.length === 0 && (
            <p className="text-sm text-white/60">No target metrics reported by the orchestrator.</p>
          )}
          {targetSummaries.map((target) => (
            <div key={target.id} className="flex flex-col gap-3 rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-white/70">
              <div className="flex items-center justify-between">
                <span className="text-xs uppercase tracking-[0.35em] text-white/50">{target.id}</span>
                <span className="inline-flex items-center gap-2 rounded-full border border-emerald-500/40 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-100">
                  <ArrowUpRight className="h-3.5 w-3.5" /> {formatPercent(target.success_rate > 1 ? target.success_rate : target.success_rate * 100)}
                </span>
              </div>
              <div className="flex items-center justify-between text-white">
                <span>RPS</span>
                <span>{formatNumber(target.rps)}</span>
              </div>
              <div className="flex items-center justify-between text-white">
                <span>Latency</span>
                <span>{formatLatency(target.avg_latency)}</span>
              </div>
            </div>
          ))}
        </div>
      </section>
    </div>
  );
};

export default Dashboard;
