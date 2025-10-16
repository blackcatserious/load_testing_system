import React from 'react';
import { Download, FileText, Calendar, Database, Shield } from 'lucide-react';
import { useReports } from '../api/hooks';
import type { ReportSummary } from '../api/types';

const describeReport = (report: ReportSummary): string => {
  if (report.summary) {
    const { total_requests, success_rate, avg_response_time } = report.summary;
    const parts: string[] = [];
    if (typeof total_requests === 'number') {
      parts.push(`Requests ${total_requests.toLocaleString()}`);
    }
    if (typeof success_rate === 'number') {
      parts.push(`Success ${success_rate}%`);
    }
    if (typeof avg_response_time === 'number') {
      parts.push(`Avg ${avg_response_time}ms`);
    }
    if (parts.length > 0) {
      return parts.join(' · ');
    }
  }

  if (report.run_info) {
    return `Target ${report.run_info.target_url}`;
  }

  return 'Domain export payload';
};

const Reports: React.FC = () => {
  const { data: reports, loading, error, downloadReport } = useReports();

  const handleDownload = async (filename: string) => {
    try {
      await downloadReport(filename);
    } catch (err) {
      console.error('Failed to download report:', err);
    }
  };

  const formatFileSize = (bytes: number) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`;
  };

  const formatDate = (dateString: string) => new Date(dateString).toLocaleString();

  if (loading) {
    return (
      <div className="flex min-h-[320px] flex-col items-center justify-center gap-4 rounded-3xl border border-white/10 bg-slate-900/70 p-10 text-white/70">
        <span className="h-12 w-12 animate-spin rounded-full border-4 border-white/10 border-t-blue-400" />
        Fetching reports from the domain orchestrator...
      </div>
    );
  }

  if (error) {
    return (
      <div className="rounded-3xl border border-rose-500/30 bg-rose-500/10 p-6 text-rose-100">
        <h3 className="text-lg font-semibold">Error loading reports</h3>
        <p className="mt-2 text-sm text-rose-100/80">{error}</p>
      </div>
    );
  }

  return (
    <div className="space-y-8 text-slate-100">
      <header className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs uppercase tracking-[0.35em] text-slate-300">
            <Shield className="h-3.5 w-3.5 text-blue-300" />
            Intelligence Archive
          </div>
          <h1 className="mt-4 text-3xl font-semibold text-white">Domain telemetry reports</h1>
          <p className="mt-2 max-w-2xl text-sm text-white/70">
            Explore exportable intelligence captured from every run: mitigation behaviour, proxy pools, and success
            windows preserved for downstream analysis.
          </p>
        </div>
        <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm text-white/80">
          <Database className="h-4 w-4 text-blue-300" />
          {reports?.length || 0} archives available
        </div>
      </header>

      <section className="rounded-3xl border border-white/10 bg-slate-900/70 shadow-lg shadow-black/30">
        <div className="flex items-center justify-between gap-3 border-b border-white/10 px-6 py-5">
          <div>
            <h2 className="text-xl font-semibold text-white">Available exports</h2>
            <p className="text-sm text-white/70">Download JSON or CSV bundles for your orchestration pipelines.</p>
          </div>
          <div className="hidden sm:inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs uppercase tracking-widest text-white/60">
            <FileText className="h-3.5 w-3.5 text-blue-300" />
            Structured Payloads
          </div>
        </div>

        {!reports || reports.length === 0 ? (
          <div className="px-6 py-16 text-center text-white/60">
            <FileText className="mx-auto h-12 w-12 text-white/30" />
            <h3 className="mt-4 text-lg font-semibold text-white">No reports generated yet</h3>
            <p className="mt-2 text-sm text-white/60">Execute a run to populate the intelligence archive.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-white/10 text-sm">
              <thead>
                <tr className="bg-white/5 text-left text-xs uppercase tracking-widest text-white/50">
                  <th className="px-6 py-3">Report</th>
                  <th className="px-6 py-3">Type</th>
                  <th className="px-6 py-3">Size</th>
                  <th className="px-6 py-3">Created</th>
                  <th className="px-6 py-3">Run Info</th>
                  <th className="px-6 py-3">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/5">
                {reports.map((report) => (
                  <tr key={report.filename} className="hover:bg-white/5">
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-blue-200">
                          <FileText className="h-5 w-5" />
                        </div>
                        <div>
                          <p className="font-semibold text-white">{report.filename}</p>
                          <p className="text-xs text-white/60">{describeReport(report)}</p>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <span
                        className={`inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold ${
                          report.type === 'json'
                            ? 'border border-blue-500/40 bg-blue-500/10 text-blue-100'
                            : 'border border-emerald-500/40 bg-emerald-500/10 text-emerald-100'
                        }`}
                      >
                        {report.type.toUpperCase()}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-white/80">{formatFileSize(report.size)}</td>
                    <td className="px-6 py-4 text-white/80">
                      <div className="flex items-center gap-2">
                        <Calendar className="h-4 w-4 text-white/40" />
                        {formatDate(report.created_at)}
                      </div>
                    </td>
                    <td className="px-6 py-4 text-white/80">
                      {report.run_info ? (
                        <div className="space-y-1">
                          <div className="text-sm font-semibold text-white">Run: {report.run_id}</div>
                          <div className="text-xs text-white/60">{report.run_info.target_url}</div>
                          {report.run_info.finished_at && (
                            <div className="text-xs text-white/50">
                              Finished: {formatDate(report.run_info.finished_at)}
                            </div>
                          )}
                        </div>
                      ) : (
                        <span className="text-xs text-white/50">No run metadata</span>
                      )}
                    </td>
                    <td className="px-6 py-4">
                      <button
                        onClick={() => handleDownload(report.filename)}
                        className="inline-flex items-center gap-2 rounded-full border border-blue-500/40 bg-blue-500/10 px-4 py-2 text-xs font-semibold text-blue-100 transition hover:border-blue-400/60 hover:bg-blue-500/15"
                      >
                        <Download className="h-4 w-4" />
                        Download
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  );
};

export default Reports;
