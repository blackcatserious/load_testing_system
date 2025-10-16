import React from 'react';
import {
  Activity,
  AlertTriangle,
  Globe2,
  LineChart as LineChartIcon,
  Radar,
  Server,
  Shield,
  Signal,
  Users,
  Zap,
} from 'lucide-react';
import { LineChart, Line, XAxis, YAxis, ResponsiveContainer, Tooltip } from 'recharts';

const chartData = [
  { time: '00:00', success: 45, error: 12 },
  { time: '04:00', success: 52, error: 8 },
  { time: '08:00', success: 48, error: 15 },
  { time: '12:00', success: 61, error: 7 },
  { time: '16:00', success: 55, error: 11 },
  { time: '20:00', success: 67, error: 5 },
  { time: '24:00', success: 59, error: 9 },
];

const statHighlights = [
  {
    label: 'Registered Operators',
    value: '632,576',
    delta: '+3.4%',
    icon: Users,
    tone: 'from-emerald-500/20 to-emerald-500/5 text-emerald-300',
  },
  {
    label: 'Active Domains',
    value: '52',
    delta: '+11',
    icon: Globe2,
    tone: 'from-sky-500/20 to-sky-500/5 text-sky-300',
  },
  {
    label: 'Live Stressors',
    value: '113',
    delta: '+18%',
    icon: Zap,
    tone: 'from-blue-500/20 to-blue-500/5 text-blue-300',
  },
  {
    label: 'Escalations',
    value: '29',
    delta: 'stable',
    icon: Activity,
    tone: 'from-indigo-500/20 to-indigo-500/5 text-indigo-300',
  },
];

const radarMetrics = [
  { label: 'Anti-DDoS Bypass', score: 92 },
  { label: 'Proxy Hygiene', score: 86 },
  { label: 'Stealth Fingerprinting', score: 94 },
  { label: 'TLS Profile Drift', score: 88 },
];

const activityFeed = [
  {
    title: 'New Telegram News Channel',
    description: 'Stay synced with deployment advisories and new evasion kits.',
    time: '2 hours ago',
  },
  {
    title: 'Package downgrade reminder',
    description: 'Confirm plan alignment before the nightly automation window.',
    time: '4 hours ago',
  },
  {
    title: 'Cross-platform validation',
    description: 'Domain skins refreshed for mobile & console deployments.',
    time: '6 hours ago',
  },
  {
    title: 'Terms & conditions refresh',
    description: 'Updated compliance matrix for customer managed attacks.',
    time: '8 hours ago',
  },
];

const sentinelCards = [
  {
    title: 'Proxy Pools',
    value: '10.4M',
    subtitle: 'rotating & residential',
    icon: Server,
    status: 'Healthy',
  },
  {
    title: 'User Agents',
    value: '4,100+',
    subtitle: 'fingerprint kits online',
    icon: Radar,
    status: 'Syncing',
  },
  {
    title: 'TLS Profiles',
    value: '78',
    subtitle: 'stealth blends enabled',
    icon: Shield,
    status: 'Adaptive',
  },
];

