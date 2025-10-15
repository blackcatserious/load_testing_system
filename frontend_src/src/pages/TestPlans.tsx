import React, { useEffect, useMemo, useState } from 'react';
import { ClipboardList, Clock, Edit3, Layers, Link2 } from 'lucide-react';
import api from '../lib/api';
import StatusPill from '../components/StatusPill';
import LoadingState from '../components/LoadingState';
import ErrorState from '../components/ErrorState';
import dayjs from '../setupDayjs';
import type { TestPlan } from '../types';

const statusOrder: Record<TestPlan['status'], number> = {
  ready: 0,
  scheduled: 1,
  draft: 2,
};

const TestPlans: React.FC = () => {
  const [plans, setPlans] = useState<TestPlan[]>([]);
  const [selectedPlanId, setSelectedPlanId] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api
      .get<TestPlan[]>('/test-plans')
      .then((response) => {
        setPlans(response.data);
        setSelectedPlanId(response.data[0]?.id ?? null);
        setError(null);
      })
      .catch((err) => setError(err.message ?? 'Unable to load plans'))
      .finally(() => setLoading(false));
  }, []);

  const sortedPlans = useMemo(
    () => [...plans].sort((a, b) => statusOrder[a.status] - statusOrder[b.status]),
    [plans],
  );

  const selectedPlan = useMemo(
    () => plans.find((plan) => plan.id === selectedPlanId) ?? null,
    [plans, selectedPlanId],
  );

  if (loading) {
    return <LoadingState label="Fetching test plans" />;
  }

  if (error) {
    return <ErrorState description={error} />;
  }

  return (
    <div className="mx-auto grid max-w-7xl gap-8 px-4 pb-16 sm:px-6 lg:grid-cols-[340px_1fr] lg:px-8">
      <aside className="flex flex-col gap-4 rounded-3xl border border-slate-800/80 bg-slate-900/40 p-6">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.35em] text-slate-500">Test plans</p>
            <h1 className="mt-2 text-xl font-semibold text-white">Launch library</h1>
          </div>
          <button className="rounded-full bg-indigo-500/20 px-3 py-1 text-xs font-semibold text-indigo-200 transition hover:bg-indigo-500/30">
            New plan
          </button>
        </div>
        <div className="flex items-center gap-2 rounded-2xl border border-slate-800/60 bg-slate-900/60 p-3 text-xs text-slate-300">
          <Layers className="h-4 w-4 text-indigo-400" />
          Blueprint reusable load scenarios, complete with guardrails and coordination notes.
        </div>
        <ul className="flex flex-col gap-3">
          {sortedPlans.map((plan) => (
            <li key={plan.id}>
              <button
                type="button"
                onClick={() => setSelectedPlanId(plan.id)}
                className={`w-full rounded-2xl border px-4 py-3 text-left transition ${
                  selectedPlanId === plan.id
                    ? 'border-indigo-500/60 bg-indigo-500/10 text-white shadow-lg shadow-indigo-500/20'
                    : 'border-slate-800/60 bg-slate-900/60 text-slate-200 hover:border-indigo-500/30 hover:bg-slate-900'
                }`}
              >
                <div className="flex items-center justify-between text-sm font-semibold">
                  <span>{plan.name}</span>
                  <StatusPill status={plan.status} />
                </div>
                <p className="mt-2 text-xs text-slate-400">Updated {dayjs(plan.lastEdited).fromNow()}</p>
                <div className="mt-3 flex flex-wrap gap-2">
                  {plan.tags.map((tag) => (
                    <span key={tag} className="rounded-full bg-slate-800 px-3 py-1 text-[11px] uppercase tracking-[0.35em] text-slate-300">
                      {tag}
                    </span>
                  ))}
                </div>
              </button>
            </li>
          ))}
        </ul>
      </aside>

      <section className="flex flex-col gap-6 rounded-3xl border border-slate-800/80 bg-slate-900/40 p-6">
        {selectedPlan ? (
          <>
            <header className="flex flex-wrap items-start justify-between gap-4">
              <div className="max-w-2xl">
                <div className="text-xs uppercase tracking-[0.35em] text-slate-500">{selectedPlan.team}</div>
                <h2 className="mt-2 text-2xl font-semibold text-white">{selectedPlan.name}</h2>
                <p className="mt-3 text-sm text-slate-300">{selectedPlan.description}</p>
              </div>
              <div className="flex items-center gap-2">
                <button className="flex items-center gap-2 rounded-full border border-slate-700/70 px-4 py-2 text-xs font-semibold text-slate-200 transition hover:border-indigo-400/70 hover:text-white">
                  <Edit3 className="h-4 w-4" />
                  Edit
                </button>
                <button className="flex items-center gap-2 rounded-full bg-gradient-to-r from-indigo-500 via-blue-500 to-cyan-400 px-4 py-2 text-xs font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:brightness-105">
                  <ClipboardList className="h-4 w-4" />
                  Activate
                </button>
              </div>
            </header>

            <div className="grid gap-4 md:grid-cols-2">
              <div className="rounded-2xl border border-slate-800/60 bg-slate-900/60 p-4">
                <p className="text-xs uppercase tracking-[0.35em] text-slate-500">Owner</p>
                <p className="mt-1 text-sm text-slate-200">{selectedPlan.owner}</p>
                <div className="mt-4 flex items-center gap-3 text-xs text-slate-400">
                  <Clock className="h-4 w-4 text-indigo-400" />
                  Duration {selectedPlan.durationMinutes} minutes • {selectedPlan.targets.length} targets
                </div>
              </div>
              <div className="rounded-2xl border border-slate-800/60 bg-slate-900/60 p-4">
                <p className="text-xs uppercase tracking-[0.35em] text-slate-500">Load shape</p>
                <p className="mt-1 text-sm text-slate-200">{selectedPlan.loadShape}</p>
                <div className="mt-4 flex flex-wrap gap-2 text-[11px] uppercase tracking-[0.35em] text-slate-400">
                  {selectedPlan.entryCriteria.map((criterion) => (
                    <span key={criterion} className="rounded-full border border-indigo-500/40 bg-indigo-500/10 px-3 py-1 text-indigo-200">
                      {criterion}
                    </span>
                  ))}
                </div>
              </div>
            </div>

            <div className="rounded-2xl border border-slate-800/60 bg-slate-900/60 p-4">
              <p className="text-xs uppercase tracking-[0.35em] text-slate-500">Targets</p>
              <div className="mt-4 grid gap-3 md:grid-cols-2">
                {selectedPlan.targets.map((target) => (
                  <div key={target.id} className="rounded-xl border border-slate-800/50 bg-slate-900/60 p-4">
                    <div className="flex items-center justify-between">
                      <div>
                        <p className="text-sm font-semibold text-white">{target.name}</p>
                        <p className="text-xs uppercase tracking-[0.35em] text-slate-500">{target.protocol.toUpperCase()}</p>
                      </div>
                      <span className="rounded-full bg-indigo-500/20 px-3 py-1 text-xs font-semibold text-indigo-200">{target.region}</span>
                    </div>
                    <div className="mt-3 grid grid-cols-3 gap-2 text-[11px] uppercase tracking-[0.35em] text-slate-400">
                      <div>
                        <p>Baseline</p>
                        <p className="mt-1 text-sm text-slate-200">{target.baselineRps.toLocaleString()} rps</p>
                      </div>
                      <div>
                        <p>Max</p>
                        <p className="mt-1 text-sm text-slate-200">{target.maxRps.toLocaleString()} rps</p>
                      </div>
                      <div>
                        <p>SLO</p>
                        <p className="mt-1 text-sm text-slate-200">{target.latencySloMs} ms</p>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            <div className="rounded-2xl border border-slate-800/60 bg-slate-900/60 p-4">
              <p className="text-xs uppercase tracking-[0.35em] text-slate-500">Exit criteria</p>
              <ul className="mt-3 flex list-disc flex-col gap-2 pl-5 text-sm text-slate-300">
                {selectedPlan.exitCriteria.map((criterion) => (
                  <li key={criterion}>{criterion}</li>
                ))}
              </ul>
            </div>

            <div className="rounded-2xl border border-slate-800/60 bg-slate-900/60 p-4 text-sm text-slate-300">
              <div className="flex items-center gap-2 text-xs uppercase tracking-[0.35em] text-slate-500">
                <Link2 className="h-4 w-4 text-indigo-400" />
                Linked checklists
              </div>
              <p className="mt-2 text-sm text-slate-200">Coordinate synthetic monitors, failover rehearsals, and reporting distribution before launch.</p>
            </div>
          </>
        ) : (
          <div className="flex h-full flex-col items-center justify-center gap-3 text-slate-300">
            <ClipboardList className="h-10 w-10 text-indigo-400" />
            <p className="text-sm">Select a plan to see its details.</p>
          </div>
        )}
      </section>
    </div>
  );
};

export default TestPlans;
