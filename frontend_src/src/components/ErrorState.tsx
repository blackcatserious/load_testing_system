import React from 'react';
import { AlertTriangle } from 'lucide-react';

interface ErrorStateProps {
  title?: string;
  description?: string;
}

const ErrorState: React.FC<ErrorStateProps> = ({
  title = 'Telemetry Offline',
  description = 'We were unable to reach the control plane API. Retry or check your network.',
}) => {
  return (
    <div className="flex min-h-[320px] flex-col items-center justify-center gap-3 rounded-3xl border border-rose-500/40 bg-rose-500/10 text-rose-200">
      <AlertTriangle className="h-10 w-10" />
      <div className="text-lg font-semibold">{title}</div>
      <p className="max-w-md text-center text-sm text-rose-100/80">{description}</p>
    </div>
  );
};

export default ErrorState;
