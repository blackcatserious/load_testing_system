import React, { useMemo, useState } from 'react';
import {
  Activity,
  CheckCircle2,
  Crosshair,
  Loader2,
  PauseCircle,
  PlayCircle,
  Radar,
  RefreshCw,
  ShieldCheck,
  Sparkles,
  Target,
} from 'lucide-react';
import { legacyControlApi } from '../api/api';
import { useTestPlans } from '../api/hooks';
import type { StartTestRequest, TestPlan } from '../api/types';

const parseTargets = (value: string): string[] =>
  value
    .split(/\n|,/)
    .map((entry) => entry.trim())
    .filter(Boolean);

const formatDate = (value?: string | null) => (value ? new Date(value).toLocaleString() : '—');

const formatDuration = (seconds: number) => {
  if (!Number.isFinite(seconds) || seconds <= 0) return '—';
  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  const parts = [];
  if (hours > 0) parts.push(`${hours}h`);
  if (minutes > 0 || parts.length === 0) parts.push(`${minutes}m`);
  return parts.join(' ');
};

const TargetCard: React.FC<{
  plan: TestPlan;
  onStop: (plan: TestPlan) => Promise<void>;
  stopping: boolean;
}> = ({ plan, onStop, stopping }) => {
  const targetList = useMemo(() => plan.targets.slice(0, 3), [plan.targets]);

  return (
    <div className="flex flex-col justify-between gap-4 rounded-2xl border border-white/10 bg-slate-900/70 p-6 text-white shadow-lg shadow-black/30">
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-xs uppercase tracking-[0.35em] text-white/50">{plan.id}</p>
            <h3 className="mt-2 text-lg font-semibold text-white">{plan.attack_method ?? plan.engine}</h3>
          </div>
          <span
            className={`inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] ${
              plan.status === 'running'
                ? 'border border-emerald-500/40 bg-emerald-500/10 text-emerald-200'
                : 'border border-blue-500/40 bg-blue-500/10 text-blue-200'
            }`}
          >
            <Activity className="h-3.5 w-3.5" />
            {plan.status}
          </span>
        </div>

        <div className="grid gap-3 text-sm text-white/70">
          <div className="flex items-center justify-between">
            <span>Targets</span>
            <span className="text-white">{plan.targets.length}</span>
          </div>
          <div className="flex items-center justify-between">
            <span>Threads</span>
            <span className="text-white">{plan.threads.toLocaleString()}</span>
          </div>
          <div className="flex items-center justify-between">
            <span>Duration</span>
            <span className="text-white">{formatDuration(plan.duration)}</span>
          </div>
          <div className="flex items-center justify-between">
            <span>Stealth</span>
            <span className="text-white/80">{plan.stealth_profile ?? 'default'}</span>
          </div>
          <div className="flex items-center justify-between">
            <span>Proxy</span>
            <span className="text-white/80">{plan.proxy_profile ?? 'adaptive'}</span>
          </div>
          <div className="flex items-center justify-between">
            <span>Started</span>
            <span className="text-white/70">{formatDate(plan.started_at)}</span>
          </div>
        </div>

        {targetList.length > 0 && (
          <div className="rounded-xl border border-white/10 bg-white/5 p-3 text-xs text-white/70">
            <p className="text-white/50">Lead targets</p>
            <ul className="mt-2 space-y-1">
              {targetList.map((target) => (
                <li key={target} className="truncate" title={target}>
                  {target}
                </li>
              ))}
              {plan.targets.length > targetList.length && (
                <li className="text-white/50">+{plan.targets.length - targetList.length} more</li>
              )}
            </ul>
          </div>
        )}
      </div>

      <button
        type="button"
        onClick={() => onStop(plan)}
        className="inline-flex items-center justify-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-white transition hover:border-rose-400/50 hover:bg-rose-500/10"
        disabled={stopping}
      >
        {stopping ? <Loader2 className="h-4 w-4 animate-spin" /> : <PauseCircle className="h-4 w-4" />}
        Stop group
      </button>
    </div>
  );
};

