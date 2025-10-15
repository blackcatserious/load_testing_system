import React from 'react';
import {
  Activity,
  AlertTriangle,
  ArrowUpRight,
  BarChart3,
  CalendarClock,
  CheckCircle2,
  Cloud,
  Cpu,
  Gauge,
  Layers,
  PlayCircle,
  ShieldCheck,
  ThermometerSun,
  TimerReset,
} from 'lucide-react';
import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer } from 'recharts';

const summaryCards = [
  {
    title: 'Average Response Time',
    value: '312 ms',
    change: '12% faster vs last week',
    icon: Gauge,
    accent: 'from-cyan-400/20 via-blue-500/10 to-transparent',
  },
  {
    title: 'Peak Throughput',
    value: '248k req/min',
    change: 'Sustained for 42 minutes',
    icon: Activity,
    accent: 'from-indigo-500/20 via-purple-500/10 to-transparent',
  },
  {
    title: 'Error Rate',
    value: '0.03%',
    change: 'Holding steady across 5 regions',
    icon: ShieldCheck,
    accent: 'from-emerald-500/20 via-teal-500/10 to-transparent',
  },
];

const performanceTimeline = [
  { time: '00:00', latency: 380, throughput: 142 },
  { time: '04:00', latency: 340, throughput: 168 },
  { time: '08:00', latency: 295, throughput: 190 },
  { time: '12:00', latency: 310, throughput: 215 },
  { time: '16:00', latency: 330, throughput: 208 },
  { time: '20:00', latency: 298, throughput: 224 },
  { time: '24:00', latency: 312, throughput: 219 },
];

const activeTests = [
  {
    id: 'RUN-3412',
    name: 'Storefront API soak test',
    owner: 'Commerce Platform',
    status: 'Running',
    progress: 72,
    startedAt: '14:30 UTC',
    load: '180k vusers',
    throughput: '245k req/min',
  },
  {
    id: 'RUN-3387',
    name: 'Checkout flow spike resilience',
    owner: 'Payments Guild',
    status: 'Monitoring',
    progress: 54,
    startedAt: '12:05 UTC',
    load: '90k vusers',
    throughput: '118k req/min',
  },
  {
    id: 'RUN-3341',
    name: 'Mobile app cold-start test',
    owner: 'Mobile Experience',
    status: 'Completed',
    progress: 100,
    startedAt: '09:10 UTC',
    load: '25k devices',
    throughput: '32k req/min',
  },
];

const readinessChecks = [
  {
    label: 'Canary release gate',
    status: 'Ready',
    description: 'Synthetic monitors green across all target regions',
    icon: CheckCircle2,
  },
  {
    label: 'Capacity forecast',
    status: 'Attention',
    description: 'APAC traffic expected to exceed planned ramp by 8%',
    icon: AlertTriangle,
  },
  {
    label: 'Incident backlog',
    status: 'Clear',
    description: 'No open issues blocking performance testing',
    icon: TimerReset,
  },
];

const insightHighlights = [
  {
    title: 'Cache hit ratio recovered to 96%',
    detail: 'After enabling tiered caching for catalog media assets',
    icon: Cloud,
  },
  {
    title: 'Autoscaling warm pool reduced cold starts by 36%',
    detail: 'Service instances now pre-provisioned before test ramps',
    icon: ThermometerSun,
  },
  {
    title: 'Database replica lag < 120ms during sustained peaks',
    detail: 'Read-after-write patterns validated under load',
    icon: Layers,
  },
];

const releaseCalendar = [
  { date: 'Aug 9', label: 'Payments rollout', type: 'Critical', team: 'Payments Guild' },
  { date: 'Aug 12', label: 'Personalization tuning', type: 'Major', team: 'Experience' },
  { date: 'Aug 17', label: 'Regional expansion rehearsal', type: 'Simulation', team: 'SRE Asia' },
];

const statusBadgeClasses: Record<string, string> = {
  Running: 'bg-blue-500/20 text-blue-100 border border-blue-400/40',
  Monitoring: 'bg-amber-500/15 text-amber-100 border border-amber-400/30',
  Completed: 'bg-emerald-500/15 text-emerald-100 border border-emerald-400/30',
};

