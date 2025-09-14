import React, { useState, useEffect } from 'react';
import { BarChart3, Activity, FileText, Settings as SettingsIcon, Database, TrendingUp, Users, Clock, AlertTriangle } from 'lucide-react';
import { Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts';
import { useLiveMetrics } from '../api/hooks';

interface GroupRun {
  group_id: string;
  status: 'running' | 'completed' | 'failed';
  duration: number;
  targets_count: number;
  started_at: string;
  top_errors: string[];
}

type TabType = 'overview' | 'runs' | 'details' | 'logs' | 'settings' | 'reports';

const Dashboard: React.FC<{}> = () => {
  const [activeTab, setActiveTab] = useState<TabType>('overview');
  const [groupRuns, setGroupRuns] = useState<GroupRun[]>([]);
  const [unlimitedMetrics, setUnlimitedMetrics] = useState<any>(null);

  const { data: metrics, error: metricsError } = useLiveMetrics(5000);

  const [engine] = useState(localStorage.getItem('engine') || 'playwright');
  const [behaviorProfile] = useState(localStorage.getItem('behaviorProfile') || 'casual');
  const [threads] = useState(40);
  const [duration] = useState(3600);
  const [unlimitedMode] = useState(true);

  useEffect(() => {
    const fetchGroupRuns = async () => {
      try {
        const response = await fetch('/api/group_runs_endpoint.php?action=list');
        if (response.ok) {
          const data = await response.json();
          setGroupRuns(data.groups || []);
        }
      } catch (err) {
        console.error('Failed to fetch group runs:', err);
      }
    };

    fetchGroupRuns();
  }, []);

  useEffect(() => {
    localStorage.setItem('engine', engine);
    localStorage.setItem('behaviorProfile', behaviorProfile);
  }, [engine, behaviorProfile]);

  useEffect(() => {
    const fetchUnlimitedMetrics = async () => {
      try {
        const response = await fetch('/api/metrics_endpoint.php');
        if (response.ok) {
          const data = await response.json();
          setUnlimitedMetrics(data.unlimited_system_stats);
        }
      } catch (error) {
        console.error('Failed to fetch unlimited metrics:', error);
      }
    };
    
    fetchUnlimitedMetrics();
    const interval = setInterval(fetchUnlimitedMetrics, 5000); // Update every 5 seconds
    return () => clearInterval(interval);
  }, []);

  const handleStartAttack = async (groupId: string) => {
    try {
      const response = await fetch('/api/start_endpoint.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'start',
          group_id: groupId,
          profile_id: 'ramp-up',
          threads: unlimitedMode ? 100000 : threads,
          duration: unlimitedMode ? 2592000 : duration, // 30 days if unlimited
          engine: engine,
          behavior_profile_id: behaviorProfile
        })
      });

      if (response.ok) {
        const fetchGroupRuns = async () => {
          try {
            const response = await fetch('/api/group_runs_endpoint.php?action=list');
            if (response.ok) {
              const data = await response.json();
              setGroupRuns(data.groups || []);
            }
          } catch (err) {
            console.error('Failed to fetch group runs:', err);
          }
        };
        fetchGroupRuns();
      }
    } catch (err) {
      console.error('Failed to start attack:', err);
    }
  };

  const handleStopAttack = async (groupId: string) => {
    try {
      const response = await fetch('/api/stop_endpoint.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'stop',
          group_id: groupId
        })
      });

      if (response.ok) {
        const fetchGroupRuns = async () => {
          try {
            const response = await fetch('/api/group_runs_endpoint.php?action=list');
            if (response.ok) {
              const data = await response.json();
              setGroupRuns(data.groups || []);
            }
          } catch (err) {
            console.error('Failed to fetch group runs:', err);
          }
        };
        fetchGroupRuns();
      }
    } catch (err) {
      console.error('Failed to stop attack:', err);
    }
  };

  const tabs = [
    { id: 'overview', label: 'Overview', icon: BarChart3 },
    { id: 'runs', label: 'Runs', icon: Activity },
    { id: 'logs', label: 'Logs', icon: FileText },
    { id: 'settings', label: 'Settings', icon: SettingsIcon },
    { id: 'reports', label: 'Reports', icon: Database }
  ];

  const statusCodeData = [
    { name: '2xx', value: metrics?.status_codes?.['2xx'] || 0, color: '#10B981' },
    { name: '4xx', value: metrics?.status_codes?.['4xx'] || 0, color: '#F59E0B' },
    { name: '5xx', value: metrics?.status_codes?.['5xx'] || 0, color: '#EF4444' }
  ];

  const renderOverviewTab = () => (
    <div className="space-y-6">
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-500">Requests Per Second</p>
              <h3 className="text-2xl font-bold text-gray-900 mt-1">
                {metrics?.rps || 0}
              </h3>
            </div>
            <div className="bg-blue-100 p-3 rounded-full">
              <TrendingUp className="h-6 w-6 text-blue-600" />
            </div>
          </div>
          <div className="mt-4">
            <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
              <div
                className="h-full bg-blue-600 rounded-full"
                style={{ width: `${Math.min(100, ((metrics?.rps || 0) / 100) * 100)}%` }}
              ></div>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-500">Active Threads</p>
              <h3 className="text-2xl font-bold text-gray-900 mt-1">
                {metrics?.active_threads || 0}
              </h3>
            </div>
            <div className="bg-green-100 p-3 rounded-full">
              <Users className="h-6 w-6 text-green-600" />
            </div>
          </div>
          <div className="mt-4">
            <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
              <div
                className="h-full bg-green-600 rounded-full"
                style={{ width: `${Math.min(100, ((metrics?.active_threads || 0) / threads) * 100)}%` }}
              ></div>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-500">Avg. Latency (ms)</p>
              <h3 className="text-2xl font-bold text-gray-900 mt-1">
                {metrics?.avg_latency || 0}
              </h3>
            </div>
            <div className="bg-yellow-100 p-3 rounded-full">
              <Clock className="h-6 w-6 text-yellow-600" />
            </div>
          </div>
          <div className="mt-4">
            <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
              <div
                className="h-full bg-yellow-600 rounded-full"
                style={{ width: `${Math.min(100, ((metrics?.avg_latency || 0) / 1000) * 100)}%` }}
              ></div>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-500">Error Rate (%)</p>
              <h3 className="text-2xl font-bold text-gray-900 mt-1">
                {metrics?.success_rate ? ((1 - metrics.success_rate) * 100).toFixed(1) : '0.0'}%
              </h3>
            </div>
            <div className="bg-red-100 p-3 rounded-full">
              <AlertTriangle className="h-6 w-6 text-red-600" />
            </div>
          </div>
          <div className="mt-4">
            <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
              <div
                className="h-full bg-red-600 rounded-full"
                style={{ width: `${Math.min(100, ((1 - (metrics?.success_rate || 1)) * 100))}%` }}
              ></div>
            </div>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Current Performance</h3>
          <div className="grid grid-cols-2 gap-4">
            <div className="text-center">
              <p className="text-sm text-gray-500">Current RPS</p>
              <p className="text-2xl font-bold text-blue-600">{metrics?.current_rps || 0}</p>
            </div>
            <div className="text-center">
              <p className="text-sm text-gray-500">Total Requests</p>
              <p className="text-2xl font-bold text-green-600">{metrics?.total_requests || 0}</p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Status Code Distribution</h3>
          <div className="h-80 flex items-center justify-center">
            {metrics ? (
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie
                    data={statusCodeData}
                    cx="50%"
                    cy="50%"
                    labelLine={false}
                    outerRadius={80}
                    fill="#8884d8"
                    dataKey="value"
                    label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                  >
                    {statusCodeData.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={entry.color} />
                    ))}
                  </Pie>
                  <Tooltip />
                </PieChart>
              </ResponsiveContainer>
            ) : (
              <div className="text-gray-500">No data available</div>
            )}
          </div>
        </div>
      </div>

      <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">Active Runs</h3>
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Group ID</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Targets</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Started</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {groupRuns.filter(run => run.status === 'running').length > 0 ? (
                groupRuns.filter(run => run.status === 'running').map((run) => (
                  <tr key={run.group_id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                      {run.group_id}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      <div className="flex flex-wrap gap-1">
                        <span className="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">
                          {run.targets_count} targets
                        </span>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                        {run.status}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {Math.floor(run.duration / 3600)}h {Math.floor((run.duration % 3600) / 60)}m
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {new Date(run.started_at).toLocaleString()}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                      <button 
                        onClick={() => handleStopAttack(run.group_id)}
                        className="bg-red-600 text-white px-3 py-1 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                      >
                        Stop Attack
                      </button>
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={6} className="px-6 py-4 text-center text-gray-500">
                    No active runs
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* UNLIMITED SYSTEM METRICS */}
      {unlimitedMetrics && (
        <div className="bg-gradient-to-r from-blue-600 to-blue-800 rounded-lg shadow-lg border border-blue-700 p-6 text-white mt-6">
          <h3 className="text-xl font-bold mb-4">📊 UNLIMITED SYSTEM METRICS</h3>
          <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div className="text-center">
              <div className="text-2xl font-bold">{unlimitedMetrics.parallel_groups_active || 0}</div>
              <div className="text-sm opacity-90">Active Groups</div>
            </div>
            <div className="text-center">
              <div className="text-2xl font-bold">{unlimitedMetrics.total_concurrent_threads?.toLocaleString() || '0'}</div>
              <div className="text-sm opacity-90">Total Threads</div>
            </div>
            <div className="text-center">
              <div className="text-2xl font-bold">{unlimitedMetrics.proxy_pool_size?.toLocaleString() || '0'}</div>
              <div className="text-sm opacity-90">Proxy Pool</div>
            </div>
            <div className="text-center">
              <div className="text-2xl font-bold">{unlimitedMetrics.proxy_rotation_rate || '0/min'}</div>
              <div className="text-sm opacity-90">Proxy Rotation</div>
            </div>
            <div className="text-center">
              <div className="text-2xl font-bold">{unlimitedMetrics.network_throughput || '0 MB/s'}</div>
              <div className="text-sm opacity-90">Network</div>
            </div>
          </div>
          <div className="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm opacity-90">
            <div>
              <p>🔄 Last proxy update: {unlimitedMetrics.proxy_collection_last_update || 'Never'}</p>
            </div>
            <div>
              <p>💾 Memory: {unlimitedMetrics.system_memory_usage || '0%'}</p>
            </div>
            <div>
              <p>🖥️ CPU: {unlimitedMetrics.cpu_usage || '0%'}</p>
            </div>
          </div>
          <div className="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4 text-sm opacity-90">
            <div>
              <p>🎯 Attack Methods: {unlimitedMetrics.attack_methods_active ? Object.values(unlimitedMetrics.attack_methods_active).reduce((sum: number, count: any) => sum + (Number(count) || 0), 0) : 0}</p>
            </div>
            <div>
              <p>🔀 Stealth Sessions: {unlimitedMetrics.stealth_rotation_stats?.ja3_rotations_per_min || 0}/min</p>
            </div>
            <div>
              <p>🌐 Proxy Sources: {unlimitedMetrics.proxy_sources_active || 0}</p>
            </div>
            <div>
              <p>⚡ Escalation: {unlimitedMetrics.escalation_engine_status?.auto_scaling_enabled ? 'Active' : 'Inactive'}</p>
            </div>
          </div>
        </div>
      )}
    </div>
  );

  const renderTabContent = () => {
    switch (activeTab) {
      case 'overview':
        return renderOverviewTab();
      case 'runs':
        return (
          <div className="space-y-6">
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <div className="flex justify-between items-center mb-4">
                <h3 className="text-lg font-semibold text-gray-900">Launch History & Active Runs</h3>
                <button className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                  Export History
                </button>
              </div>
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Group ID</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Targets</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Result</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Started</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {groupRuns.length > 0 ? (
                      groupRuns.map((run) => (
                        <tr key={run.group_id} className="hover:bg-gray-50">
                          <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {run.group_id}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <div className="flex flex-wrap gap-1">
                              <span className="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">
                                {run.targets_count} targets
                              </span>
                            </div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                              run.status === 'running' ? 'bg-green-100 text-green-800' :
                              run.status === 'completed' ? 'bg-blue-100 text-blue-800' :
                              'bg-red-100 text-red-800'
                            }`}>
                              {run.status}
                            </span>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {Math.floor(run.duration / 3600)}h {Math.floor((run.duration % 3600) / 60)}m
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {(run.top_errors || []).slice(0, 3).map((error, i) => (
                              <span key={i} className={`inline-block text-xs px-2 py-1 rounded mr-1 ${
                                error === '200' ? 'bg-green-100 text-green-800' :
                                error === '404' ? 'bg-yellow-100 text-yellow-800' :
                                error === '503' || error === '524' ? 'bg-red-100 text-red-800' :
                                'bg-gray-100 text-gray-800'
                              }`}>
                                {error}
                              </span>
                            ))}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {new Date(run.started_at).toLocaleString()}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button className="text-blue-600 hover:text-blue-900 mr-3">
                              Download Report
                            </button>
                            {run.status === 'running' ? (
                              <button 
                                onClick={() => handleStopAttack(run.group_id)}
                                className="bg-red-600 text-white px-3 py-1 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                              >
                                Stop Attack
                              </button>
                            ) : (
                              <button 
                                onClick={() => handleStartAttack(run.group_id)}
                                className="bg-green-600 text-white px-3 py-1 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
                              >
                                Start Attack
                              </button>
                            )}
                          </td>
                        </tr>
                      ))
                    ) : (
                      <tr>
                        <td colSpan={7} className="px-6 py-4 text-center text-gray-500">
                          No launch history available
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        );
      case 'details':
        return <div className="p-6">Run details content coming soon...</div>;
      case 'logs':
        return (
          <div className="space-y-6">
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-lg font-semibold text-gray-900">Backend Logs</h3>
                <button className="bg-gray-100 text-gray-700 px-3 py-2 rounded-md text-sm hover:bg-gray-200">
                  Refresh
                </button>
              </div>
              <div className="bg-gray-900 text-green-400 p-4 rounded-lg font-mono text-sm max-h-96 overflow-y-auto">
                <div className="space-y-1">
                  <div>[2025-08-06 18:14:51] INFO: System initialized successfully</div>
                  <div>[2025-08-06 18:14:52] INFO: Metrics endpoint responding</div>
                  <div>[2025-08-06 18:14:53] INFO: Group runs endpoint active</div>
                  <div>[2025-08-06 18:14:54] INFO: Live metrics updating every 5 seconds</div>
                  <div>[2025-08-06 18:14:55] INFO: RPS: 46, Threads: 40, Latency: 189ms</div>
                  <div>[2025-08-06 18:14:56] INFO: Status codes - 2xx: 95.8%, 4xx: 2.1%, 5xx: 2.1%</div>
                  <div>[2025-08-06 18:14:57] INFO: System status: Online</div>
                </div>
              </div>
            </div>
          </div>
        );
      case 'settings':
        return <div className="p-6">Settings content coming soon...</div>;
      case 'reports':
        return (
          <div className="space-y-6">
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Generated Reports</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div className="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                  <div className="flex items-center justify-between mb-2">
                    <h4 className="font-medium text-gray-900">Group Run Report</h4>
                    <span className="text-xs text-gray-500">CSV</span>
                  </div>
                  <p className="text-sm text-gray-600 mb-3">Latest group execution results</p>
                  <button className="w-full bg-blue-600 text-white px-3 py-2 rounded-md text-sm hover:bg-blue-700">
                    Download
                  </button>
                </div>
                <div className="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                  <div className="flex items-center justify-between mb-2">
                    <h4 className="font-medium text-gray-900">Metrics Summary</h4>
                    <span className="text-xs text-gray-500">JSON</span>
                  </div>
                  <p className="text-sm text-gray-600 mb-3">Performance metrics overview</p>
                  <button className="w-full bg-blue-600 text-white px-3 py-2 rounded-md text-sm hover:bg-blue-700">
                    Download
                  </button>
                </div>
                <div className="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                  <div className="flex items-center justify-between mb-2">
                    <h4 className="font-medium text-gray-900">Error Analysis</h4>
                    <span className="text-xs text-gray-500">CSV</span>
                  </div>
                  <p className="text-sm text-gray-600 mb-3">Detailed error code breakdown</p>
                  <button className="w-full bg-blue-600 text-white px-3 py-2 rounded-md text-sm hover:bg-blue-700">
                    Download
                  </button>
                </div>
              </div>
            </div>
          </div>
        );
      default:
        return renderOverviewTab();
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">Dashboard</h1>
          <p className="text-gray-600 mt-1">
            Manage and monitor your load testing operations
          </p>
        </div>
        <div className="flex items-center space-x-2">
          <div className={`w-3 h-3 rounded-full animate-pulse ${
            metricsError ? 'bg-red-500' : metrics ? 'bg-green-500' : 'bg-yellow-500'
          }`}></div>
          <span className="text-sm text-gray-600">
            {metricsError ? 'System Error' : metrics ? 'System Online' : 'Connecting...'}
          </span>
        </div>
      </div>

      {/* BATTLE ATTACK CONTROLS - UNLIMITED MODE */}
      <div className="bg-gradient-to-r from-red-600 to-red-800 rounded-lg shadow-lg border border-red-700 p-6 text-white">
        <h3 className="text-xl font-bold mb-4">🚀 BATTLE ATTACK CONTROLS - UNLIMITED MODE</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <button 
              onClick={() => handleStartAttack('battle_mode')}
              className="w-full bg-green-600 text-white px-6 py-4 rounded-lg hover:bg-green-700 focus:outline-none focus:ring-4 focus:ring-green-500 font-bold text-lg shadow-lg transform hover:scale-105 transition-all"
            >
              ⚡ START MAXIMUM ATTACK (100K+ THREADS)
            </button>
          </div>
          <div>
            <button 
              onClick={() => handleStopAttack('all')}
              className="w-full bg-red-600 text-white px-6 py-4 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-4 focus:ring-red-500 font-bold text-lg shadow-lg transform hover:scale-105 transition-all"
            >
              🛑 STOP ALL ATTACKS
            </button>
          </div>
        </div>
        <div className="mt-4 text-sm opacity-90">
          <p>⚠️ UNLIMITED MODE ENABLED: 100,000+ threads | 10M+ proxies | Unlimited parallel groups</p>
          <p>🎯 Target degradation mode: 503/524 errors | Infrastructure attack: DNS + CDN</p>
          <p>🔄 Advanced methods: HTTP/2 flood, Slowloris, TLS abuse, Crawl &amp; Drown</p>
        </div>
      </div>

      {/* UNLIMITED PARALLEL GROUPS CONFIGURATION */}
      <div className="bg-gradient-to-r from-purple-600 to-purple-800 rounded-lg shadow-lg border border-purple-700 p-6 text-white">
        <h3 className="text-xl font-bold mb-4">🔄 UNLIMITED PARALLEL GROUPS CONFIGURATION</h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label className="block text-sm font-medium mb-2">Parallel Groups Count</label>
            <input 
              type="number" 
              min="1" 
              max="1000" 
              defaultValue="100"
              className="w-full px-3 py-2 bg-purple-700 border border-purple-600 rounded-md text-white placeholder-purple-300"
              placeholder="Unlimited (default: 100)"
            />
            <p className="text-xs mt-1 opacity-75">Set to 1000 for maximum parallel execution</p>
          </div>
          <div>
            <label className="block text-sm font-medium mb-2">Threads Per Group</label>
            <input 
              type="number" 
              min="1000" 
              max="100000" 
              defaultValue="10000"
              className="w-full px-3 py-2 bg-purple-700 border border-purple-600 rounded-md text-white placeholder-purple-300"
              placeholder="10,000"
            />
            <p className="text-xs mt-1 opacity-75">Recommended: 10,000-100,000 per group</p>
          </div>
          <div>
            <label className="block text-sm font-medium mb-2">Group Launch Interval (ms)</label>
            <input 
              type="number" 
              min="100" 
              max="5000" 
              defaultValue="1000"
              className="w-full px-3 py-2 bg-purple-700 border border-purple-600 rounded-md text-white placeholder-purple-300"
              placeholder="1000ms"
            />
            <p className="text-xs mt-1 opacity-75">Delay between launching parallel groups</p>
          </div>
        </div>
        <div className="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium mb-2">Attack Strategy</label>
            <select className="w-full px-3 py-2 bg-purple-700 border border-purple-600 rounded-md text-white">
              <option value="adaptive">Adaptive Escalation</option>
              <option value="aggressive">Maximum Aggressive</option>
              <option value="stealth">Stealth Mode</option>
              <option value="mixed">Mixed Strategy</option>
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium mb-2">Target Distribution</label>
            <select className="w-full px-3 py-2 bg-purple-700 border border-purple-600 rounded-md text-white">
              <option value="round_robin">Round Robin</option>
              <option value="random">Random Distribution</option>
              <option value="weighted">Weighted by Response</option>
              <option value="focused">Focus on Weakest</option>
            </select>
          </div>
        </div>
        <div className="mt-4 flex items-center space-x-4">
          <label className="flex items-center">
            <input type="checkbox" defaultChecked className="mr-2 rounded bg-purple-700 border-purple-600" />
            <span className="text-sm">Enable Auto-Scaling</span>
          </label>
          <label className="flex items-center">
            <input type="checkbox" defaultChecked className="mr-2 rounded bg-purple-700 border-purple-600" />
            <span className="text-sm">Dynamic Thread Adjustment</span>
          </label>
          <label className="flex items-center">
            <input type="checkbox" defaultChecked className="mr-2 rounded bg-purple-700 border-purple-600" />
            <span className="text-sm">Failure Recovery</span>
          </label>
        </div>
        <div className="mt-4 text-xs opacity-75">
          <p>💡 Unlimited mode removes all hardcoded limits. System will scale based on available resources.</p>
          <p>⚡ Each parallel group operates independently with its own proxy pool and attack strategy.</p>
        </div>
      </div>

      {/* PROXY COLLECTION & ROTATION */}
      <div className="bg-gradient-to-r from-green-600 to-green-800 rounded-lg shadow-lg border border-green-700 p-6 text-white">
        <h3 className="text-xl font-bold mb-4">🔄 PROXY COLLECTION & ROTATION</h3>
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label className="block text-sm font-medium mb-2">Collection Interval</label>
            <select className="w-full px-3 py-2 bg-green-700 border border-green-600 rounded-md text-white">
              <option value="600">10 minutes</option>
              <option value="300">5 minutes</option>
              <option value="1800">30 minutes</option>
            </select>
            <p className="text-xs mt-1 opacity-75">Automatic proxy collection frequency</p>
          </div>
          <div>
            <label className="block text-sm font-medium mb-2">Rotation Speed</label>
            <select className="w-full px-3 py-2 bg-green-700 border border-green-600 rounded-md text-white">
              <option value="1">Every request</option>
              <option value="10">Every 10 requests</option>
              <option value="100">Every 100 requests</option>
            </select>
            <p className="text-xs mt-1 opacity-75">How often to rotate proxies</p>
          </div>
          <div>
            <label className="block text-sm font-medium mb-2">Max Pool Size</label>
            <input 
              type="number" 
              min="10000" 
              max="10000000" 
              defaultValue="1000000"
              className="w-full px-3 py-2 bg-green-700 border border-green-600 rounded-md text-white placeholder-green-300"
              placeholder="1M proxies"
            />
            <p className="text-xs mt-1 opacity-75">Maximum proxies to maintain in pool</p>
          </div>
          <div className="flex items-end">
            <button className="w-full bg-green-500 text-white px-4 py-2 rounded-md hover:bg-green-400 focus:outline-none focus:ring-2 focus:ring-green-300">
              🔄 Force Update Now
            </button>
          </div>
        </div>
        <div className="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label className="block text-sm font-medium mb-2">Proxy Sources</label>
            <select className="w-full px-3 py-2 bg-green-700 border border-green-600 rounded-md text-white">
              <option value="all">All Sources (GitHub + APIs)</option>
              <option value="github">GitHub Only</option>
              <option value="apis">APIs Only</option>
              <option value="custom">Custom Sources</option>
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium mb-2">Geo Distribution</label>
            <select className="w-full px-3 py-2 bg-green-700 border border-green-600 rounded-md text-white">
              <option value="global">Global Mix</option>
              <option value="us_eu">US + EU Focus</option>
              <option value="asia">Asia Focus</option>
              <option value="residential">Residential Only</option>
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium mb-2">Health Check</label>
            <select className="w-full px-3 py-2 bg-green-700 border border-green-600 rounded-md text-white">
              <option value="aggressive">Aggressive (every 30s)</option>
              <option value="moderate">Moderate (every 2min)</option>
              <option value="conservative">Conservative (every 5min)</option>
            </select>
          </div>
        </div>
        <div className="mt-4 flex items-center space-x-4">
          <label className="flex items-center">
            <input type="checkbox" defaultChecked className="mr-2 rounded bg-green-700 border-green-600" />
            <span className="text-sm">Auto-Remove Dead Proxies</span>
          </label>
          <label className="flex items-center">
            <input type="checkbox" defaultChecked className="mr-2 rounded bg-green-700 border-green-600" />
            <span className="text-sm">Randomize User-Agents</span>
          </label>
          <label className="flex items-center">
            <input type="checkbox" defaultChecked className="mr-2 rounded bg-green-700 border-green-600" />
            <span className="text-sm">TLS Fingerprint Rotation</span>
          </label>
        </div>
        <div className="mt-4 text-xs opacity-75">
          <p>🌐 System automatically collects 10M+ proxies from multiple sources every 10 minutes</p>
          <p>🔄 Advanced rotation includes IP, User-Agent, TLS fingerprint, and geographic distribution</p>
        </div>
      </div>

      <div className="bg-white rounded-lg shadow-sm border border-gray-200">
        <div className="border-b border-gray-200">
          <nav className="flex space-x-8 px-6" aria-label="Tabs">
            {tabs.map((tab) => {
              const Icon = tab.icon;
              return (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id as TabType)}
                  className={`${
                    activeTab === tab.id
                      ? 'border-blue-500 text-blue-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                  } whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center space-x-2`}
                >
                  <Icon className="h-4 w-4" />
                  <span>{tab.label}</span>
                </button>
              );
            })}
          </nav>
        </div>
        <div className="p-6">
          {renderTabContent()}
        </div>
      </div>
    </div>
  );
};

export default Dashboard;
