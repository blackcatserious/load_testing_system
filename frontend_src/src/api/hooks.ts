import { useCallback, useEffect, useMemo, useState } from 'react';
import { dashboardApi, testRunsApi, testPlansApi, reportsApi, legacyControlApi } from './api';
import type {
  DashboardMetrics,
  ReportSummary,
  TestPlan,
  TestRun,
  TestRunDetails,
} from './types';

const getErrorMessage = (err: unknown) => (err instanceof Error ? err.message : 'Unknown error');

export const useDashboardMetrics = (refreshMs = 15000, includeAntiDetect = true) => {
  const [data, setData] = useState<DashboardMetrics | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchMetrics = useCallback(async () => {
    try {
      const metrics = await dashboardApi.getMetrics(includeAntiDetect);
      setData(metrics);
      setError(null);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [includeAntiDetect]);

  useEffect(() => {
    void fetchMetrics();
    if (refreshMs <= 0) {
      return;
    }

    const interval = setInterval(() => {
      void fetchMetrics();
    }, refreshMs);

    return () => clearInterval(interval);
  }, [fetchMetrics, refreshMs]);

  return { data, loading, error, refetch: fetchMetrics };
};

export const useTestRuns = (limit = 50, refreshMs = 20000) => {
  const [data, setData] = useState<TestRun[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchRuns = useCallback(async () => {
    try {
      const runs = await testRunsApi.list(limit);
      setData(runs);
      setError(null);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    void fetchRuns();
    if (refreshMs <= 0) {
      return;
    }

    const interval = setInterval(() => {
      void fetchRuns();
    }, refreshMs);

    return () => clearInterval(interval);
  }, [fetchRuns, refreshMs]);

  return { data, loading, error, refetch: fetchRuns };
};

export const useRunDetails = (runId: string | null) => {
  const [data, setData] = useState<TestRunDetails | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchRunDetails = useCallback(async () => {
    if (!runId) {
      return;
    }

    try {
      setLoading(true);
      const details = await testRunsApi.get(runId);
      setData(details);
      setError(null);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [runId]);

  useEffect(() => {
    if (runId) {
      void fetchRunDetails();
    } else {
      setData(null);
      setError(null);
      setLoading(false);
    }
  }, [fetchRunDetails, runId]);

  return { data, loading, error, refetch: fetchRunDetails };
};

export const useTestPlans = (limit = 50) => {
  const [data, setData] = useState<TestPlan[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchPlans = useCallback(async () => {
    try {
      const plans = await testPlansApi.list(limit);
      setData(plans);
      setError(null);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    void fetchPlans();
  }, [fetchPlans]);

  return { data, loading, error, refetch: fetchPlans };
};

export const useReports = () => {
  const [data, setData] = useState<ReportSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchReports = useCallback(async () => {
    try {
      const reports = await reportsApi.list();
      setData(reports);
      setError(null);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void fetchReports();
  }, [fetchReports]);

  const downloadReport = useCallback(async (filename: string) => {
    const response = await legacyControlApi.downloadReport(filename);
    const blob = response.data;
    const url = window.URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    document.body.appendChild(anchor);
    anchor.click();
    window.URL.revokeObjectURL(url);
    document.body.removeChild(anchor);
  }, []);

  return { data, loading, error, refetch: fetchReports, downloadReport };
};

export const useRunSummary = (runs: TestRun[]) =>
  useMemo(() => {
    const byStatus = runs.reduce<Record<string, number>>((acc, run) => {
      const status = run.status || 'UNKNOWN';
      acc[status] = (acc[status] ?? 0) + 1;
      return acc;
    }, {});

    return byStatus;
  }, [runs]);
