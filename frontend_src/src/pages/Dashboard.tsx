import React, { useState, useEffect } from 'react';
import { BarChart3, Activity, FileText, Settings as SettingsIcon, Database, Play, TrendingUp, Users, Clock, AlertTriangle } from 'lucide-react';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts';
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

  const { data: metrics, error: metricsError } = useLiveMetrics(5000);

  const [engine, setEngine] = useState(localStorage.getItem('engine') || 'playwright');
  const [behaviorProfile, setBehaviorProfile] = useState(localStorage.getItem('behaviorProfile') || 'casual');
  const [threads, setThreads] = useState(40);
  const [duration, setDuration] = useState(3600);
  const [requestDelay, setRequestDelay] = useState(100);
  const [targetCount, setTargetCount] = useState(3);
  const [unlimitedMode, setUnlimitedMode] = useState(true);

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
                {metrics?.error_rate ? (metrics.error_rate * 100).toFixed(1) : '0.0'}%
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
                style={{ width: `${Math.min(100, ((metrics?.error_rate || 0) * 100))}%` }}
              ></div>
            </div>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Request Rate Over Time</h3>
          <div className="h-80">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart
                data={metrics?.time_series || []}
                margin={{ top: 20, right: 30, left: 20, bottom: 5 }}
              >
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="timestamp" />
                <YAxis />
                <Tooltip />
                <Bar dataKey="rps" fill="#3B82F6" />
              </BarChart>
            </ResponsiveContainer>
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
          <p>⚠️ UNLIMITED MODE ENABLED: 100,000+ threads | 10M+ proxies | 20-30 parallel groups</p>
          <p>🎯 Target degradation mode: 503/524 errors | Infrastructure attack: DNS + CDN</p>
          <p>🔄 Advanced methods: HTTP/2 flood, Slowloris, TLS abuse, Crawl &amp; Drown</p>
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
                  onClick={() => setActiveTab(tab.id)}
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