const Dashboard: React.FC = () => {
  return (
    <div className="relative">
      <div className="bg-gradient-to-br from-blue-600 via-indigo-600 to-slate-900">
        <div className="mx-auto max-w-7xl px-4 pb-24 pt-12 sm:px-6 lg:px-8">
          <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <p className="text-sm font-semibold uppercase tracking-widest text-blue-100/80">PulseLoad Control Room</p>
              <h1 className="mt-3 text-4xl font-semibold text-white sm:text-5xl">Operational Performance Snapshot</h1>
              <p className="mt-4 max-w-3xl text-lg text-blue-100/80">
                Monitor live load testing activity, readiness signals, and environment health so teams can launch with confidence.
              </p>
            </div>
            <div className="flex w-full gap-3 rounded-2xl border border-blue-200/20 bg-white/10 p-4 backdrop-blur lg:w-auto">
              <div>
                <p className="text-xs uppercase tracking-widest text-blue-100/70">Next release window</p>
                <p className="text-lg font-semibold text-white">Friday, 18:00 UTC</p>
                <p className="text-sm text-blue-100/70">Performance freeze begins in 2h 18m</p>
              </div>
              <div className="hidden h-12 w-px bg-blue-200/30 sm:block" aria-hidden="true" />
              <div className="hidden items-center gap-3 text-blue-100/80 sm:flex">
                <CalendarClock className="h-10 w-10" />
                <div className="text-sm">
                  <div>4 active regions</div>
                  <div>12 scheduled scenario runs</div>
                </div>
              </div>
            </div>
          </div>

          <div className="mt-10 grid gap-6 md:grid-cols-3">
            {summaryCards.map((card) => {
              const Icon = card.icon;
              return (
                <div
                  key={card.title}
                  className="relative overflow-hidden rounded-2xl border border-white/10 bg-slate-950/40 p-6 shadow-2xl shadow-blue-900/20"
                >
                  <div className={`pointer-events-none absolute inset-0 bg-gradient-to-br ${card.accent}`} aria-hidden="true" />
                  <div className="relative flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-slate-300">{card.title}</p>
                      <p className="mt-3 text-3xl font-semibold text-white">{card.value}</p>
                      <p className="mt-2 text-sm text-slate-400">{card.change}</p>
                    </div>
                    <span className="flex h-12 w-12 items-center justify-center rounded-xl border border-white/20 bg-white/10 text-white">
                      <Icon className="h-6 w-6" />
                    </span>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </div>

      <div className="mx-auto -mt-20 max-w-7xl space-y-8 px-4 pb-16 sm:px-6 lg:px-8">
        <div className="grid gap-8 lg:grid-cols-3">
          <div className="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl shadow-slate-950/40 lg:col-span-2">
            <div className="flex items-center justify-between">
              <div>
                <h2 className="text-lg font-semibold text-white">Performance envelope</h2>
                <p className="text-sm text-slate-400">Latency &amp; throughput from the last 24 hours of orchestrated tests</p>
              </div>
              <button className="inline-flex items-center gap-2 rounded-full border border-slate-700 bg-slate-800 px-3 py-1 text-xs font-medium text-slate-300 transition hover:border-slate-500 hover:text-white">
                <BarChart3 className="h-4 w-4" />
                Export data
              </button>
            </div>
            <div className="mt-6 h-72">
              <ResponsiveContainer width="100%" height="100%">
                <LineChart data={performanceTimeline}>
                  <XAxis dataKey="time" stroke="#94a3b8" tickLine={false} axisLine={false} />
                  <YAxis yAxisId="left" stroke="#94a3b8" tickLine={false} axisLine={false} width={48} />
                  <YAxis yAxisId="right" orientation="right" stroke="#94a3b8" tickLine={false} axisLine={false} width={48} />
                  <Tooltip
                    contentStyle={{
                      backgroundColor: '#020617',
                      borderRadius: '0.75rem',
                      border: '1px solid rgba(148, 163, 184, 0.3)',
                      color: '#e2e8f0',
                    }}
                  />
                  <Line yAxisId="left" type="monotone" dataKey="latency" stroke="#38bdf8" strokeWidth={3} dot={false} />
                  <Line yAxisId="right" type="monotone" dataKey="throughput" stroke="#a855f7" strokeWidth={3} dot={false} strokeDasharray="6 3" />
                </LineChart>
              </ResponsiveContainer>
            </div>
          </div>

          <div className="flex flex-col gap-4 rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl shadow-slate-950/40">
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-semibold text-white">Launch readiness</h2>
              <ArrowUpRight className="h-5 w-5 text-slate-500" />
            </div>
            <div className="space-y-4">
              {readinessChecks.map((item) => {
                const Icon = item.icon;
                return (
                  <div key={item.label} className="flex items-start gap-3 rounded-xl border border-slate-800 bg-slate-900/80 p-4">
                    <span className="mt-1 flex h-9 w-9 items-center justify-center rounded-lg bg-blue-500/20 text-blue-200">
                      <Icon className="h-5 w-5" />
                    </span>
                    <div>
                      <div className="flex items-center gap-2">
                        <p className="text-sm font-semibold text-white">{item.label}</p>
                        <span className="rounded-full bg-slate-800 px-2 py-0.5 text-xs uppercase tracking-wider text-slate-300">
                          {item.status}
                        </span>
                      </div>
                      <p className="mt-1 text-sm text-slate-400">{item.description}</p>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </div>

        <div className="grid gap-8 lg:grid-cols-3">
          <div className="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl shadow-slate-950/40 lg:col-span-2">
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-semibold text-white">Active scenario runs</h2>
              <PlayCircle className="h-5 w-5 text-slate-500" />
            </div>
            <div className="mt-6 space-y-4">
              {activeTests.map((test) => (
                <div key={test.id} className="rounded-xl border border-slate-800 bg-slate-900/80 p-4">
                  <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                      <div className="flex items-center gap-2">
                        <span className="text-xs font-semibold uppercase tracking-widest text-slate-500">{test.id}</span>
                        <span className={`rounded-full px-3 py-1 text-xs font-semibold ${statusBadgeClasses[test.status]}`}>
                          {test.status}
                        </span>
                      </div>
                      <p className="mt-1 text-lg font-semibold text-white">{test.name}</p>
                      <p className="text-sm text-slate-400">{test.owner}</p>
                    </div>
                    <div className="grid gap-4 text-sm text-slate-300 sm:grid-cols-3">
                      <div>
                        <p className="text-xs uppercase tracking-wider text-slate-500">Progress</p>
                        <div className="mt-1 flex items-center gap-3">
                          <span className="text-white">{test.progress}%</span>
                          <div className="h-1.5 w-28 overflow-hidden rounded-full bg-slate-800">
                            <div
                              className="h-full rounded-full bg-gradient-to-r from-blue-400 via-indigo-500 to-purple-500"
                              style={{ width: `${test.progress}%` }}
                            />
                          </div>
                        </div>
                      </div>
                      <div>
                        <p className="text-xs uppercase tracking-wider text-slate-500">Virtual users</p>
                        <p className="mt-1 font-semibold text-white">{test.load}</p>
                      </div>
                      <div>
                        <p className="text-xs uppercase tracking-wider text-slate-500">Throughput</p>
                        <p className="mt-1 font-semibold text-white">{test.throughput}</p>
                      </div>
                    </div>
                    <div className="text-sm text-slate-400 md:text-right">
                      <p className="text-xs uppercase tracking-wider">Started</p>
                      <p className="mt-1 font-semibold text-white">{test.startedAt}</p>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="flex flex-col gap-6">
            <div className="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl shadow-slate-950/40">
              <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold text-white">Insight digest</h2>
                <Cpu className="h-5 w-5 text-slate-500" />
              </div>
              <div className="mt-4 space-y-4">
                {insightHighlights.map((insight) => {
                  const Icon = insight.icon;
                  return (
                    <div key={insight.title} className="flex gap-3 rounded-xl border border-slate-800 bg-slate-900/80 p-4">
                      <span className="mt-1 flex h-9 w-9 items-center justify-center rounded-lg bg-purple-500/20 text-purple-200">
                        <Icon className="h-5 w-5" />
                      </span>
                      <div>
                        <p className="text-sm font-semibold text-white">{insight.title}</p>
                        <p className="mt-1 text-sm text-slate-400">{insight.detail}</p>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>

            <div className="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl shadow-slate-950/40">
              <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold text-white">Release calendar</h2>
                <CalendarClock className="h-5 w-5 text-slate-500" />
              </div>
              <div className="mt-4 space-y-4">
                {releaseCalendar.map((entry) => (
                  <div key={entry.date} className="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-900/80 p-4">
                    <div>
                      <p className="text-sm font-semibold text-white">{entry.label}</p>
                      <p className="text-xs uppercase tracking-widest text-slate-500">{entry.team}</p>
                    </div>
                    <div className="text-right">
                      <p className="text-lg font-semibold text-white">{entry.date}</p>
                      <p className="text-xs uppercase tracking-widest text-blue-200/80">{entry.type}</p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Dashboard;