const Dashboard: React.FC = () => {
  return (
    <div className="space-y-10">
      <section className="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
        {statHighlights.map((card) => {
          const Icon = card.icon;
          return (
            <div
              key={card.label}
              className={`relative overflow-hidden rounded-2xl border border-white/10 bg-slate-900/70 p-6 shadow-lg shadow-black/30 transition hover:-translate-y-1 hover:border-white/20`}
            >
              <div className={`absolute inset-0 bg-gradient-to-br ${card.tone} opacity-70`} aria-hidden />
              <div className="relative">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-semibold uppercase tracking-widest text-white/70">{card.label}</span>
                  <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-slate-900/80 text-white">
                    <Icon className="h-5 w-5" />
                  </span>
                </div>
                <p className="mt-6 text-3xl font-semibold text-white">{card.value}</p>
                <p className="mt-2 text-sm text-white/70">{card.delta}</p>
              </div>
            </div>
          );
        })}
      </section>

      <section className="grid gap-6 xl:grid-cols-[2fr_1fr]">
        <div className="rounded-3xl border border-white/10 bg-slate-900/70 p-6 shadow-lg shadow-black/30">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-xs uppercase tracking-[0.35em] text-blue-300/70">Live Telemetry</p>
              <h3 className="mt-1 text-xl font-semibold text-white">Domain throughput &amp; mitigation</h3>
            </div>
            <span className="inline-flex items-center gap-2 rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-medium text-emerald-300">
              <span className="h-2 w-2 rounded-full bg-emerald-400" />
              Stable
            </span>
          </div>
          <div className="mt-8 h-72 w-full">
            <ResponsiveContainer width="100%" height="100%">
              <LineChart data={chartData}>
                <XAxis dataKey="time" stroke="#64748b" tickLine={false} axisLine={{ stroke: '#334155' }} />
                <YAxis stroke="#64748b" tickLine={false} axisLine={{ stroke: '#334155' }} />
                <Tooltip
                  contentStyle={{
                    background: 'rgba(15, 23, 42, 0.95)',
                    borderRadius: '0.75rem',
                    border: '1px solid rgba(148, 163, 184, 0.2)',
                    color: '#e2e8f0',
                  }}
                />
                <Line type="monotone" dataKey="success" stroke="#38bdf8" strokeWidth={3} dot={false} />
                <Line type="monotone" dataKey="error" stroke="#f97316" strokeWidth={2} strokeDasharray="4 6" dot={false} />
              </LineChart>
            </ResponsiveContainer>
          </div>
          <div className="mt-6 grid gap-4 md:grid-cols-3">
            <div className="rounded-xl border border-blue-500/20 bg-blue-500/5 p-4 text-sm text-blue-100">
              <p className="text-xs uppercase tracking-widest text-blue-300/80">Average Success</p>
              <p className="mt-2 text-2xl font-semibold text-white">92.4%</p>
              <p className="mt-1 text-xs text-blue-200/80">across 24 hour rolling window</p>
            </div>
            <div className="rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-4 text-sm text-emerald-100">
              <p className="text-xs uppercase tracking-widest text-emerald-300/80">Proxy hygiene</p>
              <p className="mt-2 text-2xl font-semibold text-white">98.1%</p>
              <p className="mt-1 text-xs text-emerald-200/80">residential pools auto-rotated</p>
            </div>
            <div className="rounded-xl border border-orange-500/20 bg-orange-500/5 p-4 text-sm text-orange-100">
              <p className="text-xs uppercase tracking-widest text-orange-300/80">Error windows</p>
              <p className="mt-2 text-2xl font-semibold text-white">5.2m</p>
              <p className="mt-1 text-xs text-orange-200/80">peak mitigation in the last cycle</p>
            </div>
          </div>
        </div>

        <div className="flex flex-col gap-6">
          <div className="rounded-3xl border border-white/10 bg-slate-900/70 p-6 shadow-lg shadow-black/30">
            <div className="flex items-center gap-3">
              <LineChartIcon className="h-5 w-5 text-blue-300" />
              <div>
                <h4 className="text-sm font-semibold text-white">Adaptive Shield Score</h4>
                <p className="text-xs text-white/60">Evaluated against current domain posture</p>
              </div>
            </div>
            <ul className="mt-5 space-y-4">
              {radarMetrics.map((metric) => (
                <li key={metric.label} className="flex items-center justify-between gap-4">
                  <span className="text-sm text-white/70">{metric.label}</span>
                  <div className="flex items-center gap-3">
                    <div className="h-1.5 w-28 rounded-full bg-white/10">
                      <div
                        className="h-full rounded-full bg-blue-400"
                        style={{ width: `${metric.score}%` }}
                      />
                    </div>
                    <span className="text-sm font-semibold text-white">{metric.score}%</span>
                  </div>
                </li>
              ))}
            </ul>
          </div>

          <div className="rounded-3xl border border-white/10 bg-slate-900/70 p-6 shadow-lg shadow-black/30">
            <div className="flex items-center gap-3">
              <AlertTriangle className="h-5 w-5 text-orange-300" />
              <div>
                <h4 className="text-sm font-semibold text-white">Active Alerts</h4>
                <p className="text-xs text-white/60">2 mitigation windows recommended</p>
              </div>
            </div>
            <div className="mt-4 space-y-4 text-xs text-white/70">
              <div className="rounded-xl border border-orange-400/20 bg-orange-500/10 p-3">
                <p className="font-semibold text-orange-100">Origin saturation detected</p>
                <p className="mt-1 text-orange-50/80">Shift 40% load to Oceania proxies for next cycle.</p>
              </div>
              <div className="rounded-xl border border-rose-400/20 bg-rose-500/10 p-3">
                <p className="font-semibold text-rose-100">Captcha wall flagged</p>
                <p className="mt-1 text-rose-50/80">Inject stealth kit alpha-19 for targeted domains.</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section className="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
        <div className="rounded-3xl border border-white/10 bg-slate-900/70 p-6 shadow-lg shadow-black/30">
          <div className="flex items-center gap-3">
            <Signal className="h-5 w-5 text-blue-300" />
            <div>
              <h4 className="text-sm font-semibold text-white">Sentinel resource pools</h4>
              <p className="text-xs text-white/60">Runtime resources fuelling the domain</p>
            </div>
          </div>
          <div className="mt-6 grid gap-4 md:grid-cols-3">
            {sentinelCards.map((card) => {
              const Icon = card.icon;
              return (
                <div
                  key={card.title}
                  className="rounded-2xl border border-white/10 bg-slate-950/60 p-4 transition hover:border-white/20"
                >
                  <div className="flex items-center justify-between">
                    <span className="text-xs font-semibold uppercase tracking-widest text-white/60">{card.title}</span>
                    <span className="rounded-lg border border-white/10 bg-white/5 p-2 text-white/80">
                      <Icon className="h-4 w-4" />
                    </span>
                  </div>
                  <p className="mt-5 text-2xl font-semibold text-white">{card.value}</p>
                  <p className="text-xs text-white/60">{card.subtitle}</p>
                  <span className="mt-4 inline-flex items-center gap-2 rounded-full bg-emerald-500/10 px-3 py-1 text-xs text-emerald-300">
                    <span className="h-1.5 w-1.5 rounded-full bg-emerald-400" />
                    {card.status}
                  </span>
                </div>
              );
            })}
          </div>
        </div>

        <div className="rounded-3xl border border-white/10 bg-slate-900/70 p-6 shadow-lg shadow-black/30">
          <div className="flex items-center gap-3">
            <Activity className="h-5 w-5 text-blue-300" />
            <div>
              <h4 className="text-sm font-semibold text-white">Operational Broadcast</h4>
              <p className="text-xs text-white/60">Latest transmissions from command</p>
            </div>
          </div>
          <div className="mt-6 space-y-5">
            {activityFeed.map((activity) => (
              <div key={activity.title} className="relative pl-6">
                <span className="absolute left-0 top-1.5 flex h-2.5 w-2.5 items-center justify-center">
                  <span className="h-2.5 w-2.5 rounded-full bg-blue-400" />
                </span>
                <div className="rounded-xl border border-white/10 bg-slate-950/60 p-4">
                  <div className="flex items-center justify-between">
                    <h5 className="text-sm font-semibold text-white">{activity.title}</h5>
                    <span className="text-xs text-white/50">{activity.time}</span>
                  </div>
                  <p className="mt-2 text-sm text-white/70">{activity.description}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>
    </div>
  );
};

export default Dashboard;
