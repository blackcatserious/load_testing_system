import React from 'react';

interface StatusPillProps {
  status: 'draft' | 'scheduled' | 'ready' | 'preparing' | 'running' | 'pausing' | 'completed' | 'failed';
}

const statusStyles: Record<StatusPillProps['status'], string> = {
  draft: 'bg-slate-800 text-slate-200',
  scheduled: 'bg-amber-500/20 text-amber-300 border border-amber-500/40',
  ready: 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40',
  preparing: 'bg-sky-500/20 text-sky-300 border border-sky-500/40',
  running: 'bg-indigo-500/20 text-indigo-300 border border-indigo-500/40',
  pausing: 'bg-purple-500/20 text-purple-300 border border-purple-500/40',
  completed: 'bg-emerald-500/20 text-emerald-200 border border-emerald-500/30',
  failed: 'bg-rose-500/20 text-rose-200 border border-rose-500/40',
};

const StatusPill: React.FC<StatusPillProps> = ({ status }) => {
  return (
    <span className={`rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide ${statusStyles[status]}`}>
      {status}
    </span>
  );
};

export default StatusPill;
