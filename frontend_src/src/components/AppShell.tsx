import React, { useState } from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import {
  Activity,
  BarChart3,
  FileText,
  LayoutDashboard,
  Menu,
  ShieldCheck,
  Target,
  X,
} from 'lucide-react';

interface AppShellProps {
  children: React.ReactNode;
}

const navigation = [
  { to: '/dashboard', label: 'Dashboard', icon: LayoutDashboard },
  { to: '/targets', label: 'Targets', icon: Target },
  { to: '/reports', label: 'Reports', icon: FileText },
  { to: '/runs', label: 'Live Runs', icon: Activity },
];

export const AppShell: React.FC<AppShellProps> = ({ children }) => {
  const location = useLocation();
  const [mobileOpen, setMobileOpen] = useState(false);

  const toggleMobile = () => setMobileOpen((open) => !open);
  const closeMobile = () => setMobileOpen(false);

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100">
      <div className="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 opacity-95" />
        <div className="absolute -top-24 left-1/2 h-72 w-[36rem] -translate-x-1/2 rounded-full bg-blue-500/20 blur-3xl" />
        <div className="absolute -bottom-32 right-12 h-64 w-64 rounded-full bg-indigo-500/10 blur-3xl" />
      </div>

      <div className="relative mx-auto flex min-h-screen w-full max-w-[1440px] flex-col md:flex-row">
        <aside
          className={`fixed inset-y-0 left-0 z-40 flex w-72 flex-col border-r border-white/5 bg-slate-950/80 backdrop-blur-md transition-transform duration-200 md:static md:translate-x-0 ${
            mobileOpen ? 'translate-x-0' : '-translate-x-full'
          }`}
        >
          <div className="flex items-center justify-between px-6 py-5 border-b border-white/5">
            <div className="flex items-center gap-3">
              <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-500/20 text-blue-300">
                <ShieldCheck className="h-6 w-6" />
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.3em] text-blue-300/90">Domain Control</p>
                <p className="text-lg font-semibold text-white">Nightmare Console</p>
              </div>
            </div>
            <button
              type="button"
              onClick={toggleMobile}
              className="md:hidden rounded-full border border-white/10 p-2 text-slate-300 hover:bg-white/10"
              aria-label="Close menu"
            >
              <X className="h-4 w-4" />
            </button>
          </div>

          <nav className="flex-1 space-y-1 px-4 py-6">
            {navigation.map((item) => {
              const Icon = item.icon;
              const isActive = location.pathname.startsWith(item.to);
              return (
                <NavLink
                  key={item.to}
                  to={item.to}
                  onClick={closeMobile}
                  className={`group flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition-colors ${
                    isActive
                      ? 'bg-gradient-to-r from-blue-500/20 via-blue-500/10 to-transparent text-white'
                      : 'text-slate-300 hover:text-white hover:bg-white/5'
                  }`}
                >
                  <span
                    className={`flex h-9 w-9 items-center justify-center rounded-lg border text-slate-200 transition-colors ${
                      isActive ? 'border-blue-500/40 bg-blue-500/10 text-blue-200' : 'border-white/10'
                    }`}
                  >
                    <Icon className="h-4 w-4" />
                  </span>
                  <span>{item.label}</span>
                </NavLink>
              );
            })}
          </nav>

          <div className="px-6 pb-8">
            <div className="rounded-2xl border border-white/10 bg-slate-900/70 p-4">
              <p className="text-xs uppercase tracking-widest text-slate-400">Domain Health</p>
              <div className="mt-3 flex items-center gap-2 text-sm text-slate-200">
                <span className="inline-flex items-center gap-2 rounded-full bg-emerald-500/10 px-3 py-1 text-emerald-300">
                  <span className="h-2 w-2 rounded-full bg-emerald-400" />
                  Stable
                </span>
                <span className="text-slate-400">nightmare.stresser</span>
              </div>
              <p className="mt-3 text-xs text-slate-400">
                Live orchestration and proxy pools synchronised across all regions with adaptive mitigation enabled.
              </p>
            </div>
          </div>
        </aside>

        <div className="flex flex-1 flex-col md:pl-72">
          <header className="sticky top-0 z-30 border-b border-white/5 bg-slate-950/60 backdrop-blur">
            <div className="flex items-center justify-between px-6 py-4">
              <div className="flex items-center gap-3">
                <button
                  type="button"
                  onClick={toggleMobile}
                  className="md:hidden rounded-full border border-white/10 p-2 text-slate-300 hover:bg-white/10"
                  aria-label="Toggle navigation"
                >
                  <Menu className="h-4 w-4" />
                </button>
                <div>
                  <p className="text-xs uppercase tracking-[0.35em] text-blue-300/80">Operational Overview</p>
                  <h2 className="text-xl font-semibold text-white">Global Domain Command Center</h2>
                </div>
              </div>

              <div className="flex items-center gap-3">
                <div className="hidden sm:flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs uppercase tracking-widest text-slate-300">
                  <BarChart3 className="h-3.5 w-3.5 text-blue-300" />
                  Live Telemetry
                </div>
                <button
                  type="button"
                  className="rounded-full bg-blue-500/90 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-blue-500/20 transition hover:bg-blue-500"
                >
                  Launch Control
                </button>
              </div>
            </div>
          </header>

          <main className="flex-1 px-6 py-8">
            <div className="mx-auto max-w-6xl space-y-8 pb-10">
              {children}
            </div>
          </main>
        </div>
      </div>
    </div>
  );
};

export default AppShell;
