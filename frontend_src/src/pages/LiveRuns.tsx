import React, { useMemo, useState } from 'react';
import {
  Activity,
  AlertTriangle,
  Calendar,
  Clock,
  Loader2,
  PlayCircle,
  RefreshCw,
  Shield,
  StopCircle,
} from 'lucide-react';
import { useRunDetails, useRunSummary, useTestRuns } from '../api/hooks';
import type { TestRun } from '../api/types';

const statusTone: Record<string, string> = {
  running: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200',
  completed: 'border-blue-500/40 bg-blue-500/10 text-blue-200',
  stopped: 'border-amber-500/40 bg-amber-500/10 text-amber-200',
  error: 'border-rose-500/40 bg-rose-500/10 text-rose-200',
};

const formatDateTime = (value?: string | null) => (value ? new Date(value).toLocaleString() : '—');

const formatStatus = (status: string | undefined) => status?.replace(/_/g, ' ').toLowerCase() ?? 'unknown';

const LiveRuns: React.FC = () => {
  const { data: runs, loading, error, refetch } = useTestRuns(40, 15000);
  const [selectedRun, setSelectedRun] = useState<TestRun | null>(null);
  const summary = useRunSummary(runs);
  const { data: runDetails, loading: detailLoading } = useRunDetails(selectedRun?.id ?? null);

  const sortedRuns = useMemo(
    () =>
      [...runs].sort((a, b) => {
        const aTime = a.started_at ? new Date(a.started_at).getTime() : 0;
        const bTime = b.started_at ? new Date(b.started_at).getTime() : 0;
        return bTime - aTime;
      }),
    [runs],
  );

  return (
    <div className="space-y-10 text-slate-100">
      <header className="rounded-3xl border border-white/10 bg-slate-900/70 p-8 shadow-xl shadow-black/40">
        <div className="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs uppercase tracking-[0.35em] text-slate-300">
              <Activity className="h-3.5 w-3.5 text-blue-300" />
              Live Runs
            </div>
            <h1 className="mt-4 text-3xl font-semibold text-white">Orchestrator execution stream</h1>
            <p className="mt-2 max-w-2xl text-sm text-white/70">
              Monitor every run reported by <code className="rounded bg-white/10 px-1 py-0.5 text-xs text-white/80">/api/test-runs</code>. Compare status, target URLs, and mitigation results without leaving the new control surface.
            </p>
          </div>
          <div className="grid gap-3 text-right text-sm text-white/70">
            {Object.entries(summary).map(([status, count]) => (
              <div key={status} className="inline-flex items-center justify-end gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1">
                <span className="text-xs uppercase tracking-[0.35em] text-white/50">{status}</span>
                <span className="text-white">{count}</span>
              </div>
            ))}
          </div>
        </div>
      </header>

      <section className="grid gap-6 xl:grid-cols-[2fr_1fr]">
        <div className="rounded-3xl border border-white/10 bg-slate-900/70 p-6 shadow-lg shadow-black/30">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-xs uppercase tracking-[0.35em] text-blue-300/80">Run registry</p>
              <h2 className="mt-2 text-xl font-semibold text-white">Active &amp; recent executions</h2>
            </div>
            <button
              type="button"
              onClick={() => refetch()}
              className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-white/70 transition hover:border-white/30 hover:text-white"
            >
              <RefreshCw className="h-3.5 w-3.5" /> Refresh
            </button>
          </div>

          {loading ? (
            <div className="mt-8 flex items-center justify-center gap-3 rounded-2xl border border-white/10 bg-slate-950/60 p-8 text-white/60">
              <Loader2 className="h-5 w-5 animate-spin" /> Fetching live runs…
            </div>
          ) : error ? (
            <div className="mt-8 rounded-2xl border border-rose-500/30 bg-rose-500/10 p-6 text-rose-100">
              <AlertTriangle className="mr-2 inline h-4 w-4" /> {error}
            </div>
          ) : sortedRuns.length === 0 ? (
            <div className="mt-8 flex flex-col items-center justify-center gap-4 rounded-2xl border border-white/10 bg-slate-950/60 p-10 text-white/60">
              <Shield className="h-10 w-10 text-white/40" />
              No runs recorded yet.
            </div>
          ) : (
            <div className="mt-8 overflow-x-auto">
              <table className="min-w-full divide-y divide-white/10 text-sm">
                <thead>
                  <tr className="bg-white/5 text-left text-xs uppercase tracking-widest text-white/50">
                    <th className="px-4 py-3">Run</th>
                    <th className="px-4 py-3">Target</th>
                    <th className="px-4 py-3">Status</th>
                    <th className="px-4 py-3">Started</th>
                    <th className="px-4 py-3">Finished</th>
                    <th className="px-4 py-3">Detection</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-white/5">
                  {sortedRuns.map((run) => {
                    const statusKey = formatStatus(run.status);
                    const tone = statusTone[statusKey] ?? 'border-white/10 bg-white/5 text-white/70';
                    return (
                      <tr
                        key={run.id}
                        className={`cursor-pointer transition hover:bg-white/5 ${selectedRun?.id === run.id ? 'bg-white/5' : ''}`}
                        onClick={() => setSelectedRun(run)}
                      >
                        <td className="px-4 py-3 font-semibold text-white">{run.id}</td>
                        <td className="px-4 py-3 text-white/70">
                          <span className="line-clamp-1" title={run.target_url}>
                            {run.target_url ?? '—'}
                          </span>
                        </td>
                        <td className="px-4 py-3">
                          <span className={`inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] ${tone}`}>
                            {statusKey}
                          </span>
                        </td>
                        <td className="px-4 py-3 text-white/70">{formatDateTime(run.started_at)}</td>
                        <td className="px-4 py-3 text-white/70">{formatDateTime(run.finished_at)}</td>
                        <td className="px-4 py-3 text-white/70">
                          {run.success_detection_triggered ? (
                            <span className="inline-flex items-center gap-2 rounded-full border border-emerald-500/40 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-200">
                              <PlayCircle className="h-3.5 w-3.5" /> Triggered
                            </span>
                          ) : (
                            <span className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-white/60">
                              <StopCircle className="h-3.5 w-3.5" /> Idle
                            </span>
                          )}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>

        <aside className="flex flex-col gap-6">
          <div className="rounded-3xl border border-white/10 bg-slate-900/70 p-6 shadow-lg shadow-black/30">
            <div className="flex items-center gap-3">
              <Clock className="h-5 w-5 text-blue-300" />
              <div>
                <h3 className="text-sm font-semibold text-white">Selected run</h3>
                <p className="text-xs text-white/60">Deep dive into orchestrator metadata</p>
              </div>
            </div>

            {!selectedRun ? (
              <p className="mt-6 text-sm text-white/60">Tap a run to inspect status, detection flags, and orchestrator metadata.</p>
            ) : detailLoading ? (
              <div className="mt-6 flex items-center gap-3 text-white/60">
                <Loader2 className="h-4 w-4 animate-spin" /> Loading run details…
              </div>
            ) : runDetails ? (
              <dl className="mt-6 space-y-3 text-sm text-white/70">
                <div className="flex items-center justify-between">
                  <dt className="text-white/50">Run ID</dt>
                  <dd className="text-white">{runDetails.id}</dd>
                </div>
                <div className="flex items-center justify-between">
                  <dt className="text-white/50">Group</dt>
                  <dd className="text-white/80">{runDetails.group_id ?? '—'}</dd>
                </div>
                <div className="flex items-center justify-between">
                  <dt className="text-white/50">Target</dt>
                  <dd className="max-w-[220px] truncate text-white/80" title={runDetails.target_url ?? undefined}>
                    {runDetails.target_url ?? '—'}
                  </dd>
                </div>
                <div className="flex items-center justify-between">
                  <dt className="text-white/50">Permanent failure</dt>
                  <dd className="text-white/80">{runDetails.permanent_failure_achieved ? 'Yes' : 'No'}</dd>
                </div>
              </dl>
            ) : (
              <p className="mt-6 text-sm text-white/60">No detail payload available for this run.</p>
            )}
          </div>

          <div className="rounded-3xl border border-white/10 bg-white/5 p-6 text-sm text-white/70">
            <div className="flex items-center gap-3 text-white">
              <Calendar className="h-5 w-5 text-blue-300" />
              <div>
                <h3 className="text-sm font-semibold text-white">Operational cadence</h3>
                <p className="text-xs text-white/60">Snapshot from the PHP orchestration timeline</p>
              </div>
            </div>
            <ul className="mt-4 space-y-3">
              <li className="flex items-center gap-2 text-xs text-white/60">
                <PlayCircle className="h-3.5 w-3.5 text-blue-300" /> Runs refresh every 15 seconds.
              </li>
              <li className="flex items-center gap-2 text-xs text-white/60">
                <Shield className="h-3.5 w-3.5 text-blue-300" /> Error states bubble up with actionable messaging.
              </li>
              <li className="flex items-center gap-2 text-xs text-white/60">
                <RefreshCw className="h-3.5 w-3.5 text-blue-300" /> Data served via Express orchestration proxy.
              </li>
            </ul>
          </div>
        </aside>
      </section>
    </div>
  );
};

export default LiveRuns;
