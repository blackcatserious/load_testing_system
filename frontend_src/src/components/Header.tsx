import React from 'react';
import { NavLink } from 'react-router-dom';
import { Activity, LifeBuoy, Rocket } from 'lucide-react';

const navigation = [
  { label: 'Mission Control', to: '/dashboard' },
  { label: 'Test Plans', to: '/plans' },
  { label: 'Live Runs', to: '/runs' },
  { label: 'Reports', to: '/reports' },
];

const Header: React.FC = () => {
  return (
    <header className="sticky top-0 z-40 border-b border-slate-800/80 bg-slate-950/90 backdrop-blur">
      <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
        <div className="flex items-center gap-3">
          <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 via-blue-500 to-cyan-400 text-white shadow-xl shadow-blue-500/30">
            <Activity className="h-5 w-5" />
          </span>
          <div>
            <div className="text-xs font-semibold uppercase tracking-[0.35em] text-slate-400">PulseLoad Platform</div>
            <div className="text-xl font-semibold text-white">Control Center</div>
          </div>
        </div>

        <nav className="hidden items-center gap-1 rounded-full border border-slate-800/80 bg-slate-900/60 px-1.5 py-1.5 text-sm font-medium text-slate-300 shadow-inner md:flex">
          {navigation.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              className={({ isActive }) =>
                `rounded-full px-4 py-2 transition-colors duration-200 ${
                  isActive
                    ? 'bg-indigo-500 text-white shadow-lg shadow-indigo-500/40'
                    : 'hover:bg-slate-800/80 hover:text-white'
                }`
              }
            >
              {item.label}
            </NavLink>
          ))}
        </nav>

        <div className="flex items-center gap-3">
          <button className="hidden items-center gap-2 rounded-full border border-slate-700/60 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:border-indigo-400/80 hover:text-white md:flex">
            <LifeBuoy className="h-4 w-4" />
            Runbook
          </button>
          <button className="flex items-center gap-2 rounded-full bg-gradient-to-r from-indigo-500 via-blue-500 to-cyan-400 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:brightness-105">
            <Rocket className="h-4 w-4" />
            Launch Test
          </button>
        </div>
      </div>
    </header>
  );
};

export default Header;
