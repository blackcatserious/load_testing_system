import React from 'react';
import { Loader2 } from 'lucide-react';

interface LoadingStateProps {
  label?: string;
}

const LoadingState: React.FC<LoadingStateProps> = ({ label = 'Loading telemetry' }) => {
  return (
    <div className="flex min-h-[320px] flex-col items-center justify-center gap-3 rounded-3xl border border-slate-800/80 bg-slate-900/50 text-slate-300">
      <Loader2 className="h-10 w-10 animate-spin text-indigo-400" />
      <span className="text-sm font-medium uppercase tracking-[0.35em] text-slate-400">{label}</span>
    </div>
  );
};

export default LoadingState;
