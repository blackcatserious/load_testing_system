import React from 'react';
import { TrendingUp } from 'lucide-react';

interface MetricCardProps {
  label: string;
  value: string;
  unit?: string;
  caption?: string;
  delta?: string;
}

const MetricCard: React.FC<MetricCardProps> = ({ label, value, unit, caption, delta }) => {
  return (
    <div className="flex flex-col justify-between rounded-3xl border border-slate-800/80 bg-slate-900/50 p-6 shadow-inner shadow-slate-900/40">
      <div className="text-xs font-semibold uppercase tracking-[0.35em] text-slate-400">{label}</div>
      <div className="mt-3 flex items-baseline gap-2 text-4xl font-semibold text-white">
        <span>{value}</span>
        {unit ? <span className="text-lg font-normal text-slate-400">{unit}</span> : null}
      </div>
      <div className="mt-4 flex items-center gap-2 text-xs text-slate-400">
        <TrendingUp className="h-4 w-4 text-indigo-400" />
        <span>{caption}</span>
        {delta ? <span className="font-semibold text-indigo-300">{delta}</span> : null}
      </div>
    </div>
  );
};

export default MetricCard;
