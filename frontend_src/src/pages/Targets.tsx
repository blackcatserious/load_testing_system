import React, { useMemo, useState } from 'react';
import {
  CalendarRange,
  CheckCheck,
  ChevronDown,
  FileSpreadsheet,
  Play,
  RefreshCw,
  TrendingUp,
  Users,
} from 'lucide-react';

interface TestPlan {
  id: string;
  service: string;
  environment: 'Production' | 'Staging' | 'Sandbox';
  scenario: string;
  status: 'Draft' | 'Scheduled' | 'Running' | 'Completed' | 'Paused';
  targetLoad: string;
  duration: string;
  lastRun: string;
  nextRun: string;
  owner: string;
  notes?: string;
}

const initialPlans: TestPlan[] = [
  {
    id: 'PLAN-418',
    service: 'Checkout API',
    environment: 'Production',
    scenario: 'Black Friday spike rehearsal',
    status: 'Running',
    targetLoad: '220k concurrent users',
    duration: '4h ramp / 2h hold',
    lastRun: '2025-08-02 13:40 UTC',
    nextRun: 'Today 17:00 UTC',
    owner: 'Payments Guild',
    notes: 'Monitoring sustained 250k req/min throughput',
  },
  {
    id: 'PLAN-409',
    service: 'Personalization Service',
    environment: 'Staging',
    scenario: 'Cache warm-up and failover drill',
    status: 'Scheduled',
    targetLoad: '80k concurrent users',
    duration: '90m ramp / 30m hold',
    lastRun: '2025-07-28 09:15 UTC',
    nextRun: 'Tomorrow 08:30 UTC',
    owner: 'Experience Platform',
    notes: 'Validate new auto-scaling policy for region EU-central',
  },
  {
    id: 'PLAN-392',
    service: 'Analytics Streaming',
    environment: 'Production',
    scenario: 'Long-haul soak test',
    status: 'Completed',
    targetLoad: '140k events/sec',
    duration: '24h sustained',
    lastRun: '2025-07-31 22:10 UTC',
    nextRun: 'Next window pending',
    owner: 'Data Platform',
    notes: 'No errors observed, CPU headroom at 28%',
  },
  {
    id: 'PLAN-377',
    service: 'Mobile Gateway',
    environment: 'Sandbox',
    scenario: 'New device onboarding flow',
    status: 'Draft',
    targetLoad: '25k devices / min',
    duration: '45m ramp / 15m hold',
    lastRun: 'Not yet executed',
    nextRun: 'Awaiting approval',
    owner: 'Mobile Experience',
    notes: 'Requires updated telemetry hooks before promotion',
  },
];

const statusStyles: Record<TestPlan['status'], string> = {
  Draft: 'bg-slate-800 text-slate-200 border border-slate-700',
  Scheduled: 'bg-blue-500/20 text-blue-100 border border-blue-400/30',
  Running: 'bg-emerald-500/15 text-emerald-100 border border-emerald-400/30',
  Completed: 'bg-purple-500/15 text-purple-100 border border-purple-400/30',
  Paused: 'bg-amber-500/15 text-amber-100 border border-amber-400/30',
};

const environments: Array<'All' | TestPlan['environment']> = ['All', 'Production', 'Staging', 'Sandbox'];

const scenarioCatalog = [
  {
    name: 'Holiday surge preparedness',
    description: 'Rapid 10x ramp with CDN failover validation and real user monitoring hooks.',
  },
  {
    name: 'Soak test with rotating user journeys',
    description: '24h sustained workload across browse, search, and purchase funnels.',
  },
  {
    name: 'Regional edge resilience drill',
    description: 'Simulates region isolation, reroutes traffic, validates scaling guards.',
  },
];

