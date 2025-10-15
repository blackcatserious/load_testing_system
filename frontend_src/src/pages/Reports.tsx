import React from 'react';
import { useReports } from '../api/hooks';
import type { Report } from '../api/types';
import { Download, FileText, Calendar, Database, FolderOpen } from 'lucide-react';

const Reports: React.FC = () => {
  const { data: reports, loading, error, downloadReport } = useReports();

  if (loading) {
    return (
      <div className="flex min-h-[60vh] items-center justify-center">
        <div className="h-24 w-24 animate-spin rounded-full border-4 border-blue-500/40 border-t-blue-400" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex min-h-[60vh] items-center justify-center px-4">
        <div className="max-w-md rounded-2xl border border-rose-500/40 bg-rose-500/10 p-6 text-rose-100 shadow-lg shadow-rose-900/40">
          <div className="flex items-center gap-3 text-sm">
            <span className="flex h-10 w-10 items-center justify-center rounded-full bg-rose-500/30 text-rose-100">⚠️</span>
            <div>
              <p className="font-semibold">Unable to load reports</p>
              <p className="mt-1 text-rose-200/80">{error}</p>
            </div>
          </div>
        </div>
      </div>
    );
  }

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

  const formatSummary = (report: Report) => {
    if (!report?.summary) {
      return 'Load test export';
    }

    const parts: string[] = [];
    if (typeof report.summary.total_requests === 'number') {
      parts.push(`${report.summary.total_requests.toLocaleString()} requests`);
    }
    if (typeof report.summary.success_rate === 'number') {
      parts.push(`${report.summary.success_rate.toFixed(1)}% success`);
    }
    if (typeof report.summary.avg_response_time === 'number') {
      parts.push(`${Math.round(report.summary.avg_response_time)} ms avg response`);
    }

    return parts.length > 0 ? parts.join(' • ') : 'Load test export';
  };

  return (
    <div className="relative">
      <div className="bg-gradient-to-br from-slate-900 via-purple-700 to-blue-600">
        <div className="mx-auto max-w-7xl px-4 pb-20 pt-12 sm:px-6 lg:px-8">
          <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <p className="text-sm font-semibold uppercase tracking-widest text-purple-100/80">Insights &amp; Evidence</p>
              <h1 className="mt-3 text-4xl font-semibold text-white sm:text-5xl">Report archive</h1>
              <p className="mt-4 max-w-3xl text-lg text-purple-100/80">
                Access full telemetry exports, executive summaries, and evidence packs captured from recent load tests.
              </p>
            </div>
            <div className="flex gap-3 rounded-2xl border border-purple-200/20 bg-white/10 p-4 backdrop-blur">
              <div>
                <p className="text-xs uppercase tracking-widest text-purple-100/70">Available reports</p>
                <p className="text-lg font-semibold text-white">{reports?.length || 0}</p>
                <p className="text-sm text-purple-100/70">Updated automatically after each scheduled run</p>
              </div>
              <div className="hidden h-12 w-px bg-purple-200/30 sm:block" aria-hidden="true" />
              <div className="hidden items-center gap-3 text-purple-100/80 sm:flex">
                <FolderOpen className="h-10 w-10" />
                <div className="text-sm">
                  <div>Evidence retained for 90 days</div>
                  <div>Long-term archive in cold storage</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="mx-auto -mt-16 max-w-7xl space-y-8 px-4 pb-16 sm:px-6 lg:px-8">
        <div className="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-xl shadow-slate-950/40">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <h2 className="text-lg font-semibold text-white">Available reports</h2>
              <p className="text-sm text-slate-400">Download raw data exports, visual summaries, and compliance evidence.</p>
            </div>
            <div className="flex items-center gap-3 rounded-full border border-slate-800 bg-slate-950/70 px-4 py-2 text-xs font-semibold text-slate-300">
              <Database className="h-4 w-4" />
              Stored securely in object storage
            </div>
          </div>

          {!reports || reports.length === 0 ? (
            <div className="mt-12 text-center">
              <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl border border-slate-800 bg-slate-900 text-slate-400">
                <FileText className="h-6 w-6" />
              </div>
              <h3 className="mt-4 text-lg font-semibold text-white">No reports available</h3>
              <p className="mt-2 text-sm text-slate-400">Run a load test to generate your first report package.</p>
            </div>
          ) : (
            <div className="mt-8 overflow-hidden rounded-2xl border border-slate-800">
              <table className="min-w-full divide-y divide-slate-800 text-sm">
                <thead className="bg-slate-900/80">
                  <tr className="text-left text-xs font-semibold uppercase tracking-widest text-slate-400">
                    <th className="px-6 py-3">Report</th>
                    <th className="px-6 py-3">Type</th>
                    <th className="px-6 py-3">Size</th>
                    <th className="px-6 py-3">Created</th>
                    <th className="px-6 py-3">Run info</th>
                    <th className="px-6 py-3">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800 bg-slate-950/40">
                  {reports.map((report) => (
                    <tr key={report.filename} className="text-slate-200">
                      <td className="px-6 py-4">
                        <div className="flex items-center gap-3">
                          <span className="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-800 bg-slate-900">
                            <FileText className="h-5 w-5 text-slate-400" />
                          </span>
                          <div>
                            <p className="text-sm font-semibold text-white">{report.filename}</p>
                            <p className="text-xs text-slate-400">{formatSummary(report)}</p>
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <span
                          className={`inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold ${
                            report.type === 'json'
                              ? 'border border-blue-400/30 bg-blue-500/15 text-blue-100'
                              : 'border border-emerald-400/30 bg-emerald-500/15 text-emerald-100'
                          }`}
                        >
                          {report.type.toUpperCase()}
                        </span>
                      </td>
                      <td className="px-6 py-4 text-slate-300">{formatFileSize(report.size)}</td>
                      <td className="px-6 py-4 text-slate-300">
                        <div className="flex items-center gap-2">
                          <Calendar className="h-4 w-4 text-slate-500" />
                          {formatDate(report.created_at)}
                        </div>
                      </td>
                      <td className="px-6 py-4 text-slate-300">
                        {report.run_info ? (
                          <div>
                            <p className="text-sm font-semibold text-white">Run: {report.run_id}</p>
                            <p className="text-xs text-slate-400">{report.run_info.target_url}</p>
                            {report.run_info.finished_at && (
                              <p className="text-xs text-slate-500">Finished: {formatDate(report.run_info.finished_at)}</p>
                            )}
                          </div>
                        ) : (
                          <span className="text-xs text-slate-500">No run metadata</span>
                        )}
                      </td>
                      <td className="px-6 py-4">
                        <button
                          onClick={() => handleDownload(report.filename)}
                          className="inline-flex items-center gap-2 rounded-full border border-blue-400/40 bg-blue-500/10 px-4 py-2 text-xs font-semibold text-blue-100 transition hover:bg-blue-500/20"
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
        </div>
      </div>
    </div>
  );
};

export default Reports;
