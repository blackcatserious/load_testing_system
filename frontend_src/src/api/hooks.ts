import { useState, useEffect, useCallback } from 'react';
import {
  healthApi,
  metricsApi,
  runsApi,
  reportsApi,
  profilesApi,
  wafApi,
} from './api';
import type {
  HealthStatus,
  LiveMetrics,
  Run,
  RunDetails,
  Report,
  ClientProfile,
  TLSProfile,
  StartTestRequest,
  StopTestRequest,
} from './types';

export const useHealth = () => {
  const [data, setData] = useState<HealthStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchHealth = useCallback(async () => {
    try {
      setLoading(true);
      const health = await healthApi.getStatus();
      setData(health);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchHealth();
  }, [fetchHealth]);

  return { data, loading, error, refetch: fetchHealth };
};

export const useLiveMetrics = (intervalMs = 2000, includeAntiDetect = true) => {
  const [data, setData] = useState<LiveMetrics | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchMetrics = useCallback(async () => {
    try {
      const metrics = await metricsApi.getLiveMetrics(includeAntiDetect);
      setData(metrics);
      setError(null);
      if (loading) setLoading(false);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unknown error');
      if (loading) setLoading(false);
    }
  }, [includeAntiDetect, loading]);

  useEffect(() => {
    fetchMetrics();
    const interval = setInterval(fetchMetrics, intervalMs);
    return () => clearInterval(interval);
  }, [fetchMetrics, intervalMs]);

  return { data, loading, error, refetch: fetchMetrics };
};

export const useRuns = (limit = 50) => {
  const [data, setData] = useState<Run[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchRuns = useCallback(async () => {
    try {
      setLoading(true);
      const runs = await runsApi.getRuns(limit);
      setData(runs);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    fetchRuns();
  }, [fetchRuns]);

  return { data, loading, error, refetch: fetchRuns };
};

export const useRunDetails = (runId: string | null) => {
  const [data, setData] = useState<RunDetails | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchRunDetails = useCallback(async () => {
    if (!runId) return;
    
    try {
      setLoading(true);
      const details = await runsApi.getRunDetails(runId);
      setData(details);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  }, [runId]);

  useEffect(() => {
    if (runId) {
      fetchRunDetails();
    } else {
      setData(null);
      setError(null);
      setLoading(false);
    }
  }, [fetchRunDetails, runId]);

  return { data, loading, error, refetch: fetchRunDetails };
};

export const useReports = () => {
  const [data, setData] = useState<Report[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchReports = useCallback(async () => {
    try {
      setLoading(true);
      const reports = await reportsApi.getReports();
      setData(reports);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchReports();
  }, [fetchReports]);

  const downloadReport = useCallback(async (filename: string) => {
    try {
      const blob = await reportsApi.downloadReport(filename);
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);
    } catch (err) {
      throw new Error(err instanceof Error ? err.message : 'Download failed');
    }
  }, []);

  return { data, loading, error, refetch: fetchReports, downloadReport };
};

export const useClientProfiles = () => {
  const [data, setData] = useState<{ [key: string]: ClientProfile }>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchProfiles = useCallback(async () => {
    try {
      setLoading(true);
      const profiles = await profilesApi.getClientProfiles();
      setData(profiles);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchProfiles();
  }, [fetchProfiles]);

  return { data, loading, error, refetch: fetchProfiles };
};

export const useTLSProfiles = () => {
  const [data, setData] = useState<{ [key: string]: TLSProfile }>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchProfiles = useCallback(async () => {
    try {
      setLoading(true);
      const profiles = await profilesApi.getTLSProfiles();
      setData(profiles);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchProfiles();
  }, [fetchProfiles]);

  return { data, loading, error, refetch: fetchProfiles };
};

export const useTestActions = () => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const startTest = useCallback(async (request: StartTestRequest) => {
    try {
      setLoading(true);
      setError(null);
      const result = await runsApi.startTest(request);
      return result;
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Failed to start test';
      setError(errorMessage);
      throw new Error(errorMessage);
    } finally {
      setLoading(false);
    }
  }, []);

  const stopTest = useCallback(async (request: StopTestRequest) => {
    try {
      setLoading(true);
      setError(null);
      await runsApi.stopTest(request);
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Failed to stop test';
      setError(errorMessage);
      throw new Error(errorMessage);
    } finally {
      setLoading(false);
    }
  }, []);

  return { startTest, stopTest, loading, error };
};

export const useWAFDetection = () => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const detectWAF = useCallback(async (targetUrl: string) => {
    try {
      setLoading(true);
      setError(null);
      const result = await wafApi.detectWAF(targetUrl);
      return result;
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'WAF detection failed';
      setError(errorMessage);
      throw new Error(errorMessage);
    } finally {
      setLoading(false);
    }
  }, []);

  const getWAFStats = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const result = await wafApi.getWAFStats();
      return result;
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Failed to get WAF stats';
      setError(errorMessage);
      throw new Error(errorMessage);
    } finally {
      setLoading(false);
    }
  }, []);

  return { detectWAF, getWAFStats, loading, error };
};