const Targets: React.FC = () => {
  const [plans, setPlans] = useState<TestPlan[]>(initialPlans);
  const [selectedEnvironment, setSelectedEnvironment] = useState<(typeof environments)[number]>('All');
  const [planForm, setPlanForm] = useState({
    service: '',
    environment: 'Production' as TestPlan['environment'],
    scenario: '',
    targetLoad: '',
    duration: '',
    notes: '',
  });

  const filteredPlans = useMemo(() => {
    if (selectedEnvironment === 'All') return plans;
    return plans.filter((plan) => plan.environment === selectedEnvironment);
  }, [plans, selectedEnvironment]);

  const resetForm = () => {
    setPlanForm({ service: '', environment: 'Production', scenario: '', targetLoad: '', duration: '', notes: '' });
  };

  const handleCreatePlan = () => {
    if (!planForm.service || !planForm.scenario || !planForm.targetLoad || !planForm.duration) {
      return;
    }

    const newPlan: TestPlan = {
      id: `PLAN-${Math.floor(Math.random() * 900 + 100)}`,
      service: planForm.service,
      environment: planForm.environment,
      scenario: planForm.scenario,
      status: 'Draft',
      targetLoad: planForm.targetLoad,
      duration: planForm.duration,
      lastRun: 'Not yet executed',
      nextRun: 'Schedule pending',
      owner: 'Unassigned',
      notes: planForm.notes,
    };

    setPlans((prev) => [newPlan, ...prev]);
    resetForm();
  };

  const handleStatusUpdate = (id: string, status: TestPlan['status']) => {
    setPlans((prev) => prev.map((plan) => (plan.id === id ? { ...plan, status } : plan)));
  };

  return (
    <div className="relative">
      <div className="bg-gradient-to-br from-slate-900 via-indigo-700 to-blue-600">
        <div className="mx-auto max-w-7xl px-4 pb-24 pt-12 sm:px-6 lg:px-8">
          <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <p className="text-sm font-semibold uppercase tracking-widest text-blue-100/80">Plan and Orchestrate</p>
              <h1 className="mt-3 text-4xl font-semibold text-white sm:text-5xl">Test plan library</h1>
              <p className="mt-4 max-w-3xl text-lg text-blue-100/80">
                Curate reusable load test scenarios, capture requirements, and keep every environment ready for the next launch.
              </p>
            </div>
            <div className="flex gap-3 rounded-2xl border border-blue-200/20 bg-white/10 p-4 backdrop-blur">
              <div>
                <p className="text-xs uppercase tracking-widest text-blue-100/70">Active plans</p>
                <p className="text-lg font-semibold text-white">{plans.filter((plan) => plan.status !== 'Completed').length}</p>
                <p className="text-sm text-blue-100/70">Across {new Set(plans.map((plan) => plan.environment)).size} environments</p>
              </div>
              <div className="hidden h-12 w-px bg-blue-200/30 sm:block" aria-hidden="true" />
              <div className="hidden items-center gap-3 text-blue-100/80 sm:flex">
                <Users className="h-10 w-10" />
                <div className="text-sm">
                  <div>Coordinated by Load Engineering</div>
                  <div>Weekly sync every Tuesday</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="mx-auto -mt-20 max-w-7xl space-y-10 px-4 pb-16 sm:px-6 lg:px-8">
        <section className="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl shadow-slate-950/40">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <h2 className="text-lg font-semibold text-white">Create a new test plan</h2>
              <p className="text-sm text-slate-400">Document the scenario, load profile, and any guardrails to share with delivery teams.</p>
            </div>
            <button
              onClick={resetForm}
              className="inline-flex items-center gap-2 rounded-full border border-slate-700 bg-slate-800 px-3 py-1 text-xs font-medium text-slate-300 transition hover:border-slate-500 hover:text-white"
            >
              <RefreshCw className="h-4 w-4" />
              Reset form
            </button>
          </div>

          <div className="mt-6 grid gap-6 lg:grid-cols-3">
            <div className="lg:col-span-2 space-y-4">
              <div>
                <label className="text-xs font-semibold uppercase tracking-wider text-slate-400">Service / capability</label>
                <input
                  type="text"
                  value={planForm.service}
                  onChange={(event) => setPlanForm((prev) => ({ ...prev, service: event.target.value }))}
                  placeholder="e.g. Checkout API"
                  className="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950/60 px-4 py-3 text-sm text-white placeholder:text-slate-500 focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-400"
                />
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                <div>
                  <label className="text-xs font-semibold uppercase tracking-wider text-slate-400">Environment</label>
                  <div className="relative mt-2">
                    <select
                      value={planForm.environment}
                      onChange={(event) =>
                        setPlanForm((prev) => ({ ...prev, environment: event.target.value as TestPlan['environment'] }))
                      }
                      className="w-full appearance-none rounded-xl border border-slate-700 bg-slate-950/60 px-4 py-3 pr-10 text-sm text-white focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-400"
                    >
                      <option value="Production">Production</option>
                      <option value="Staging">Staging</option>
                      <option value="Sandbox">Sandbox</option>
                    </select>
                    <ChevronDown className="pointer-events-none absolute right-3 top-3.5 h-4 w-4 text-slate-500" />
                  </div>
                </div>
                <div>
                  <label className="text-xs font-semibold uppercase tracking-wider text-slate-400">Scenario summary</label>
                  <input
                    type="text"
                    value={planForm.scenario}
                    onChange={(event) => setPlanForm((prev) => ({ ...prev, scenario: event.target.value }))}
                    placeholder="Describe the business flow under test"
                    className="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950/60 px-4 py-3 text-sm text-white placeholder:text-slate-500 focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-400"
                  />
                </div>
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                <div>
                  <label className="text-xs font-semibold uppercase tracking-wider text-slate-400">Target load</label>
                  <input
                    type="text"
                    value={planForm.targetLoad}
                    onChange={(event) => setPlanForm((prev) => ({ ...prev, targetLoad: event.target.value }))}
                    placeholder="e.g. 150k concurrent users"
                    className="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950/60 px-4 py-3 text-sm text-white placeholder:text-slate-500 focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-400"
                  />
                </div>
                <div>
                  <label className="text-xs font-semibold uppercase tracking-wider text-slate-400">Duration &amp; ramp</label>
                  <input
                    type="text"
                    value={planForm.duration}
                    onChange={(event) => setPlanForm((prev) => ({ ...prev, duration: event.target.value }))}
                    placeholder="e.g. 30m ramp / 60m hold"
                    className="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950/60 px-4 py-3 text-sm text-white placeholder:text-slate-500 focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-400"
                  />
                </div>
              </div>
            </div>

            <div className="space-y-4">
              <div>
                <label className="text-xs font-semibold uppercase tracking-wider text-slate-400">Notes &amp; guardrails</label>
                <textarea
                  rows={5}
                  value={planForm.notes}
                  onChange={(event) => setPlanForm((prev) => ({ ...prev, notes: event.target.value }))}
                  placeholder="Document any dependencies, freeze windows, or rollback plans."
                  className="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950/60 px-4 py-3 text-sm text-white placeholder:text-slate-500 focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-400"
                />
              </div>
              <button
                onClick={handleCreatePlan}
                className="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-500 via-indigo-500 to-purple-500 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-900/40 transition hover:from-blue-400 hover:via-indigo-500 hover:to-purple-500"
              >
                <Play className="h-4 w-4" />
                Add to library
              </button>
              <p className="text-xs text-slate-400">
                Plans start as drafts. Assign an owner and schedule when ready for execution.
              </p>
            </div>
          </div>
        </section>

        <section className="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl shadow-slate-950/40">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <h2 className="text-lg font-semibold text-white">Scenario catalog</h2>
              <p className="text-sm text-slate-400">Kickstart planning with proven templates from the load engineering playbook.</p>
            </div>
            <button className="inline-flex items-center gap-2 rounded-full border border-slate-700 bg-slate-800 px-3 py-1 text-xs font-medium text-slate-300 transition hover:border-slate-500 hover:text-white">
              <FileSpreadsheet className="h-4 w-4" />
              Download template pack
            </button>
          </div>
          <div className="mt-6 grid gap-4 md:grid-cols-3">
            {scenarioCatalog.map((scenario) => (
              <div key={scenario.name} className="rounded-xl border border-slate-800 bg-slate-900/80 p-4">
                <p className="text-sm font-semibold text-white">{scenario.name}</p>
                <p className="mt-2 text-sm text-slate-400">{scenario.description}</p>
                <button className="mt-4 inline-flex items-center gap-2 text-xs font-semibold text-blue-200 transition hover:text-white">
                  <TrendingUp className="h-4 w-4" />
                  Apply scenario
                </button>
              </div>
            ))}
          </div>
        </section>

        <section className="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl shadow-slate-950/40">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <h2 className="text-lg font-semibold text-white">Plan inventory</h2>
              <p className="text-sm text-slate-400">Filter by environment to see what is ready, running, or needs an owner.</p>
            </div>
            <div className="flex flex-wrap gap-2">
              {environments.map((environment) => (
                <button
                  key={environment}
                  onClick={() => setSelectedEnvironment(environment)}
                  className={`rounded-full px-4 py-2 text-xs font-semibold transition ${
                    selectedEnvironment === environment
                      ? 'bg-blue-500 text-white shadow-lg shadow-blue-500/30'
                      : 'border border-slate-700 bg-slate-800 text-slate-300 hover:border-slate-500 hover:text-white'
                  }`}
                >
                  {environment}
                </button>
              ))}
            </div>
          </div>

          <div className="mt-6 overflow-x-auto">
            <table className="min-w-full divide-y divide-slate-800 text-sm">
              <thead className="bg-slate-900">
                <tr className="text-left text-xs font-semibold uppercase tracking-wider text-slate-400">
                  <th className="px-4 py-3">Plan</th>
                  <th className="px-4 py-3">Service</th>
                  <th className="px-4 py-3">Environment</th>
                  <th className="px-4 py-3">Scenario</th>
                  <th className="px-4 py-3">Load profile</th>
                  <th className="px-4 py-3">Window</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3">Owner</th>
                  <th className="px-4 py-3">Next milestone</th>
                  <th className="px-4 py-3">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800">
                {filteredPlans.length === 0 ? (
                  <tr>
                    <td colSpan={10} className="px-4 py-6 text-center text-slate-400">
                      No plans found for this environment.
                    </td>
                  </tr>
                ) : (
                  filteredPlans.map((plan) => (
                    <tr key={plan.id} className="text-slate-200">
                      <td className="px-4 py-4">
                        <div className="flex flex-col">
                          <span className="text-xs font-semibold uppercase tracking-widest text-slate-500">{plan.id}</span>
                          <span className="text-sm font-semibold text-white">{plan.scenario}</span>
                        </div>
                      </td>
                      <td className="px-4 py-4">{plan.service}</td>
                      <td className="px-4 py-4">
                        <span className="inline-flex items-center gap-2 rounded-full border border-slate-700 bg-slate-800 px-3 py-1 text-xs font-semibold text-slate-300">
                          <CalendarRange className="h-4 w-4" />
                          {plan.environment}
                        </span>
                      </td>
                      <td className="px-4 py-4 text-slate-300">{plan.scenario}</td>
                      <td className="px-4 py-4 text-slate-300">{plan.targetLoad}</td>
                      <td className="px-4 py-4 text-slate-300">{plan.duration}</td>
                      <td className="px-4 py-4">
                        <span className={`inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold ${statusStyles[plan.status]}`}>
                          <CheckCheck className="h-4 w-4" />
                          {plan.status}
                        </span>
                      </td>
                      <td className="px-4 py-4 text-slate-300">{plan.owner}</td>
                      <td className="px-4 py-4 text-slate-300">{plan.nextRun}</td>
                      <td className="px-4 py-4">
                        <div className="flex flex-wrap gap-2">
                          <button
                            onClick={() => handleStatusUpdate(plan.id, 'Scheduled')}
                            className="rounded-full border border-slate-700 px-3 py-1 text-xs font-semibold text-slate-300 transition hover:border-slate-500 hover:text-white"
                          >
                            Schedule
                          </button>
                          <button
                            onClick={() => handleStatusUpdate(plan.id, 'Running')}
                            className="rounded-full border border-emerald-500/40 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-100 transition hover:bg-emerald-500/20"
                          >
                            Start run
                          </button>
                          <button
                            onClick={() => handleStatusUpdate(plan.id, 'Completed')}
                            className="rounded-full border border-purple-500/40 bg-purple-500/10 px-3 py-1 text-xs font-semibold text-purple-100 transition hover:bg-purple-500/20"
                          >
                            Mark done
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </div>
  );
};

export default Targets;
