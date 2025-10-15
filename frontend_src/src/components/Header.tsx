import React from 'react';
import { NavLink } from 'react-router-dom';
import { Activity } from 'lucide-react';

const navigation = [
  { label: 'Dashboard', to: '/dashboard' },
  { label: 'Test Plans', to: '/targets' },
  { label: 'Reports', to: '/reports' },
];

const Header: React.FC = () => {
  return (
    <header className="sticky top-0 z-40 border-b border-slate-800/80 bg-slate-950/90 backdrop-blur">
      <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
        <div className="flex items-center gap-3">
          <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-cyan-400 text-white shadow-lg">
            <Activity className="h-5 w-5" />
          </span>
          <div>
            <div className="text-sm font-medium uppercase tracking-widest text-slate-400">Load Testing Control Center</div>
            <div className="text-xl font-semibold text-white">PulseLoad</div>
          </div>
        </div>

        <nav className="hidden items-center gap-1 rounded-full border border-slate-800 bg-slate-900/70 px-1.5 py-1.5 text-sm font-medium text-slate-300 shadow-inner md:flex">
          {navigation.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              className={({ isActive }) =>
                `rounded-full px-4 py-2 transition-colors duration-200 ${
                  isActive
                    ? 'bg-blue-500 text-white shadow-lg shadow-blue-500/30'
                    : 'hover:text-white'
                }`
              }
            >
              {item.label}
            </NavLink>
          ))}
        </nav>

        <button className="rounded-full border border-blue-400/40 bg-blue-500/20 px-4 py-2 text-sm font-medium text-blue-100 transition hover:bg-blue-500/30">
          New Test Run
        </button>
      </div>
    </header>
  );
};

export default Header;
