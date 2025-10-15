import React, { useEffect, useMemo, useState } from 'react';
import {
  Area,
  AreaChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import dayjs from '../setupDayjs';
import { AlertCircle, Calendar, CloudLightning, RefreshCw } from 'lucide-react';
import MetricCard from '../components/MetricCard';
import StatusPill from '../components/StatusPill';
import LoadingState from '../components/LoadingState';
import ErrorState from '../components/ErrorState';
import api from '../lib/api';
import type { DashboardOverview } from '../types';

const Dashboard: React.FC = () => {
  const [overview, setOverview] = useState<DashboardOverview | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    api
      .get<DashboardOverview>('/dashboard/overview', { signal: controller.signal })
      .then((response) => {
        setOverview(response.data);
        setError(null);
      })
      .catch((err) => {
        if (!controller.signal.aborted) {
          setError(err.message ?? 'Failed to load dashboard');
        }
      })
      .finally(() => {
        if (!controller.signal.aborted) {
          setLoading(false);
        }
      });

    const interval = setInterval(() => {
      api
        .get<DashboardOverview>('/dashboard/overview')
        .then((response) => {
          setOverview(response.data);
          setError(null);
        })
        .catch((err) => {
          setError(err.message ?? 'Failed to refresh dashboard');
        });
    }, 30_000);

    return () => {
      controller.abort();
      clearInterval(interval);
    };
  }, []);

  const trendData = useMemo(() => overview?.trend ?? [], [overview?.trend]);

  if (loading) {
    return <LoadingState label="Loading control center" />;
  }

  if (error || !overview) {
    return <ErrorState description={error ?? undefined} />;
  }

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-8 px-4 pb-16 sm:px-6 lg:px-8">
      <section className="relative overflow-hidden rounded-4xl border border-indigo-500/30 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 px-8 py-10 shadow-[0_40px_120px_-60px_rgba(99,102,241,0.5)]">
        <div className="absolute -right-24 -top-24 h-64 w-64 rounded-full bg-indigo-500/30 blur-3xl" aria-hidden />
        <div className="absolute bottom-0 left-20 h-32 w-32 rounded-full bg-cyan-400/20 blur-3xl" aria-hidden />
        <div className="relative flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
          <div className="max-w-2xl">
            <p className="text-xs font-semibold uppercase tracking-[0.5em] text-indigo-200">Mission Control</p>
            <h1 className="mt-4 text-4xl font-semibold text-white md:text-5xl">Real-time command for your load operations</h1>
            <p className="mt-4 text-base text-slate-200/80">
              Coordinate orchestrated spikes, monitor saturation, and ship production-grade load intelligence from a single
              ultramodern surface.
            </p>
          </div>
          <div className="flex flex-col items-start gap-4 rounded-3xl border border-indigo-500/40 bg-indigo-500/10 p-6 text-indigo-100">
            <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.35em] text-indigo-200/90">
              <RefreshCw className="h-4 w-4 animate-spin" />
              Synced {dayjs(overview.updatedAt).fromNow()}
            </div>
            <div className="text-4xl font-semibold">{overview.readinessScore}%</div>
            <p className="max-w-xs text-sm text-indigo-100/80">{overview.readinessSummary}</p>
          </div>
        </div>
      </section>

      <section className="grid gap-6 md:grid-cols-2 xl:grid-cols-5">
        <MetricCard label="Throughput" value={overview.metrics.throughput.toLocaleString()} unit="rps" caption="Peak over 5 min" delta="+3.2%" />
        <MetricCard label="Active Concurrency" value={overview.metrics.concurrency.toLocaleString()} caption="Aggregate users" delta="+180" />
        <MetricCard label="p95 Latency" value={overview.metrics.p95Latency.toString()} unit="ms" caption="Control plane" delta="-12ms" />
        <MetricCard label="Average Latency" value={overview.metrics.avgLatency.toString()} unit="ms" caption="Global" delta="-4ms" />
        <MetricCard label="Error Rate" value={overview.metrics.errorRate.toString()} unit="%" caption="Platform" delta="-0.2%" />
      </section>

      <section className="grid gap-6 lg:grid-cols-[3fr_2fr]">
        <div className="rounded-3xl border border-slate-800/80 bg-slate-900/40 p-6">
          <div className="flex items-center justify-between">
            <h2 className="text-sm font-semibold uppercase tracking-[0.35em] text-slate-400">Adaptive performance trend</h2>
            <span className="text-xs text-slate-500">Last 12 minutes</span>
          </div>
          <div className="mt-6 h-64">
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={trendData}>
                <defs>
                  <linearGradient id="colorThroughput" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#6366f1" stopOpacity={0.6} />
                    <stop offset="95%" stopColor="#6366f1" stopOpacity={0} />
                  </linearGradient>
                  <linearGradient id="colorLatency" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#22d3ee" stopOpacity={0.4} />
                    <stop offset="95%" stopColor="#22d3ee" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="#1e293b" />
                <XAxis dataKey="timestamp" tickFormatter={(value) => dayjs(value).format('HH:mm:ss')} stroke="#475569" />
                <YAxis yAxisId="left" stroke="#475569" />
                <YAxis yAxisId="right" orientation="right" stroke="#475569" />
                <Tooltip
                  contentStyle={{ backgroundColor: '#0f172a', borderRadius: 16, border: '1px solid rgba(99,102,241,0.4)' }}
                  labelFormatter={(value) => dayjs(value).format('HH:mm:ss')}
                />
                <Area type="monotone" dataKey="throughput" stroke="#6366f1" strokeWidth={2} fillOpacity={1} fill="url(#colorThroughput)" yAxisId="left" name="Throughput" />
                <Area type="monotone" dataKey="p95Latency" stroke="#22d3ee" strokeWidth={2} fillOpacity={1} fill="url(#colorLatency)" yAxisId="right" name="p95 Latency" />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </div>

        <div className="flex flex-col gap-4 rounded-3xl border border-slate-800/80 bg-slate-900/40 p-6">
          <h2 className="text-sm font-semibold uppercase tracking-[0.35em] text-slate-400">Mission checklist</h2>
          <ul className="flex flex-col gap-3 text-sm text-slate-200">
            {overview.keyAlerts.map((alert) => (
              <li key={alert} className="flex items-start gap-3 rounded-2xl border border-slate-800/60 bg-slate-900/60 p-4">
                <AlertCircle className="mt-0.5 h-5 w-5 text-indigo-400" />
                <span>{alert}</span>
              </li>
            ))}
          </ul>
          <div className="mt-2 rounded-2xl border border-indigo-500/40 bg-indigo-500/10 p-4 text-xs text-indigo-200">
            Synchronised {dayjs(overview.updatedAt).format('HH:mm:ss')} • {trendData.length} datapoints
          </div>
        </div>
      </section>

      <section className="grid gap-6 lg:grid-cols-[3fr_2fr]">
        <div className="flex flex-col gap-4 rounded-3xl border border-slate-800/80 bg-slate-900/40 p-6">
          <div className="flex items-center justify-between">
            <h2 className="text-sm font-semibold uppercase tracking-[0.35em] text-slate-400">Active campaigns</h2>
            <span className="text-xs text-slate-500">{overview.activeRuns.length} ongoing</span>
          </div>
          <div className="flex flex-col gap-4">
            {overview.activeRuns.map((run) => (
              <div key={run.id} className="rounded-2xl border border-slate-800/60 bg-slate-900/60 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <p className="text-xs uppercase tracking-[0.4em] text-slate-500">{run.environment}</p>
                    <h3 className="text-lg font-semibold text-white">{run.name}</h3>
                  </div>
                  <StatusPill status={run.status} />
                </div>
                <div className="mt-4 grid gap-4 sm:grid-cols-4">
                  <div>
                    <p className="text-xs uppercase tracking-[0.35em] text-slate-500">Progress</p>
                    <p className="text-sm text-slate-200">{run.progress}%</p>
                    <div className="mt-2 h-1.5 w-full rounded-full bg-slate-800">
                      <div className="h-full rounded-full bg-gradient-to-r from-indigo-500 to-cyan-400" style={{ width: `${run.progress}%` }} />
                    </div>
                  </div>
                  <div>
                    <p className="text-xs uppercase tracking-[0.35em] text-slate-500">Concurrency</p>
                    <p className="text-sm text-slate-200">{run.concurrency.toLocaleString()}</p>
                  </div>
                  <div>
                    <p className="text-xs uppercase tracking-[0.35em] text-slate-500">Peak rps</p>
                    <p className="text-sm text-slate-200">{run.peakRps.toLocaleString()}</p>
                  </div>
                  <div>
                    <p className="text-xs uppercase tracking-[0.35em] text-slate-500">Error budget</p>
                    <p className="text-sm text-slate-200">{Math.round(run.errorBudgetConsumed * 100)}%</p>
                  </div>
                </div>
                {run.blockers.length ? (
                  <div className="mt-4 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-200">
                    {run.blockers[0]}
                  </div>
                ) : null}
                <div className="mt-4 grid gap-4 md:grid-cols-2">
                  <div>
                    <p className="text-xs uppercase tracking-[0.35em] text-slate-500">Timeline</p>
                    <p className="text-sm text-slate-200">
                      Started {dayjs(run.startTime).fromNow()} • ETA {dayjs(run.estimatedEndTime).fromNow()}
                    </p>
                  </div>
                  <div>
                    <p className="text-xs uppercase tracking-[0.35em] text-slate-500">Checkpoints</p>
                    <p className="text-sm text-slate-200">{run.checkpoints.map((checkpoint) => checkpoint.label).join(' • ')}</p>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>

        <div className="flex flex-col gap-4 rounded-3xl border border-slate-800/80 bg-slate-900/40 p-6">
          <h2 className="text-sm font-semibold uppercase tracking-[0.35em] text-slate-400">Milestones</h2>
          <ul className="flex flex-col gap-4">
            {overview.upcomingMilestones.map((milestone) => (
              <li key={milestone.label} className="flex items-center gap-4 rounded-2xl border border-slate-800/60 bg-slate-900/60 p-4">
                <Calendar className="h-8 w-8 rounded-2xl bg-indigo-500/20 p-1.5 text-indigo-300" />
                <div className="flex flex-col gap-1">
                  <p className="text-sm font-semibold text-white">{milestone.label}</p>
                  <p className="text-xs uppercase tracking-[0.35em] text-slate-500">{dayjs(milestone.timestamp).format('MMM D, HH:mm')}</p>
                  {milestone.note ? <p className="text-xs text-slate-300">{milestone.note}</p> : null}
                </div>
              </li>
            ))}
          </ul>
          <div className="mt-2 flex items-center gap-3 rounded-2xl border border-cyan-500/40 bg-cyan-500/10 p-4 text-sm text-cyan-100">
            <CloudLightning className="h-5 w-5" />
            Launch readiness automation keeps milestones in sync with your deployment calendar.
          </div>
        </div>
      </section>
    </div>
  );
};

export default Dashboard;
