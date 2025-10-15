import React, { useEffect, useState } from 'react';
import { Download, FileText, Share2 } from 'lucide-react';
import api from '../lib/api';
import LoadingState from '../components/LoadingState';
import ErrorState from '../components/ErrorState';
import dayjs from '../setupDayjs';
import type { TestReport } from '../types';

const Reports: React.FC = () => {
  const [reports, setReports] = useState<TestReport[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api
      .get<TestReport[]>('/reports')
      .then((response) => {
        setReports(response.data);
        setError(null);
      })
      .catch((err) => setError(err.message ?? 'Unable to load reports'))
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return <LoadingState label="Indexing reports" />;
  }

  if (error) {
    return <ErrorState description={error} />;
  }

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-8 px-4 pb-16 sm:px-6 lg:px-8">
      <header className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.35em] text-slate-500">Reports</p>
          <h1 className="mt-2 text-3xl font-semibold text-white">Intelligence archive</h1>
          <p className="mt-2 max-w-2xl text-sm text-slate-300">
            Publish findings faster with structured insights, pre-baked sections, and export automations for every stakeholder.
          </p>
        </div>
        <button className="flex items-center gap-2 rounded-full border border-slate-700/70 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:border-indigo-400/70 hover:text-white">
          <Share2 className="h-4 w-4" />
          Share workspace
        </button>
      </header>

      <section className="grid gap-6 md:grid-cols-2">
        {reports.map((report) => (
          <article key={report.id} className="flex flex-col gap-4 rounded-3xl border border-slate-800/80 bg-slate-900/40 p-6">
            <header className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <div className="text-xs uppercase tracking-[0.35em] text-slate-500">{report.planId}</div>
                <h2 className="mt-2 text-xl font-semibold text-white">{report.title}</h2>
                <p className="mt-2 text-xs text-slate-400">Generated {dayjs(report.generatedAt).fromNow()} by {report.author}</p>
              </div>
              <span className="rounded-full bg-indigo-500/20 px-3 py-1 text-xs font-semibold uppercase tracking-[0.35em] text-indigo-200">
                {report.format}
              </span>
            </header>
            <div className="grid gap-3 rounded-2xl border border-slate-800/60 bg-slate-900/60 p-4 text-sm text-slate-200">
              <div className="grid grid-cols-2 gap-2 text-[11px] uppercase tracking-[0.35em] text-slate-500">
                <p>Peak throughput</p>
                <p>{report.metrics.peakThroughput.toLocaleString()} rps</p>
                <p>Average latency</p>
                <p>{report.metrics.avgLatency} ms</p>
                <p>Error rate</p>
                <p>{report.metrics.errorRate}%</p>
                <p>Availability</p>
                <p>{report.metrics.availability}%</p>
              </div>
            </div>
            <div className="grid gap-3 text-sm text-slate-300">
              {report.sections.map((section) => (
                <div key={section.title} className="rounded-2xl border border-slate-800/60 bg-slate-900/60 p-4">
                  <p className="text-xs uppercase tracking-[0.35em] text-slate-500">{section.title}</p>
                  <p className="mt-2 text-sm text-slate-200">{section.summary}</p>
                  <ul className="mt-3 flex list-disc flex-col gap-1 pl-5 text-xs text-slate-400">
                    {section.insights.map((insight) => (
                      <li key={insight}>{insight}</li>
                    ))}
                  </ul>
                </div>
              ))}
            </div>
            <footer className="flex flex-wrap items-center justify-between gap-3">
              <div className="text-xs text-slate-400">Run {report.runId} • Last updated {dayjs(report.generatedAt).format('MMM D, HH:mm')}</div>
              <div className="flex items-center gap-2">
                <button className="flex items-center gap-2 rounded-full border border-slate-700/70 px-4 py-2 text-xs font-semibold text-slate-200 transition hover:border-indigo-400/70 hover:text-white">
                  <FileText className="h-4 w-4" />
                  Open
                </button>
                <button className="flex items-center gap-2 rounded-full bg-gradient-to-r from-indigo-500 via-blue-500 to-cyan-400 px-4 py-2 text-xs font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:brightness-105">
                  <Download className="h-4 w-4" />
                  Export
                </button>
              </div>
            </footer>
          </article>
        ))}
      </section>
    </div>
  );
};

export default Reports;