const Targets: React.FC = () => {
  const { data: plans, loading, error, refetch } = useTestPlans(20);
  const [targetInput, setTargetInput] = useState('');
  const [profileId, setProfileId] = useState('ramp-up');
  const [threads, setThreads] = useState(1000);
  const [duration, setDuration] = useState(3600);
  const [engine, setEngine] = useState('auto-bypass');
  const [stealthProfile, setStealthProfile] = useState('high');
  const [proxyProfile, setProxyProfile] = useState('rotating');
  const [attackMethod, setAttackMethod] = useState('auto-bypass');
  const [behaviorProfile, setBehaviorProfile] = useState('aggressive');
  const [submitting, setSubmitting] = useState(false);
  const [stoppingId, setStoppingId] = useState<string | null>(null);
  const [feedback, setFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

  const handleStart = async () => {
    const targets = parseTargets(targetInput);
    if (targets.length === 0) {
      setFeedback({ type: 'error', message: 'Add at least one target URL.' });
      return;
    }

    const payload: StartTestRequest = {
      profile_id: profileId,
      threads,
      duration,
      engine,
      behavior_profile_id: behaviorProfile,
      targets,
      attack_method: attackMethod,
      stealth_profile: stealthProfile,
      proxy_profile: proxyProfile,
      user_agent_rotation: true,
      ja3_rotation: true,
      tls_rotation: true,
      proxy_rotation: true,
      spoof_headers: true,
    };

    try {
      setSubmitting(true);
      await legacyControlApi.start(payload);
      setFeedback({ type: 'success', message: 'Launch sequence transmitted to orchestrator.' });
      setTargetInput('');
      await refetch();
    } catch (err) {
      setFeedback({ type: 'error', message: err instanceof Error ? err.message : 'Failed to start test.' });
    } finally {
      setSubmitting(false);
    }
  };

  const handleStop = async (plan: TestPlan) => {
    try {
      setStoppingId(plan.id);
      await legacyControlApi.stop({ group_id: plan.id });
      setFeedback({ type: 'success', message: `Stop command sent for ${plan.id}.` });
      await refetch();
    } catch (err) {
      setFeedback({ type: 'error', message: err instanceof Error ? err.message : 'Failed to stop plan.' });
    } finally {
      setStoppingId(null);
    }
  };

  return (
    <div className="space-y-10 text-slate-100">
      <header className="rounded-3xl border border-white/10 bg-slate-900/70 p-8 shadow-xl shadow-black/40">
        <div className="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs uppercase tracking-[0.35em] text-slate-300">
              <Target className="h-3.5 w-3.5 text-blue-300" />
              Domain Targets
            </div>
            <h1 className="mt-4 text-3xl font-semibold text-white">Active orchestration groups</h1>
            <p className="mt-2 max-w-2xl text-sm text-white/70">
              Review live target groups sourced from the PHP orchestration layer and launch new stress tests with proxy and stealth
              presets aligned to your domain strategy.
            </p>
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/5 px-5 py-4 text-right text-white/70">
            <p className="text-xs uppercase tracking-[0.35em] text-white/50">Tracked plans</p>
            <p className="mt-1 text-2xl font-semibold text-white">{plans.length}</p>
            <p className="mt-2 text-xs text-white/60">Pulled from /api/test-plans</p>
          </div>
        </div>
      </header>

      {feedback && (
        <div
          className={`rounded-2xl border px-4 py-3 text-sm ${
            feedback.type === 'success'
              ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-100'
              : 'border-rose-500/40 bg-rose-500/10 text-rose-100'
          }`}
        >
          {feedback.message}
        </div>
      )}

      <section className="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <div className="space-y-6">
          <div className="flex items-center justify-between">
            <h2 className="text-xl font-semibold text-white">Orchestrated target groups</h2>
            <button
              type="button"
              onClick={() => refetch()}
              className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-white/70 transition hover:border-white/30 hover:text-white"
            >
              <RefreshCw className="h-3.5 w-3.5" /> Refresh
            </button>
          </div>

          {loading ? (
            <div className="flex min-h-[220px] items-center justify-center rounded-3xl border border-white/10 bg-slate-900/70 text-white/60">
              <Loader2 className="mr-3 h-5 w-5 animate-spin" /> Fetching plans…
            </div>
          ) : error ? (
            <div className="rounded-3xl border border-rose-500/30 bg-rose-500/10 p-6 text-rose-100">
              Unable to fetch orchestrator plans: {error}
            </div>
          ) : plans.length === 0 ? (
            <div className="flex min-h-[220px] flex-col items-center justify-center gap-4 rounded-3xl border border-white/10 bg-slate-900/70 text-white/60">
              <Sparkles className="h-10 w-10 text-white/40" />
              No groups registered yet.
            </div>
          ) : (
            <div className="grid gap-5 md:grid-cols-2">
              {plans.map((plan) => (
                <TargetCard key={plan.id} plan={plan} onStop={handleStop} stopping={stoppingId === plan.id} />
              ))}
            </div>
          )}
        </div>

        <aside className="flex flex-col gap-6">
          <div className="rounded-3xl border border-white/10 bg-slate-900/70 p-6 shadow-lg shadow-black/30">
            <div className="flex items-center gap-3">
              <ShieldCheck className="h-5 w-5 text-blue-300" />
              <div>
                <h3 className="text-sm font-semibold text-white">Launch new group</h3>
                <p className="text-xs text-white/60">Issue a start command directly to the orchestrator.</p>
              </div>
            </div>

            <div className="mt-6 space-y-5 text-sm text-white/70">
              <div>
                <label className="text-xs uppercase tracking-[0.35em] text-white/40">Targets</label>
                <textarea
                  value={targetInput}
                  onChange={(event) => setTargetInput(event.target.value)}
                  rows={4}
                  className="mt-2 w-full rounded-2xl border border-white/10 bg-slate-950/60 p-3 text-sm text-white shadow-inner shadow-black/40 focus:border-blue-500/50 focus:outline-none"
                  placeholder="https://target-one.tld\nhttps://target-two.tld"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-xs uppercase tracking-[0.35em] text-white/40">Threads</label>
                  <input
                    type="number"
                    min={1}
                    value={threads}
                    onChange={(event) => setThreads(Number(event.target.value))}
                    className="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/60 p-3 text-sm text-white focus:border-blue-500/50 focus:outline-none"
                  />
                </div>
                <div>
                  <label className="text-xs uppercase tracking-[0.35em] text-white/40">Duration (s)</label>
                  <input
                    type="number"
                    min={60}
                    value={duration}
                    onChange={(event) => setDuration(Number(event.target.value))}
                    className="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/60 p-3 text-sm text-white focus:border-blue-500/50 focus:outline-none"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-xs uppercase tracking-[0.35em] text-white/40">Profile</label>
                  <input
                    value={profileId}
                    onChange={(event) => setProfileId(event.target.value)}
                    className="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/60 p-3 text-sm text-white focus:border-blue-500/50 focus:outline-none"
                  />
                </div>
                <div>
                  <label className="text-xs uppercase tracking-[0.35em] text-white/40">Behavior</label>
                  <input
                    value={behaviorProfile}
                    onChange={(event) => setBehaviorProfile(event.target.value)}
                    className="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/60 p-3 text-sm text-white focus:border-blue-500/50 focus:outline-none"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-xs uppercase tracking-[0.35em] text-white/40">Engine</label>
                  <input
                    value={engine}
                    onChange={(event) => setEngine(event.target.value)}
                    className="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/60 p-3 text-sm text-white focus:border-blue-500/50 focus:outline-none"
                  />
                </div>
                <div>
                  <label className="text-xs uppercase tracking-[0.35em] text-white/40">Attack</label>
                  <input
                    value={attackMethod}
                    onChange={(event) => setAttackMethod(event.target.value)}
                    className="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/60 p-3 text-sm text-white focus:border-blue-500/50 focus:outline-none"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-xs uppercase tracking-[0.35em] text-white/40">Stealth</label>
                  <input
                    value={stealthProfile}
                    onChange={(event) => setStealthProfile(event.target.value)}
                    className="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/60 p-3 text-sm text-white focus:border-blue-500/50 focus:outline-none"
                  />
                </div>
                <div>
                  <label className="text-xs uppercase tracking-[0.35em] text-white/40">Proxy</label>
                  <input
                    value={proxyProfile}
                    onChange={(event) => setProxyProfile(event.target.value)}
                    className="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/60 p-3 text-sm text-white focus:border-blue-500/50 focus:outline-none"
                  />
                </div>
              </div>

              <button
                type="button"
                onClick={handleStart}
                disabled={submitting}
                className="mt-2 inline-flex w-full items-center justify-center gap-2 rounded-full border border-blue-500/40 bg-blue-500/20 px-4 py-3 text-sm font-semibold text-blue-100 transition hover:border-blue-400/60 hover:bg-blue-500/30 disabled:cursor-not-allowed disabled:opacity-60"
              >
                {submitting ? <Loader2 className="h-4 w-4 animate-spin" /> : <PlayCircle className="h-4 w-4" />}
                Launch run
              </button>
            </div>
          </div>

          <div className="rounded-3xl border border-white/10 bg-white/5 p-6 text-sm text-white/70">
            <div className="flex items-center gap-3 text-white">
              <Crosshair className="h-5 w-5 text-blue-300" />
              <div>
                <h3 className="text-sm font-semibold text-white">Operational notes</h3>
                <p className="text-xs text-white/60">Highlights from recent orchestrations</p>
              </div>
            </div>
            <ul className="mt-4 space-y-3">
              <li className="flex items-center gap-2 text-xs text-white/60">
                <Sparkles className="h-3.5 w-3.5 text-blue-300" /> Residential proxy pools synced hourly.
              </li>
              <li className="flex items-center gap-2 text-xs text-white/60">
                <Radar className="h-3.5 w-3.5 text-blue-300" /> JA3 rotation enabled across stealth kits.
              </li>
              <li className="flex items-center gap-2 text-xs text-white/60">
                <CheckCircle2 className="h-3.5 w-3.5 text-blue-300" /> Legacy PHP endpoints proxied via Express gateway.
              </li>
            </ul>
          </div>
        </aside>
      </section>
    </div>
  );
};

export default Targets;
