import React, { useEffect, useMemo, useState } from 'react';
import { Activity, BarChart3, Flag, Play } from 'lucide-react';
import api from '../lib/api';
import LoadingState from '../components/LoadingState';
import ErrorState from '../components/ErrorState';
import StatusPill from '../components/StatusPill';
import dayjs from '../setupDayjs';
import type { TestRun } from '../types';

const Runs: React.FC = () => {
  const [runs, setRuns] = useState<TestRun[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api
      .get<TestRun[]>('/runs')
      .then((response) => {
        setRuns(response.data);
        setError(null);
      })
      .catch((err) => setError(err.message ?? 'Unable to load runs'))
      .finally(() => setLoading(false));
  }, []);

  const groupedRuns = useMemo(() => {
    return runs.reduce<Record<string, TestRun[]>>((acc, run) => {
      const key = run.status;
      acc[key] = acc[key] ?? [];
      acc[key].push(run);
      return acc;
    }, {});
  }, [runs]);

  if (loading) {
    return <LoadingState label="Synchronising runs" />;
  }

  if (error) {
    return <ErrorState description={error} />;
  }

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-8 px-4 pb-16 sm:px-6 lg:px-8">
      <header className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.35em] text-slate-500">Live runs</p>
          <h1 className="mt-2 text-3xl font-semibold text-white">Operational theatre</h1>
          <p className="mt-2 max-w-2xl text-sm text-slate-300">
            Track active, scheduled, and historical runs with full fidelity across checkpoints, phases, and error budgets.
          </p>
        </div>
        <button className="flex items-center gap-2 rounded-full bg-gradient-to-r from-indigo-500 via-blue-500 to-cyan-400 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:brightness-105">
          <Play className="h-4 w-4" />
          Schedule run
        </button>
      </header>

      <section className="grid gap-6 lg:grid-cols-2">
        {Object.entries(groupedRuns).map(([status, items]) => (
          <div key={status} className="flex flex-col gap-4 rounded-3xl border border-slate-800/80 bg-slate-900/40 p-6">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2 text-xs uppercase tracking-[0.35em] text-slate-500">
                <Activity className="h-4 w-4 text-indigo-400" />
                {status}
              </div>
              <StatusPill status={status as TestRun['status']} />
            </div>
            <div className="flex flex-col gap-4">
              {items.map((run) => (
                <article key={run.id} className="rounded-2xl border border-slate-800/60 bg-slate-900/60 p-4">
                  <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                      <h2 className="text-lg font-semibold text-white">{run.name}</h2>
                      <p className="text-xs uppercase tracking-[0.35em] text-slate-500">Plan {run.planId}</p>
                    </div>
                    <span className="rounded-full bg-indigo-500/20 px-3 py-1 text-xs font-semibold uppercase tracking-[0.35em] text-indigo-200">
                      {run.environment}
                    </span>
                  </div>
                  <div className="mt-4 grid gap-3 sm:grid-cols-4">
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
                  <div className="mt-4 grid gap-4 text-sm text-slate-300 md:grid-cols-2">
                    <div className="flex items-center gap-2">
                      <Flag className="h-4 w-4 text-indigo-400" />
                      <span>Started {dayjs(run.startTime).format('MMM D, HH:mm')} • ETA {dayjs(run.estimatedEndTime).format('HH:mm')}</span>
                    </div>
                    <div className="flex items-center gap-2">
                      <BarChart3 className="h-4 w-4 text-indigo-400" />
                      <span>{run.phases.map((phase) => `${phase.phase} ${phase.targetRps} rps`).join(' → ')}</span>
                    </div>
                  </div>
                  {run.blockers.length ? (
                    <div className="mt-4 rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-200">
                      {run.blockers.join(' • ')}
                    </div>
                  ) : null}
                </article>
              ))}
            </div>
          </div>
        ))}
      </section>
    </div>
  );
};

export default Runs;
